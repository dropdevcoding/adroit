<?php

/**
 * This file is part of the Adroit package.
 *
 * (c) bitExpert AG
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace bitExpert\Adroit\Responder;

use bitExpert\Adroit\Domain\DomainPayload;
use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\Response;

/**
 * Unit test for {@link \bitExpert\Adroit\Responder\TwigResponder}.
 *
 * @covers \bitExpert\Adroit\Responder\TwigResponder
 */
class TwigResponderUnitTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \bitExpert\Adroit\Domain\DomainPayload
     */
    protected $domainPayload;
    /**
     * @var \Twig_Environment|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $twig;
    /**
     * @var TwigResponder
     */
    protected $responder;
    /**
     * @var ResponseInterface
     */
    protected $response;

    /**
     * @see PHPUnit_Framework_TestCase::setUp()
     */
    protected function setUp()
    {
        parent::setUp();

        $this->twig = $this->getMock('\Twig_Environment');
        $this->domainPayload = new DomainPayload('test');
        $this->responder = new TwigResponder($this->twig);
        $this->response = new Response();
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     */
    public function passingAnArrayAsTemplateWillThrowAnException()
    {
        $this->responder->setTemplate(array());
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     */
    public function passingAnIntAsTemplateWillThrowAnException()
    {
        $this->responder->setTemplate(2);
    }

    /**
     * @test
     * @expectedException \RuntimeException
     */
    public function callingBuildResponseWithoutAPresetTemplateWillThrowAnException()
    {
        $this->responder->buildResponse($this->domainPayload, $this->response);
    }

    /**
     * @test
     */
    public function callingBuildResponseWithAPresetTemplateWillReturnResponse()
    {
        $this->twig->expects($this->once())
            ->method('render')
            ->will($this->returnValue('<html>'));

        $this->responder->setTemplate('mytemplate.twig');
        $response = $this->responder->buildResponse($this->domainPayload, $this->response);
        $response->getBody()->rewind();

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(['text/html'], $response->getHeader('Content-Type'));
        $this->assertEquals('<html>', $response->getBody()->getContents());
    }

    /**
     * @test
     */
    public function additionalHttpHeadersGetAppendedToResponse()
    {
        $this->twig->expects($this->once())
            ->method('render')
            ->will($this->returnValue('<html>'));

        $this->responder->setTemplate('mytemplate.twig');
        $this->responder->setHeaders(['X-Sender' => 'PHPUnit Testcase']);
        $response = $this->responder->buildResponse($this->domainPayload, $this->response);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(['text/html'], $response->getHeader('Content-Type'));
        $this->assertTrue($response->hasHeader('X-Sender'));
    }

    /**
     * @test
     */
    public function contentTypeCantBeChanged()
    {
        $this->twig->expects($this->once())
            ->method('render')
            ->will($this->returnValue('<html>'));

        $this->responder->setTemplate('mytemplate.twig');
        $this->responder->setHeaders(['Content-Type' => 'my/type']);
        $response = $this->responder->buildResponse($this->domainPayload, $this->response);

        $this->assertEquals(['text/html'], $response->getHeader('Content-Type'));
    }
}