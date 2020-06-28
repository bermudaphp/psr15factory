<?php


namespace Lobster\MiddlewareFactory;


use Psr\Http\Server\MiddlewareInterface;


/**
 * Class MiddlewareFactoryStack
 * @package Lobster\MiddlewareFactory
 */
final class MiddlewareFactoryStack implements MiddlewareFactoryInterface
{
    private array $factories = [];
    
    public function push(MiddlewareFactoryInterface $factory): self
    {
        return $this->factories[] = $factory;
    }

    /**
     * @inheritDoc
     */
    public function make($any): MiddlewareInterface
    {
        foreach($this->factories as $factory)
        {
            try
            {
                return $factory->make($any)
            }
            
            catch(MiddlewareFactoryException $e)
            {
                continue;
            }    
        }
        
        MiddlewareFactoryException::notCreatable($any)->throw();
    }
    
    /**
     * @inheritDoc
     */
    public function __invoke($any) : MiddlewareInterface 
    {
        return $this->make($any);
    }
}
