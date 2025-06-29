<?php

declare(strict_types=1);

namespace Bermuda\MiddlewareFactory\Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Bermuda\MiddlewareFactory\Adapter\RequestHandlerAdapter;

/**
 * Tests for RequestHandlerAdapter class
 */
class RequestHandlerAdapterTest extends TestCase
{
    private RequestHandlerInterface|MockObject $handler;
    private ServerRequestInterface|MockObject $request;
    private RequestHandlerInterface|MockObject $nextHandler;
    private ResponseInterface|MockObject $response;

    protected function setUp(): void
    {
        $this->handler = $this->createMock(RequestHandlerInterface::class);
        $this->request = $this->createMock(ServerRequestInterface::class);
        $this->nextHandler = $this->createMock(RequestHandlerInterface::class);
        $this->response = $this->createMock(ResponseInterface::class);
    }

    public function testImplementsMiddlewareInterface(): void
    {
        $adapter = new RequestHandlerAdapter($this->handler);
        $this->assertInstanceOf(MiddlewareInterface::class, $adapter);
    }

    public function testImplementsRequestHandlerInterface(): void
    {
        $adapter = new RequestHandlerAdapter($this->handler);
        $this->assertInstanceOf(RequestHandlerInterface::class, $adapter);
    }

    public function testProcessInjectsFallbackHandler(): void
    {
        $requestWithAttribute = $this->createMock(ServerRequestInterface::class);

        $this->request->expects($this->once())
            ->method('withAttribute')
            ->with(RequestHandlerAdapter::FALLBACK_HANDLER_ATTRIBUTES_KEY, $this->nextHandler)
            ->willReturn($requestWithAttribute);

        $this->handler->expects($this->once())
            ->method('handle')
            ->with($requestWithAttribute)
            ->willReturn($this->response);

        $adapter = new RequestHandlerAdapter($this->handler);
        $result = $adapter->process($this->request, $this->nextHandler);

        $this->assertSame($this->response, $result);
    }

    public function testHandleCallsWrappedHandlerDirectly(): void
    {
        $this->handler->expects($this->once())
            ->method('handle')
            ->with($this->request)
            ->willReturn($this->response);

        $adapter = new RequestHandlerAdapter($this->handler);
        $result = $adapter->handle($this->request);

        $this->assertSame($this->response, $result);
    }

    public function testFallbackHandlerAttributesKeyConstant(): void
    {
        $this->assertEquals(
            "Bermuda\MiddlewareFactory\Adapter:fallback",
            RequestHandlerAdapter::FALLBACK_HANDLER_ATTRIBUTES_KEY
        );
    }

    public function testProcessDoesNotModifyOriginalRequest(): void
    {
        $requestWithAttribute = $this->createMock(ServerRequestInterface::class);

        $this->request->expects($this->once())
            ->method('withAttribute')
            ->with(RequestHandlerAdapter::FALLBACK_HANDLER_ATTRIBUTES_KEY, $this->nextHandler)
            ->willReturn($requestWithAttribute);

        $this->handler->expects($this->once())
            ->method('handle')
            ->with($requestWithAttribute)
            ->willReturn($this->response);

        $adapter = new RequestHandlerAdapter($this->handler);
        $adapter->process($this->request, $this->nextHandler);

        // The original request should not be modified, only the copy with attribute
        // This is implicitly tested by the mock expectations above
        $this->assertTrue(true);
    }

    public function testHandleDoesNotInjectFallbackHandler(): void
    {
        // When handle() is called, no fallback handler should be injected
        $this->request->expects($this->never())
            ->method('withAttribute');

        $this->handler->expects($this->once())
            ->method('handle')
            ->with($this->request)
            ->willReturn($this->response);

        $adapter = new RequestHandlerAdapter($this->handler);
        $result = $adapter->handle($this->request);

        $this->assertSame($this->response, $result);
    }

    public function testProcessWithDifferentHandlers(): void
    {
        $handler1 = $this->createMock(RequestHandlerInterface::class);
        $handler2 = $this->createMock(RequestHandlerInterface::class);
        $response1 = $this->createMock(ResponseInterface::class);
        $response2 = $this->createMock(ResponseInterface::class);

        $requestWithAttribute1 = $this->createMock(ServerRequestInterface::class);
        $requestWithAttribute2 = $this->createMock(ServerRequestInterface::class);

        // Test first adapter
        $this->request->expects($this->exactly(2))
            ->method('withAttribute')
            ->willReturnCallback(function($key, $value) use ($handler1, $handler2, $requestWithAttribute1, $requestWithAttribute2) {
                if ($value === $handler1) {
                    return $requestWithAttribute1;
                } elseif ($value === $handler2) {
                    return $requestWithAttribute2;
                }
                throw new \Exception('Unexpected handler');
            });

        $this->handler->expects($this->exactly(2))
            ->method('handle')
            ->willReturnCallback(function($request) use ($requestWithAttribute1, $requestWithAttribute2, $response1, $response2) {
                if ($request === $requestWithAttribute1) {
                    return $response1;
                } elseif ($request === $requestWithAttribute2) {
                    return $response2;
                }
                throw new \Exception('Unexpected request');
            });

        $adapter = new RequestHandlerAdapter($this->handler);

        $result1 = $adapter->process($this->request, $handler1);
        $result2 = $adapter->process($this->request, $handler2);

        $this->assertSame($response1, $result1);
        $this->assertSame($response2, $result2);
    }

    public function testAdapterPassesThroughExceptions(): void
    {
        $exception = new \RuntimeException('Handler error');

        $requestWithAttribute = $this->createMock(ServerRequestInterface::class);

        $this->request->expects($this->once())
            ->method('withAttribute')
            ->willReturn($requestWithAttribute);

        $this->handler->expects($this->once())
            ->method('handle')
            ->with($requestWithAttribute)
            ->willThrowException($exception);

        $adapter = new RequestHandlerAdapter($this->handler);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Handler error');

        $adapter->process($this->request, $this->nextHandler);
    }
}