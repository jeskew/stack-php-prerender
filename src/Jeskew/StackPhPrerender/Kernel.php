<?php

namespace Jeskew\StackPhPrerender;


use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class Kernel implements HttpKernelInterface
{
    /**
     * @var \Symfony\Component\HttpKernel\HttpKernelInterface
     */
    protected $app;

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

    protected function parseOptions(array $options)
    {
        $this->addToBlacklist($this->getWithDefault($options, 'blacklist'));
        $this->addToWhitelist($this->getWithDefault($options, 'whitelist'));
        $this->setPrerenderToken($this->getWithDefault($options, 'prerenderToken'));

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

        $this->botUserAgents = array_flip($this->getWithDefault(
            $options,
            'botUserAgents',
            DefaultSettings::$botUserAgents
        ));
    }

    protected function getWithDefault(array $array, $key, $default = null)
    {
        return isset($array[$key]) ? $array[$key] : $default;
    }

    public function setBackendUrl($url)
    {
        $parsedUrl = parse_url($url);

        if ($parsedUrl && isset($parsedUrl['host'])) {
            $this->backendUrl = $url;

            return $this;
        }

        throw new InvalidArgumentException('Invalid backend url');
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

    public function prerenderForBots($bots)
    {
        $this->applyMethod('prerenderForBot', $bots);

        return $this;
    }

    public function addToBlacklist($paths)
    {
        $this->applyMethod('prerenderExtension', $paths);

        return $this;
    }

    public function dropFromBlacklist($paths)
    {
        $this->applyMethod('skipExtension', $paths);

        return $this;
    }

    public function addToWhitelist($paths)
    {
        $this->applyMethod('prerenderExtension', $paths);

        return $this;
    }

    public function dropFromWhitelist($paths)
    {
        $this->applyMethod('skipExtension', $paths);

        return $this;
    }

    protected function applyMethod($name, $args)
    {
        return $this->xArgs([$this, $name], $args);
    }

    protected function xArgs(callable $callable, $crossApplicable)
    {
        if (!is_array($crossApplicable)) {
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

    public function handle(Request $request, $type = self::MASTER_REQUEST, $check = true)
    {
        if ('GET' !== $request->getRealMethod()) {
            return $this->app->handle($request, $type, $check);
        }

        return $this->app->handle($request, $type, $check);
    }
} 