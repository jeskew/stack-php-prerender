<?php

use GuzzleHttp\Adapter\MockAdapter;
use GuzzleHttp\Adapter\TransactionInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Message\Response;
use Jeskew\StackPhPrerender\DefaultSettings;
use Jeskew\StackPhPrerender\Kernel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class KernelTest extends PHPUnit_Framework_TestCase
{
    /**
     * @test
     *
     * @dataProvider basicKernelProvider
     */
    public function kernel_returns_symfony_response(Kernel $app)
    {
        $request = $this->getMock('Symfony\Component\HttpFoundation\Request');

        $this->assertInstanceOf(
            'Symfony\Component\HttpFoundation\Response',
            $app->handle($request)
        );
    }

    /**
     * @test
     *
     * @dataProvider basicKernelProvider
     */
    public function kernel_knows_to_prerender_ajax_crawl_requests(Kernel $app)
    {
        $request = new Request();

        $request->query->set('_escaped_fragment_', '');

        $response = $app->handle($request);

        $this->assertInstanceOf(
            'Symfony\Component\HttpFoundation\Response',
            $response
        );

        $this->assertNotNull($response->headers->get('x-prerendered-on'));
    }

    /**
     * @test
     *
     * @dataProvider botRequestProvider
     */
    public function kernel_knows_to_prerender_bot_requests(Kernel $app, $botUserAgent)
    {
        $request = new Request();

        $request->headers->set('User-Agent', $botUserAgent);

        $response = $app->handle($request);

        $this->assertInstanceOf(
            'Symfony\Component\HttpFoundation\Response',
            $response
        );

        $this->assertNotNull($response->headers->get('x-prerendered-on'));
    }

    /**
     * @test
     *
     * @dataProvider appProvider
     */
    public function kernel_allows_custom_backend_urls(HttpKernelInterface $app)
    {
        $validUrl = 'http://www.google.com/';

        $kernel = new Kernel($app, ['backendUrl' => $validUrl]);

        $backendUrl = $this->inaccessiblePropertyInspector($kernel, 'backendUrl');

        $this->assertEquals($validUrl, $backendUrl);
    }

    /**
     * @test
     *
     * @dataProvider appProvider
     *
     * @expectedException InvalidArgumentException
     */
    public function kernel_disallows_malformed_backend_urls(HttpKernelInterface $app)
    {
        $validUrl = 'www.google.com';

        $kernel = new Kernel($app, ['backendUrl' => $validUrl]);

        $backendUrl = $this->inaccessiblePropertyInspector($kernel, 'backendUrl');

        $this->assertEquals($validUrl, $backendUrl);
    }

    /**
     * @test
     *
     * @dataProvider appProvider
     */
    public function kernel_allows_prerender_tokens(HttpKernelInterface $app)
    {
        $token = 'token_token';

        $kernel = new Kernel($app, ['prerenderToken' => $token]);

        $storedToken = $this->inaccessiblePropertyInspector($kernel, 'prerenderToken');

        $this->assertEquals($token, $storedToken);
    }

    /**
     * @test
     *
     * @dataProvider appProvider
     */
    public function kernel_allows_blacklisting_of_urls(HttpKernelInterface $app)
    {
        $pattern = '/admin/';

        $kernel = new Kernel($app, ['blacklist' => $pattern]);

        $blacklist = $this->inaccessiblePropertyInspector($kernel, 'blacklist');

        $this->assertArrayHasKey($pattern, $blacklist);
    }

    /**
     * @test
     *
     * @dataProvider appProvider
     */
    public function kernel_allows_whitelisting_of_urls(HttpKernelInterface $app)
    {
        $pattern = '/blog/';

        $kernel = new Kernel($app, ['whitelist' => $pattern]);

        $whitelist = $this->inaccessiblePropertyInspector($kernel, 'whitelist');

        $this->assertArrayHasKey($pattern, $whitelist);
    }

    protected function inaccessiblePropertyInspector($object, $property)
    {
        $reflectedProperty = new ReflectionProperty(get_class($object), $property);

        $reflectedProperty->setAccessible(true);

        return $reflectedProperty->getValue($object);
    }

    public function basicKernelProvider()
    {
        $app = $this->appFactory();

        return [[new Kernel($app, ['client' => $this->mockClientFactory()])]];
    }

    public function appProvider()
    {
        return [[$this->appFactory()]];
    }

    public function botRequestProvider()
    {
        $bots = DefaultSettings::$botUserAgents;

        $returnables = [];

        foreach ($bots as $bot) {
            $returnables []= [
                new Kernel($this->appFactory(), ['client' => $this->mockClientFactory()]),
                $bot,
            ];
        }

        return $returnables;
    }

    /**
     * @return Symfony\Component\HttpKernel\HttpKernelInterface
     */
    protected function appFactory()
    {
        $app = $this->getMock('Symfony\Component\HttpKernel\HttpKernelInterface');

        $app->expects($this->once())
            ->method('handle')
            ->will(
                $this->returnValue(
                    $this->getMock('Symfony\Component\HttpFoundation\Response')
                )
            );

        return $app;
    }

    protected function mockClientFactory(Response $response = null)
    {
        $mockAdapter = new MockAdapter(
            function (TransactionInterface $trans) use ($response) {
                return $response ?: new Response(200);
            }
        );

        return new Client(['adapter' => $mockAdapter]);
    }
}
 