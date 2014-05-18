<?php

use Jeskew\StackPhPrerender\Kernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class KernelTest extends PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        Mockery::close();
    }

    /**
     * @test
     */
    public function kernel_returns_symfony_response()
    {

        $app = Mockery::mock('Symfony\Component\HttpKernel\HttpKernelInterface');

        $app->shouldReceive('handle')
            ->once()
            ->andReturn(
                Mockery::mock('Symfony\Component\HttpFoundation\Response')
            );

        $kernel = new Kernel($app);

        $this->markTestIncomplete();
        $request = $this->getMock('Symfony\Component\HttpFoundation\Request');

        $this->assertInstanceOf(
            'Symfony\Component\HttpFoundation\Response',
            $kernel->handle($request)
        );
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

        return [[new Kernel($app)]];
    }
}
 