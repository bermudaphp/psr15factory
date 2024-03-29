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

$middleware = $factory->make(static function(ContainerInterface $c) use ($uri, $permanent): RedirectMiddleware
{         
    return new RedirectMiddleware($uri, $c->get(ResponseFactoryInterface::class), $permanent);
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
    public function methodName(ServerRequestInterface $req) : ResponseInterface
    {
        return new TextResponse('Hello World');
    }
}

$middleware = $factory->make('MyCallback@methodName');
$middleware instanceof MiddlewareInterface::class // true
```

## Invokable Middleware

```php

class Invokable
{
    public function __invoke(string $name) : ResponseInterface
    {
        return new TextResponse('Hello, '. $name);
    }
}

$factory->make('Invokable::class') instanceof MiddlewareInterface::class ; // true
```

## Request Args Middleware

```php

$middleware = $factory->make(static function(string $name): ResponseInterface
{
    return new TextResponse(sprintf('Hello, %s!', $name));
});

$response = $middleware->process((new ServerRequest())->withAttribute('name', 'John'), $requestHandler);
$response instanceof TextResponse // true
```

## Availables callback method  signature 

```php
function(): ResponseInterface ;
function(ContainerInterface $container): ResponseInterface ;
function(mixed ... $requestAttributeName): ResponseInterface ;
function(ServerRequestInterface $req): ResponseInterface ;
function(ServerRequestInterface $req, RequestHandlerInterface $handler): ResponseInterface ;
function(ServerRequestInterface $req, ResponseInterface $resp, callable $next): ResponseInterface ;
function(ServerRequestInterface $req, callable $next): ResponseInterface ;
```

## Aggregation MiddlewareFactory

```php

$myFactory = new class implements MiddlewareFactoryInterface
{
    /**
     * @param mixed $any
     * @return MiddlewareInterface
     * @throws UnresolvableMiddlewareException
     */
    public function make($any): MiddlewareInterface
    {
        if (is_string($any) && $any == 'redirect')
        {
            return new MyRedirectMiddleware ;
        }
        
        throw new UnresolvableMiddlewareException;
    }
    
    /**
     * Alias for self::make 
     */
    public function __invoke($any) : MiddlewareInterface
    {
        return $this->make($any);
    }
}

$factory = (new AggregateMiddlewareFactory)->addFactory($factory)
                ->addFactory($myFactory);

$middleware = $factory->make('redirect');
$middleware instanceof MiddlewareInterface // true 
```



