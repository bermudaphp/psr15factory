<?php

namespace Bermuda\MiddlewareFactory\Adapter;

use Bermuda\MiddlewareFactory\Resolver\RequestHandlerParameter;
use Bermuda\MiddlewareFactory\Resolver\RequestParameter;
use Bermuda\DI\CallableInvokerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Adapter that converts user-defined callables into PSR-15 compliant middleware.
 *
 * This adapter bridges the gap between various callable patterns and the PSR-15
 * middleware interface. It supports three main patterns:
 *
 * 1. Standard callable middleware - Any callable that can be invoked with parameters
 * 2. Single-pass middleware - Callables expecting (request, next) parameters
 * 3. Double-pass middleware - Callables expecting (request, response, next) parameters
 *
 * The adapter uses dependency injection to resolve callable parameters and handles
 * the normalization of callable return values to ResponseInterface instances.
 *
 * @internal This class is intended for internal use by the middleware factory
 */
class CallableAdapter implements MiddlewareInterface
{
    /**
     * The callable to be adapted.
     *
     * @var callable
     */
    protected $callable;

    /**
     * Creates a new callable adapter.
     *
     * @param callable $callable The callable to adapt to PSR-15 middleware
     * @param CallableInvokerInterface $invoker Service for invoking callables with dependency injection
     */
    public function __construct(
        callable $callable,
        protected readonly CallableInvokerInterface $invoker,
    ) {
        $this->callable = $callable;
    }

    /**
     * Processes a server request and produces a response.
     *
     * This method invokes the wrapped callable with appropriate parameters and
     * normalizes the result to ensure PSR-15 compliance:
     *
     * - MiddlewareInterface results are processed recursively
     * - RequestHandlerInterface results are handled directly
     * - ResponseInterface results are returned as-is
     * - Other return types trigger an exception
     *
     * @param ServerRequestInterface $request The server request to process
     * @param RequestHandlerInterface $handler The next request handler in the chain
     * @return ResponseInterface The HTTP response
     * @throws \Bermuda\DI\CallableInvokerExceptionInterface If callable invocation fails
     * @throws \UnexpectedValueException If callable doesn't produce a valid response
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $result = $this->invoker->call(
            $this->callable,
            $this->provideParams($request, $handler)
        );

        // Handle middleware result types
        if ($result instanceof MiddlewareInterface) {
            return $result->process($request, $handler);
        }

        if ($result instanceof RequestHandlerInterface) {
            return $result->handle($request);
        }

        // Ensure result is a valid HTTP response
        if (!$result instanceof ResponseInterface) {
            throw new \UnexpectedValueException(sprintf(
                'Callable middleware must return an instance of %s, %s, or %s. Got: %s',
                ResponseInterface::class,
                MiddlewareInterface::class,
                RequestHandlerInterface::class,
                get_debug_type($result)
            ));
        }

        return $result;
    }

    /**
     * Prepares parameters for callable invocation.
     *
     * This method creates a parameter array containing the request and handler
     * instances, formatted for use by the parameter resolution system.
     *
     * @param ServerRequestInterface $request The current server request
     * @param RequestHandlerInterface $handler The next request handler
     * @return array Parameter array for callable invocation
     */
    protected function provideParams(ServerRequestInterface $request, RequestHandlerInterface $handler): array
    {
        return RequestHandlerParameter::set(
            RequestParameter::set([], $request),
            $handler
        );
    }

    /**
     * Creates a single-pass middleware adapter.
     *
     * Single-pass middleware follows the pattern: function(request, next)
     * where 'next' is a callable that processes the request through the remaining middleware.
     *
     * @param callable $callable The single-pass middleware callable
     * @param CallableInvokerInterface $invoker Service for callable invocation
     * @return CallableAdapter Specialized adapter for single-pass middleware
     */
    public static function singlePassMiddleware(
        callable $callable,
        CallableInvokerInterface $invoker,
    ): CallableAdapter
    {
        return new class($callable, $invoker) extends CallableAdapter
        {
            /**
             * Provides parameters for single-pass middleware pattern.
             *
             * Creates parameter array with:
             * - ServerRequestInterface: The current request
             * - callable: Function that continues processing through remaining middleware
             *
             * @param ServerRequestInterface $request The current request
             * @param RequestHandlerInterface $handler The next handler in chain
             * @return array Parameters formatted for single-pass middleware
             */
            protected function provideParams(ServerRequestInterface $request, RequestHandlerInterface $handler): array
            {
                return RequestParameter::set([
                    $request,
                    static fn (ServerRequestInterface $req): ResponseInterface => $handler->handle($req)
                ], $request);
            }
        };
    }

    /**
     * Creates a double-pass middleware adapter.
     *
     * Double-pass middleware follows the pattern: function(request, response, next)
     * where 'response' is a base response object and 'next' continues processing.
     *
     * @param callable $callable The double-pass middleware callable
     * @param CallableInvokerInterface $invoker Service for callable invocation
     * @param ResponseFactoryInterface $factory Factory for creating base response objects
     * @return CallableAdapter Specialized adapter for double-pass middleware
     */
    public static function doublePassMiddleware(
        callable $callable,
        CallableInvokerInterface $invoker,
        ResponseFactoryInterface $factory
    ): CallableAdapter
    {
        return new class($callable, $invoker, $factory) extends CallableAdapter
        {
            /**
             * Creates a double-pass adapter with response factory.
             *
             * @param callable $callable The callable to adapt
             * @param CallableInvokerInterface $invoker Invocation service
             * @param ResponseFactoryInterface $responseFactory Factory for base responses
             */
            public function __construct(
                callable $callable,
                CallableInvokerInterface $invoker,
                private readonly ResponseFactoryInterface $responseFactory
            ) {
                parent::__construct($callable, $invoker);
            }

            /**
             * Provides parameters for double-pass middleware pattern.
             *
             * Creates parameter array with:
             * - ServerRequestInterface: The current request
             * - ResponseInterface: A base response from the factory
             * - callable: Function that continues processing through remaining middleware
             *
             * @param ServerRequestInterface $request The current request
             * @param RequestHandlerInterface $handler The next handler in chain
             * @return array Parameters formatted for double-pass middleware
             */
            protected function provideParams(ServerRequestInterface $request, RequestHandlerInterface $handler): array
            {
                return RequestParameter::set([
                    $request,
                    $this->responseFactory->createResponse(),
                    static fn (ServerRequestInterface $req): ResponseInterface => $handler->handle($req)
                ], $request);
            }
        };
    }
}