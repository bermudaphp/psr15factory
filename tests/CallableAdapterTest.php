<?php

declare(strict_types=1);

namespace Bermuda\MiddlewareFactory\Tests;

use Bermuda\DI\CallableInvokerExceptionInterface;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Bermuda\DI\CallableInvokerInterface;
use Bermuda\MiddlewareFactory\Adapter\CallableAdapter;
use Bermuda\MiddlewareFactory\Resolver\RequestParameter;
use Bermuda\MiddlewareFactory\Resolver\RequestHandlerParameter;
use UnexpectedValueException;

/**
 * Tests for CallableAdapter class
 */
class CallableAdapterTest extends TestCase
{
    private CallableInvokerInterface|MockObject $invoker;
    private ServerRequestInterface|MockObject $request;
    private RequestHandlerInterface|MockObject $handler;
    private ResponseInterface|MockObject $response;

    protected function setUp(): void
    {
        $this->invoker = $this->createMock(CallableInvokerInterface::class);
        $this->request = $this->createMock(ServerRequestInterface::class);
        $this->handler = $this->createMock(RequestHandlerInterface::class);
        $this->response = $this->createMock(ResponseInterface::class);
    }

    public function testProcessWithResponseReturn(): void
    {
        $callable = fn() => $this->response;

        $this->invoker->expects($this->once())
            ->method('call')
            ->with($callable, $this->callback(function($params) {
                return isset($params[RequestParameter::KEY])
                    && $params[RequestParameter::KEY] instanceof ServerRequestInterface
                    && isset($params[RequestHandlerParameter::KEY])
                    && $params[RequestHandlerParameter::KEY] instanceof RequestHandlerInterface;
            }))
            ->willReturn($this->response);

        $adapter = new CallableAdapter($callable, $this->invoker);
        $result = $adapter->process($this->request, $this->handler);

        $this->assertSame($this->response, $result);
    }

    public function testProcessWithMiddlewareReturn(): void
    {
        $middleware = $this->createMock(MiddlewareInterface::class);
        $callable = fn() => $middleware;

        $middleware->expects($this->once())
            ->method('process')
            ->with($this->request, $this->handler)
            ->willReturn($this->response);

        $this->invoker->expects($this->once())
            ->method('call')
            ->with($callable, $this->callback(function ($params) {
                return isset($params[RequestParameter::KEY])
                    && $params[RequestParameter::KEY] instanceof ServerRequestInterface
                    && isset($params[RequestHandlerParameter::KEY])
                    && $params[RequestHandlerParameter::KEY] instanceof RequestHandlerInterface;
            }))
            ->willReturn($middleware);

        $adapter = new CallableAdapter($callable, $this->invoker);
        $result = $adapter->process($this->request, $this->handler);

        $this->assertSame($this->response, $result);
    }

    public function testProcessWithRequestHandlerReturn(): void
    {
        $requestHandler = $this->createMock(RequestHandlerInterface::class);
        $callable = fn() => $requestHandler;

        $requestHandler->expects($this->once())
            ->method('handle')
            ->with($this->request)
            ->willReturn($this->response);

        $this->invoker->expects($this->once())
            ->method('call')
            ->with($callable, $this->callback(function ($params) {
                return isset($params[RequestParameter::KEY])
                    && $params[RequestParameter::KEY] instanceof ServerRequestInterface
                    && isset($params[RequestHandlerParameter::KEY])
                    && $params[RequestHandlerParameter::KEY] instanceof RequestHandlerInterface;
            }))
            ->willReturn($requestHandler);

        $adapter = new CallableAdapter($callable, $this->invoker);
        $result = $adapter->process($this->request, $this->handler);

        $this->assertSame($this->response, $result);
    }

    public function testProcessWithInvalidReturn(): void
    {
        $callable = fn() => 'invalid';

        $this->invoker->expects($this->once())
            ->method('call')
            ->with($callable, $this->callback(function($params) {
                return isset($params[RequestParameter::KEY])
                    && $params[RequestParameter::KEY] instanceof ServerRequestInterface
                    && isset($params[RequestHandlerParameter::KEY])
                    && $params[RequestHandlerParameter::KEY] instanceof RequestHandlerInterface;
            }))
            ->willReturn('invalid');

        $adapter = new CallableAdapter($callable, $this->invoker);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Callable middleware must return an instance of');

        $adapter->process($this->request, $this->handler);
    }

    public function testSinglePassMiddleware(): void
    {
        $callable = function(ServerRequestInterface $request, callable $next) {
            return $next($request);
        };

        $this->handler->expects($this->once())
            ->method('handle')
            ->with($this->request)
            ->willReturn($this->response);

        $this->invoker->expects($this->once())
            ->method('call')
            ->with($callable, $this->callback(function($params) {
                return count($params) >= 2
                    && isset($params[0]) && $params[0] instanceof ServerRequestInterface
                    && isset($params[1]) && is_callable($params[1])
                    && isset($params[RequestParameter::KEY]) && $params[RequestParameter::KEY] instanceof ServerRequestInterface;
            }))
            ->willReturnCallback(function($originalCallable, $params) {
                return $originalCallable($params[0], $params[1]);
            });

        $adapter = CallableAdapter::singlePassMiddleware($callable, $this->invoker);
        $result = $adapter->process($this->request, $this->handler);

        $this->assertSame($this->response, $result);
    }

    public function testDoublePassMiddleware(): void
    {
        $callable = static function(ServerRequestInterface $request, ResponseInterface $response, callable $next) {
            return $response;
        };

        $responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $baseResponse = $this->createMock(ResponseInterface::class);

        $responseFactory->expects($this->once())
            ->method('createResponse')
            ->willReturn($baseResponse);

        $this->invoker->expects($this->once())
            ->method('call')
            ->with($callable, $this->callback(function($params) {
                return count($params) >= 3
                    && isset($params[0]) && $params[0] instanceof ServerRequestInterface
                    && isset($params[1]) && $params[1] instanceof ResponseInterface
                    && isset($params[2]) && is_callable($params[2])
                    && isset($params[RequestParameter::KEY]) && $params[RequestParameter::KEY] instanceof ServerRequestInterface;
            }))
            ->willReturnCallback(function($originalCallable, $params) {
                return $originalCallable($params[0], $params[1], $params[2]);
            });

        $adapter = CallableAdapter::doublePassMiddleware($callable, $this->invoker, $responseFactory);
        $result = $adapter->process($this->request, $this->handler);

        $this->assertSame($baseResponse, $result);
    }
}
