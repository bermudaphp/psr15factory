# MiddlewareFactory
Psr-15 middleware factory

## Installation

```bash
composer require lobster-php/middleware-factory
```

## Usage

```php
$factory = new MiddlewareFactory($containerInterface, $responseFactoryInterface);
```

## Classname 

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


$middleware = $factory->make(MyMiddleware::class);
$middleware instanceof MyMiddleware::class // true


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


$middleware = $factory->make(MyHandler::class);
$middleware instanceof MiddlewareInterface::class // true

```

## Callable Middleware

```php

$middleware = $factory->make(static function(ServerRequestInterface $req)
{
    return new TextResponse('Hello World!');
});

$middleware instanceof MiddlewareInterface::class // true

class MyCallback
{
    public function methoodName(ServerRequestInterface $req) : 
    {
        return new TextResponse('Hello World');
    }
}

$middleware = $factory->make('MyCallback@methoodName');
$middleware instanceof MiddlewareInterface::class // true
```

## Availables callback method  signature 

```php
function(ServerRequestInterface $req): ResponseInterface ;
function(ServerRequestInterface $req, RequestHandlerInterface $handler): ResponseInterface ;
function(ServerRequestInterface $req, ResponseInterface $resp, callable $next): ResponseInterface ;
function(ServerRequestInterface $req, callable $next): ResponseInterface ;
```

## MiddlewareFactoryStack 

```php
$stack = (new MiddlewareFactoryStack)->push($factory)->push(new MyMiddlewareFactoryInterfaceInplementation);
$middleware = $stack->make(static function(ServerRequestInterface $req, RequestHandlerInterface $next): ResponseInterface
{
    if()
    {
        return $next->handle($req);
    }
    
    return new TextResponse('Hello World!');
});
```



