<?php


namespace Lobster\MiddlewareFactory\Decorator;


use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;


/**
 * Class ServiceDecorator
 * @package Lobster\Resolver\Decorator
 */
final class SirviceDecorator implements MiddlewareInterface
{
    private string $service;
    private ContainerInterface $container;

    public function __construct(string $service, ContainerInterface $container)
    {
        $this->service = $service;
        $this->container = $container;
    }

    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $service = $this->container->get($this->service);

        if($service instanceof RequestHandlerInterface)
        {
            return $service->handle($request);
        }

        return $service->process($request, $handler);
    }
}
