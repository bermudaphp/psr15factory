## Installation

```bash
composer require bermudaphp/psr15factory
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

## Lazy Middleware

```php

$middleware = $factory->make(static function(ContainerInterface $c) use ($redirectPath, $permanent): MiddlewareInterface
{         
    return new RedirectMiddleware($redirectPath, $c->get(ResponseFactoryInterface::class), $permanent);
});

$middleware instanceof MiddlewareInterface::class // true
$middleware instanceof RedirectMiddleware::class // true

```

## Callable Middleware

```php

$middleware = $factory->make(static function(ServerRequestInterface $req): ResponseInterface
{
    return new TextResponse('Hello World!');
});

$middleware instanceof MiddlewareInterface::class // true

class MyCallback
{
    public function methoodName(ServerRequestInterface $req) : ResponseInterface
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
$stack = (new MiddlewareFactoryStack)->push($factory)->push(new MyMiddlewareFactoryInterfaceImplementation);
$middleware = $stack->make(static function(ServerRequestInterface $req, RequestHandlerInterface $next): ResponseInterface
{
    if()
    {
        return $next->handle($req);
    }
    
    return new TextResponse('Hello World!');
});
```



