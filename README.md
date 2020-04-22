# Resolver
Psr-15 middleware resolver

## Installation

```bash
composer require lobster-php/resolver
```

## Usage

```php
$resolver = new Resolver($containerInterface, $responseFactoryInterface);
```

## Lazy load MiddlewareInterface

```php

class MyMiddleware implements MiddlewareInterface 
{
    private ResponseFactoryInterface $factory;
    
    public function __construct(ResponseFactoryInterface $factory)
    {
        $this->factory = $factory;
    }
    
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $this->factory->createResponse(200, 'OK!');
    }
}

$middlewareInstance = $resolver->resolve(MyMiddleware::class);
$middlewareInstance instanceof MyMiddleware::class // true
```

## Lazy load RequestHandlerInterface

```php

class MyHandler implements RequestHandlerInterface 
{
    private ResponseFactoryInterface $factory;
    
    public function __construct(ResponseFactoryInterface $factory)
    {
        $this->factory = $factory;
    }
    
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->factory->createResponse(200, 'OK!');
    }
}

$middlewareInstance = $resolver->resolve(MyHandler::class);
$middlewareInstance instanceof MiddlewareInterface::class // true
```

## Callable Middleware

```php

$middlewareInstance = $resolver->resolve(function(ServerRequestInterface $req)
{
    return new TextResponse('Hello World!');
});
$middlewareInstance instanceof MiddlewareInterface::class // true

or

$middlewareInstance = $resolver->resolve(function(ServerRequestInterface $req, RequestHandlerInterface $handler)
{
    return new TextResponse('Hello World!');
});
$middlewareInstance instanceof MiddlewareInterface::class // true
```
## Single Pass Middleware

```php

$middlewareInstance = $resolver->resolve(function(ServerRequestInterface $req, callable $next)
{
    if($cond)
    {
        return $next($request)
    }
    
    return new TextResponse('Hello World!');
});
$middlewareInstance instanceof MiddlewareInterface::class // true
```

## Double Pass Middleware

```php

$middlewareInstance = $resolver->resolve(function(ServerRequestInterface $req, ResponseInterface $resp, callable $next)
{
    if($cond)
    {
        return $next($request)
    }
    
    return $response;
});
$middlewareInstance instanceof MiddlewareInterface::class // true
```



