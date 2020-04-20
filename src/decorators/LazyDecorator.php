<?php


namespace Lobster\Resolver\Decorators;


use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;


/**
 * Class LazyDecorator
 * @package Lobster\Resolver\Decorators
 */
final class LazyDecorator implements MiddlewareInterface
{
    /**
     * @var string
     */
    private string $service;

    /**
     * @var ContainerInterface
     */
    private ContainerInterface $container;

    /**
     * LazyDecorator constructor.
     * @param string $service
     * @param ContainerInterface $container
     */
    public function __construct(string $service, ContainerInterface $container)
    {
        $this->service = $service;
        $this->container = $container;
    }

    /**
     * Process an incoming server request.
     *
     * Processes an incoming server request in order to produce a response.
     * If unable to produce the response itself, it may delegate to the provided
     * request handler to do so.
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {

        $middleware = $this->container->get($this->service);

        if($middleware instanceof RequestHandlerInterface){
            return $middleware->handle($request);
        }

        return $middleware->process($request, $handler);
    }
}
