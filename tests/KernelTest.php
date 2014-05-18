<?php

use Jeskew\StackPhPrerender\Kernel;
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
     * @dataProvider appProvider
     */
    public function kernel_allows_custom_backend_urls(HttpKernelInterface $app)
    {
        $validUrl = 'http://www.google.com';

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
        $app = $this->appProvider()[0][0];

        return [[new Kernel($app)]];
    }

    public function appProvider()
    {
        $app = $this->getMock('Symfony\Component\HttpKernel\HttpKernelInterface');

        $app->expects($this->once())
            ->method('handle')
            ->will(
                $this->returnValue(
                    $this->getMock('Symfony\Component\HttpFoundation\Response')
                )
            );

        return [[$app]];
    }
}
 