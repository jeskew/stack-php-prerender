<?php

namespace Jeskew\StackPhPrerender;


use DateTime;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class Kernel implements HttpKernelInterface
{
    /**
     * @var \Symfony\Component\HttpKernel\HttpKernelInterface
     */
    protected $app;

    /**
     * @var \GuzzleHttp\Client
     */
    protected $client;

    protected $blacklist;

    protected $whitelist;

    protected $prerenderToken;

    protected $backendUrl;

    protected $ignoredExtensions = [];

    protected $botUserAgents = [];

    public function __construct(HttpKernelInterface $app, array $options = [])
    {
        $this->app = $app;
        $this->parseOptions($options);
    }

    public function handle(Request $request, $type = self::MASTER_REQUEST, $check = true)
    {
        if ($this->shouldPrerenderPage($request) && isset($this->client)) {
            $response = new Response();

            try {
                $preRendered = $this->client->send(
                    $this->generatePrerenderRequest($request)
                );

                $response->setContent((string) $preRendered->getBody());
                $response->setStatusCode($preRendered->getStatusCode());
            } catch (RequestException $e) {
                $response->setStatusCode($e->getResponse()->getStatusCode());
            } catch (Exception $e) {
                $response->setStatusCode(500);
            }

            $response->headers->set(
                'X-Prerendered-On', (new DateTime)->format(DateTime::ATOM)
            );

            return $response;
        }

        return $this->app->handle($request, $type, $check);
    }

    public function shouldPrerenderPage(Request $request)
    {
        if ($this->isCacheable($request) && $this->isPrerenderable($request)) {
            if ($this->isMappedAjaxCrawl($request)) {
                return true;
            }

            if ($this->isBot($request)) {
                return true;
            }
        }

        return false;
    }

    public function setBackendUrl($url)
    {
        $parsedUrl = parse_url($url);

        if ($parsedUrl && isset($parsedUrl['host'])) {
            $this->backendUrl = rtrim($url, '/') . '/';

            return $this;
        }

        throw new InvalidArgumentException('Invalid backend url');
    }

    public function setClient(Client $client)
    {
        $this->client = $client;

        return $this;
    }

    public function setPrerenderToken($token)
    {
        $this->prerenderToken = $token;

        return $this;
    }

    public function prerenderExtensions($extensions)
    {
        $this->applyMethod('prerenderExtension', $extensions);

        return $this;
    }

    public function skipExtensions($extensions)
    {
        $this->applyMethod('skipExtension', $extensions);

        return $this;
    }

    public function prerenderForUserAgents($bots)
    {
        $this->applyMethod('prerenderForBot', $bots);

        return $this;
    }

    public function skipForUserAgents($bots)
    {
        $this->applyMethod('skipForBot', $bots);

        return $this;
    }

    public function addToBlacklist($paths)
    {
        $this->applyMethod('blacklistPath', $paths);

        return $this;
    }

    public function dropFromBlacklist($paths)
    {
        $this->applyMethod('forgetBlacklisted', $paths);

        return $this;
    }

    public function addToWhitelist($paths)
    {
        $this->applyMethod('whitelistPath', $paths);

        return $this;
    }

    public function dropFromWhitelist($paths)
    {
        $this->applyMethod('forgetWhitelisted', $paths);

        return $this;
    }


    protected function generatePrerenderRequest(Request $request)
    {
        $prerenderRequest = $this->client
            ->createRequest('GET', $this->backendUrl . $request->getUri());

        if ($this->prerenderToken) {
            $prerenderRequest->addHeader('X-Prerender-Token', $this->prerenderToken);
        }

        return $prerenderRequest;
    }

    protected function parseOptions(array $options)
    {
        $this->addToBlacklist($this->getWithDefault($options, 'blacklist'));
        $this->addToWhitelist($this->getWithDefault($options, 'whitelist'));
        $this->setPrerenderToken($this->getWithDefault($options, 'prerenderToken'));

        $this->setClient($this->getWithDefault(
            $options,
            'client',
            DefaultSettings::guzzleClientFactory()
        ));

        $this->setBackendUrl($this->getWithDefault(
            $options,
            'backendUrl',
            DefaultSettings::$backendUrl
        ));

        $this->skipExtensions($this->getWithDefault(
            $options,
            'ignoredExtensions',
            DefaultSettings::$ignoredExtensions
        ));

        $this->prerenderForUserAgents($this->getWithDefault(
            $options,
            'botUserAgents',
            DefaultSettings::$botUserAgents
        ));
    }

    protected function getWithDefault(array $array, $key, $default = null)
    {
        return isset($array[$key]) ? $array[$key] : $default;
    }

    protected function applyMethod($name, $args)
    {
        return $this->xArgs([$this, $name], $args);
    }

    protected function xArgs(callable $callable, $crossApplicable)
    {
        if (null === $crossApplicable) {
            return null;
        } elseif (!is_array($crossApplicable)) {
            $crossApplicable = [$crossApplicable];
        }

        $results = [];

        foreach ($crossApplicable as $arg) {
            $results []= call_user_func($callable, $arg);
        }

        return $results;
    }

    protected function prerenderExtension($extension)
    {
        unset($this->ignoredExtensions[$this->normalizeExtension($extension)]);

        return $this;
    }

    protected function skipExtension($extension)
    {
        $this->ignoredExtensions[$this->normalizeExtension($extension)] = true;

        return $this;
    }

    protected function prerenderForBot($bot)
    {
        $this->botUserAgents[$bot] = true;

        return $this;
    }

    protected function skipForBot($bot)
    {
        unset($this->botUserAgents[$bot]);

        return $this;
    }

    protected function blacklistPath($path)
    {
        $this->blacklist[$path] = true;

        return $this;
    }

    protected function forgetBlacklisted($path)
    {
        unset($this->blacklist[$path]);

        return $this;
    }

    protected function whitelistPath($path)
    {
        $this->whitelist[$path] = true;

        return $this;
    }

    protected function forgetWhitelisted($path)
    {
        unset($this->whitelist[$path]);

        return $this;
    }

    protected function normalizeExtension($extension)
    {
        return '.' . ltrim($extension, '.');
    }

    protected function getIgnoredExtensions()
    {
        return array_keys($this->ignoredExtensions);
    }

    protected function isCacheable(Request $request)
    {
        return 'GET' === $request->getRealMethod();
    }

    protected function isPrerenderable(Request $request)
    {
        if ($this->isIgnoredExtension($request)) {
            return false;
        }

        if (!empty($this->whitelist) && $this->isWhitelisted($request)) {
            return true;
        }

        if (!empty($this->blacklist && $this->isBlacklisted($request))) {
            return false;
        }

        return true;
    }

    protected function isIgnoredExtension(Request $request)
    {
        foreach ($this->ignoredExtensions as $extension => $placeholder) {
            if (false !== stripos($request->getRequestUri(), $extension)) {
                return true;
            }
        }

        return false;
    }

    protected function isWhitelisted(Request $request)
    {
        foreach ($this->whitelist as $whitelisted => $token) {
            if ($this->urlContains($whitelisted, $request->getRequestUri())) {
                return true;
            }
        }

        return false;
    }

    protected function isBlacklisted(Request $request)
    {
        foreach ($this->blacklist as $blacklisted => $token) {
            if ($this->urlContains($blacklisted, $request->getRequestUri())) {
                return true;
            }

            if ($this->urlContains($blacklisted, $request->headers->get('Referer'))) {
                return true;
            }
        }

        return false;
    }

    protected function urlContains($pattern, $url)
    {
        $matches = preg_match("`{$pattern}`i", $url);

        if ($matches) {
            return true;
        }

        return false;
    }

    protected function isMappedAjaxCrawl(Request $request)
    {
        return null !== $request->query->get('_escaped_fragment_');
    }

    protected function isBot(Request $request)
    {
        $agent = strtolower($request->headers->get('User-Agent'));

        foreach ($this->botUserAgents as $userAgent => $placeholder) {
            if (false !== stripos($userAgent, $agent)) {
                return true;
            }
        }

        return false;
    }
} 