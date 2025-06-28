# Bermuda PSR-15 Factory

**Languages:** [English](README.md) | [Русский](README-RU.md)

A powerful and flexible factory for creating PSR-15 compatible middleware in PHP. Supports various types of middleware definitions, automatic dependency injection, request data mapping, and multiple middleware patterns.

## Requirements

- PHP 8.4+
- PSR-7 HTTP Message Interface
- PSR-11 Container Interface  
- PSR-15 HTTP Server Request Handlers

## Installation

```bash
composer require bermudaphp/psr15factory
```

## Quick Start

### Basic Setup

```php
use Bermuda\MiddlewareFactory\MiddlewareFactory;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Create factory
$factory = MiddlewareFactory::createFromContainer($container);

// Create middleware from various definitions
$middleware1 = $factory->makeMiddleware('App\\Middleware\\AuthMiddleware');
$middleware2 = $factory->makeMiddleware(function(ServerRequestInterface $request, callable $next): ResponseInterface {
    // Single-pass middleware
    return $next($request);
});
$middleware3 = $factory->makeMiddleware([$middlewareArray]);
```

### Controller Example

```php
use Bermuda\MiddlewareFactory\Attribute\MapQueryParameter;
use Bermuda\MiddlewareFactory\Attribute\MapRequestPayload;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class UserController
{
    public function getUsers(
        #[MapQueryParameter] int $page = 1,
        #[MapQueryParameter] int $limit = 10,
        #[MapQueryParameter('q')] string $search = ''
    ): ResponseInterface {
        // Automatically extracts ?page=2&limit=20&q=john
        // $page = 2, $limit = 20, $search = "john"
        
        return new JsonResponse(['users' => $this->userService->find($search, $page, $limit)]);
    }
    
    public function createUser(
        #[MapRequestPayload(['full_name' => 'name'])] CreateUserRequest $request
    ): ResponseInterface {
        // Automatically maps JSON payload to DTO with field renaming
        return new JsonResponse(['user' => $this->userService->create($request)]);
    }
}
```

## Middleware Types

### 1. Class Name Strategy

Resolves middleware by class name from the container:

```php
// Register in container
$container->set(AuthMiddleware::class, new AuthMiddleware());

// Usage
$middleware = $factory->makeMiddleware(AuthMiddleware::class);
```

### 2. Callable Strategy

Automatically detects and adapts various callable patterns. Supports multiple callable formats through built-in CallableResolver:

#### Supported callable formats:

**1. Closures**
```php
$middleware = $factory->makeMiddleware(function(ServerRequestInterface $request, callable $next): ResponseInterface {
    return $next($request);
});
```

**2. Standard PHP callables**
```php
$middleware = $factory->makeMiddleware([$object, 'methodName']);
$middleware = $factory->makeMiddleware('globalFunction');
```

**3. String representations:**

- **Static class methods**: `"Class::method"`
```php
$middleware = $factory->makeMiddleware('App\\Middleware\\AuthMiddleware::handle');
```

- **Container service methods**: `"serviceId::method"`  
```php
$middleware = $factory->makeMiddleware('auth.service::authenticate');
```

- **Global functions**: `"functionName"`
```php
$middleware = $factory->makeMiddleware('myGlobalMiddlewareFunction');
```

- **Callable services from container**: `"serviceId"`
```php
// Service that is itself callable
$middleware = $factory->makeMiddleware('custom.middleware.service');
```

**4. Arrays:**

- **Object and method**: `[object, method]`
```php
$authService = new AuthService();
$middleware = $factory->makeMiddleware([$authService, 'authenticate']);
```

- **Service ID and method**: `[serviceId, method]`
```php
$middleware = $factory->makeMiddleware(['user.service', 'validateToken']);
```

#### Middleware patterns (automatic detection):

##### Single-pass middleware
```php
$middleware = $factory->makeMiddleware(function(ServerRequestInterface $request, callable $next): ResponseInterface {
    // Pre-processing
    $request = $request->withAttribute('timestamp', time());
    
    // Call next middleware
    $response = $next($request);
    
    // Post-processing
    return $response->withHeader('X-Processing-Time', microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']);
});

// Or through service
$middleware = $factory->makeMiddleware('logging.middleware::process');
```

##### Double-pass middleware  
```php
$middleware = $factory->makeMiddleware(function(
    ServerRequestInterface $request, 
    ResponseInterface $response, 
    callable $next
): ResponseInterface {
    // Work with base response object
    if ($request->getHeaderLine('Accept') === 'application/json') {
        return $next($request);
    }
    
    return $response->withStatus(406);
});

// Or through class
$middleware = $factory->makeMiddleware('App\\Middleware\\LegacyMiddleware::handle');
```

##### Standard callable with DI
```php
$middleware = $factory->makeMiddleware(function(
    ServerRequestInterface $request,
    #[Inject('user.service')] UserService $userService,
    #[MapQueryParameter] string $token
): ResponseInterface {
    $user = $userService->findByToken($token);
    if (!$user) {
        return new JsonResponse(['error' => 'Invalid token'], 401);
    }
    
    return new JsonResponse(['user' => $user]);
});

// Or through service with DI
$middleware = $factory->makeMiddleware('api.middleware::handleAuth');
```

#### Factory Callable

For dynamic middleware creation using the container:

```php
// Factory callable - created only on first use
$middleware = $factory->makeMiddleware(static function(ContainerInterface $c) use ($uri, $permanent): RedirectMiddleware {         
    return new RedirectMiddleware($uri, $c->get(ResponseFactoryInterface::class), $permanent);
});

$middleware instanceof MiddlewareInterface; // true
$middleware instanceof RedirectMiddleware; // true

// Complex factory callable with configuration
$authMiddleware = $factory->makeMiddleware(static function(ContainerInterface $c): AuthMiddleware {
    $config = $c->get('config');
    $jwtSecret = $config['auth']['jwt_secret'];
    $tokenTtl = $config['auth']['token_ttl'] ?? 3600;
    
    return new AuthMiddleware(
        $c->get(JwtService::class),
        $c->get(UserRepository::class),
        $jwtSecret,
        $tokenTtl
    );
});

// Conditional factory callable
$compressionMiddleware = $factory->makeMiddleware(static function(ContainerInterface $c): MiddlewareInterface {
    $config = $c->get('config');
    
    if ($config['compression']['enabled'] ?? false) {
        return new CompressionMiddleware(
            $config['compression']['level'] ?? 6,
            $config['compression']['types'] ?? ['text/html', 'application/json']
        );
    }
    
    // Return empty middleware if compression is disabled
    return new class implements MiddlewareInterface {
        public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
            return $handler->handle($request);
        }
    };
});
```

#### Examples with different formats:

```php
// Container with various middleware services
$container->set('auth.middleware', new AuthenticationMiddleware());
$container->set('auth.service', new AuthService());
$container->set('rate.limiter', new RateLimitMiddleware());

// Various ways to create middleware:

// 1. Direct closure
$middleware1 = $factory->makeMiddleware(function($request, $next) {
    return $next($request);
});

// 2. Service-middleware from container
$middleware2 = $factory->makeMiddleware('auth.middleware');

// 3. Service method from container
$middleware3 = $factory->makeMiddleware('auth.service::validateRequest');

// 4. Static class method
$middleware4 = $factory->makeMiddleware('App\\Utils\\SecurityUtils::checkOrigin');

// 5. Array with service and method
$middleware5 = $factory->makeMiddleware(['rate.limiter', 'handle']);

// 6. Global function
$middleware6 = $factory->makeMiddleware('customSecurityHandler');

// 7. Direct object and method
$corsHandler = new CorsHandler();
$middleware7 = $factory->makeMiddleware([$corsHandler, 'process']);
```

### 3. Pipeline Strategy and MiddlewareGroup

For combining multiple middleware into a single pipeline:

```php
use Bermuda\MiddlewareFactory\MiddlewareGroup;

$group = new MiddlewareGroup([
    'App\\Middleware\\AuthMiddleware',
    ['cors.service', 'handle'],
    function($request, $next) { return $next($request); }
]);

$middleware = $factory->makeMiddleware($group);

// Adding middleware
$newGroup = $group->add('rate.limiter::check');
$newGroup = $group->addMany(['cache.middleware', 'response.formatter']);

// Checks
echo $group->count(); // Number of middleware
foreach ($group as $definition) {
    echo get_debug_type($definition) . "\n";
}
```

## Request Data Mapping

The factory supports automatic extraction and transformation of data from PSR-7 requests into method parameters using PHP 8+ attributes. This provides clean separation between HTTP data handling and business logic.

### Query parameters

The `#[MapQueryParameter]` attribute extracts individual parameters from the query string:

```php
use Bermuda\MiddlewareFactory\Attribute\MapQueryParameter;

public function search(
    #[MapQueryParameter] string $query,           // ?query=something
    #[MapQueryParameter('p')] int $page = 1,      // ?p=2 (extracts parameter 'p')
    #[MapQueryParameter] ?string $category = null // ?category=books (optional)
): ResponseInterface {
    // $query = "something", $page = 2, $category = "books"
    
    $results = $this->searchService->search($query, $page, $category);
    return new JsonResponse(['results' => $results]);
}

// Advanced usage with typing
public function filter(
    #[MapQueryParameter] int $page = 1,              // Automatic cast to int
    #[MapQueryParameter] bool $active = true,        // String "true"/"false" → bool
    #[MapQueryParameter] array $tags = [],           // Multiple values ?tags[]=php&tags[]=api
    #[MapQueryParameter('min_price')] ?float $minPrice = null  // Extract 'min_price' as float
): ResponseInterface {
    // All parameters automatically cast to appropriate types
    
    $filters = compact('page', 'active', 'tags', 'minPrice');
    $products = $this->productService->filter($filters);
    
    return new JsonResponse(['products' => $products]);
}
```

### Query string (complete parameter set)

The `#[MapQueryString]` attribute extracts all query parameters with optional field renaming:

```php
use Bermuda\MiddlewareFactory\Attribute\MapQueryString;

public function advancedSearch(
    #[MapQueryString] array $filters
): ResponseInterface {
    // ?name=John&age=25&city=NYC&sort=name&dir=asc
    // $filters = ['name' => 'John', 'age' => '25', 'city' => 'NYC', 'sort' => 'name', 'dir' => 'asc']
    
    return $this->processFilters($filters);
}

// With field renaming
public function listProducts(
    #[MapQueryString(['sort' => 'sortBy', 'dir' => 'direction', 'q' => 'search'])]
    array $queryParams
): ResponseInterface {
    // ?sort=price&dir=asc&q=laptop&limit=10&offset=20
    // $queryParams = [
    //     'sortBy' => 'price',      // field 'sort' renamed to 'sortBy'
    //     'direction' => 'asc',     // field 'dir' renamed to 'direction'
    //     'search' => 'laptop',     // field 'q' renamed to 'search'
    //     'limit' => '10',          // kept as is
    //     'offset' => '20'          // kept as is
    // ]
    
    $products = $this->productService->search($queryParams);
    return new JsonResponse(['products' => $products]);
}

// Usage with DTO
public function searchWithDTO(
    #[MapQueryString(['q' => 'query', 'cat' => 'category'])]
    SearchCriteria $criteria
): ResponseInterface {
    // Query parameters automatically mapped to SearchCriteria object
    // 'q' → 'query', 'cat' → 'category' during object creation
    
    $results = $this->searchService->search($criteria);
    return new JsonResponse($results);
}
```

### Request payload (request body)

The `#[MapRequestPayload]` attribute extracts data from request body (JSON, form data, XML):

```php
use Bermuda\MiddlewareFactory\Attribute\MapRequestPayload;

public function createUser(
    #[MapRequestPayload] array $userData
): ResponseInterface {
    // POST /users
    // Content-Type: application/json
    // {"name": "John", "email": "john@example.com", "age": 25}
    //
    // $userData = ['name' => 'John', 'email' => 'john@example.com', 'age' => 25]
    
    $user = $this->userService->create($userData);
    return new JsonResponse(['user' => $user], 201);
}

// With field renaming API → internal names
public function updateProfile(
    #[MapRequestPayload(['full_name' => 'name', 'phone_number' => 'phone'])]
    array $profileData
): ResponseInterface {
    // POST /profile
    // {"full_name": "John Doe", "phone_number": "+1234567890", "bio": "Developer"}
    //
    // $profileData = [
    //     'name' => 'John Doe',        // field 'full_name' renamed to 'name'
    //     'phone' => '+1234567890',    // field 'phone_number' renamed to 'phone'
    //     'bio' => 'Developer'         // kept as is
    // ]
    
    return $this->profileService->update($profileData);
}

// Direct mapping to DTO
public function createProduct(
    #[MapRequestPayload] CreateProductRequest $request,
    #[RequestAttribute] User $currentUser
): ResponseInterface {
    // JSON payload automatically converted to CreateProductRequest
    // through MiddlewareFactory using DI container
    
    $product = $this->productService->create($request, $currentUser);
    return new JsonResponse(['product' => $product], 201);
}

// Complex mapping with validation
public function processOrder(
    #[MapRequestPayload([
        'customer_info' => 'customer',
        'payment_method' => 'payment',
        'shipping_address' => 'shipping'
    ])]
    ProcessOrderRequest $orderRequest
): ResponseInterface {
    // Complex nested data automatically mapped to structured DTO
    
    $order = $this->orderService->process($orderRequest);
    return new JsonResponse(['order' => $order], 201);
}
```

### Request attributes

The `#[RequestAttribute]` attribute extracts data set by previous middleware:

```php
use Bermuda\MiddlewareFactory\Attribute\RequestAttribute;

public function getProfile(
    #[RequestAttribute] User $currentUser,              // $request->getAttribute('currentUser')
    #[RequestAttribute('route.id')] int $userId         // $request->getAttribute('route.id')
): ResponseInterface {
    // Attributes usually set by previous middleware
    // e.g., authentication or routing
    
    if ($currentUser->getId() !== $userId && !$currentUser->isAdmin()) {
        return new JsonResponse(['error' => 'Access denied'], 403);
    }
    
    $profile = $this->userService->getProfile($userId);
    return new JsonResponse(['profile' => $profile]);
}

// Working with optional attributes
public function processWithContext(
    #[RequestAttribute('request.id')] string $requestId,
    #[RequestAttribute('trace.id')] ?string $traceId = null,
    #[RequestAttribute('feature.flags')] array $featureFlags = []
): ResponseInterface {
    $context = [
        'request_id' => $requestId,
        'trace_id' => $traceId,
        'features' => $featureFlags
    ];
    
    return $this->processWithContext($context);
}
```

### Combining different data sources

```php
public function complexOperation(
    #[MapQueryParameter] int $page = 1,
    #[MapQueryParameter] int $limit = 10,
    #[MapRequestPayload(['filter_data' => 'filters'])] array $filters,
    #[RequestAttribute] User $currentUser,
    #[RequestAttribute('route.resource')] string $resource,
    #[Config('app.max_results')] int $maxResults,
    #[Inject('search.service')] SearchService $searchService
): ResponseInterface {
    // Data from different sources automatically combined
    // Query: ?page=2&limit=20
    // Body: {"filter_data": {"category": "electronics", "price_min": 100}}
    // Attributes: currentUser, route.resource
    // Config: app.max_results
    // DI: SearchService
    
    $limit = min($limit, $maxResults); // Limit by configuration
    
    $searchParams = [
        'page' => $page,
        'limit' => $limit,
        'filters' => $filters,
        'user_id' => $currentUser->getId(),
        'resource' => $resource
    ];
    
    $results = $searchService->search($searchParams);
    
    return new JsonResponse([
        'results' => $results,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $results->getTotal()
        ]
    ]);
}
```

### Mapping error handling

```php
// Middleware automatically handles mapping errors
public function handleErrors(
    #[MapQueryParameter] int $requiredParam,        // Required parameter
    #[MapRequestPayload] ValidatedRequest $request  // DTO with validation
): ResponseInterface {
    // If requiredParam is missing from query string:
    // → OutOfBoundsException → ParameterResolutionException
    //
    // If request body cannot be converted to ValidatedRequest:
    // → corresponding exception from MiddlewareFactory

    return new JsonResponse(['success' => true]);
}

// Custom error handling
$errorHandlingMiddleware = $factory->makeMiddleware(function(
    ServerRequestInterface $request,
    callable $next
): ResponseInterface {
    try {
        return $next($request);
    } catch (ParameterResolutionException $e) {
        return new JsonResponse([
            'error' => 'Invalid request parameters',
            'details' => $e->getMessage(),
            'parameter' => $e->parameter->getName()
        ], 400);
    } catch (MiddlewareResolutionException $e) {
        return new JsonResponse([
            'error' => 'Request processing failed',
            'details' => $e->getMessage()
        ], 500);
    }
});
```

### Configuration and DI container

```php
use Bermuda\DI\Attribute\Config;
use Bermuda\DI\Attribute\Inject;

public function processPayment(
    #[Config('payment.api_key')] string $apiKey,                    // From configuration
    #[Config('payment.timeout', 30)] int $timeout,                  // With default value
    #[Inject('payment.gateway')] PaymentGateway $gateway,           // Service by name
    #[Inject('logger')] LoggerInterface $logger,                    // Logger from container
    PaymentService $paymentService,                                 // Automatically by type
    #[MapRequestPayload] PaymentRequest $request                    // Data from request
): ResponseInterface {
    $logger->info("Processing payment with timeout: {$timeout}s");
    
    $result = $gateway->processPayment($request, [
        'api_key' => $apiKey,
        'timeout' => $timeout
    ]);
    
    return new JsonResponse(['result' => $result]);
}
```

### Complex example with configuration

```php
public function handleUpload(
    #[Config('upload.max_size')] int $maxSize,                      // Maximum file size
    #[Config('upload.allowed_types', ['jpg', 'png'])] array $types, // Allowed types
    #[Config('storage.path')] string $storagePath,                  // Storage path
    #[Inject('file.validator')] FileValidator $validator,           // File validator
    #[Inject('storage.manager')] StorageManager $storage,           // Storage manager
    #[MapRequestPayload] UploadRequest $uploadData,                 // Upload data
    #[RequestAttribute] User $currentUser                           // Current user
): ResponseInterface {
    // File size validation
    if ($uploadData->getFileSize() > $maxSize) {
        return new JsonResponse(['error' => 'File too large'], 413);
    }
    
    // File type validation  
    if (!in_array($uploadData->getFileType(), $types)) {
        return new JsonResponse(['error' => 'Invalid file type'], 415);
    }
    
    // Additional validation
    if (!$validator->validate($uploadData)) {
        return new JsonResponse(['error' => 'File validation failed'], 422);
    }
    
    // Save file
    $savedFile = $storage->store($uploadData, $storagePath, $currentUser);
    
    return new JsonResponse(['file' => $savedFile], 201);
}
```

## Advanced Usage

### Creating custom strategy

```php
use Bermuda\MiddlewareFactory\Strategy\StrategyInterface;
use Psr\Http\Server\MiddlewareInterface;

class CustomStrategy implements StrategyInterface
{
    public function makeMiddleware(mixed $middleware): ?MiddlewareInterface
    {
        if ($middleware instanceof MyCustomType) {
            return new MyCustomAdapter($middleware);
        }
        
        return null; // Cannot handle this type
    }
}

// Register custom strategy
$factory->addStrategy(new CustomStrategy(), true); // true = add to beginning
```

### Configuration through container

```php
use Bermuda\MiddlewareFactory\ConfigProvider;

// In container configuration
return [
    ConfigProvider::CONFIG_KEY_STRATEGIES => [
        CustomStrategy::class,
        AnotherStrategy::class
    ]
];
```

### Error handling

```php
use Bermuda\MiddlewareFactory\MiddlewareResolutionException;

try {
    $middleware = $factory->makeMiddleware($invalidDefinition);
} catch (MiddlewareResolutionException $e) {
    echo "Failed to create middleware: " . $e->getMessage();
    echo "Middleware type: " . get_debug_type($e->middleware);
    
    // Get information about nested error
    if ($e->getPrevious()) {
        echo "Cause: " . $e->getPrevious()->getMessage();
    }
}
```

## Architecture

### Main components

- **MiddlewareFactory** - Main factory coordinating strategies
- **Strategy** - Interface for middleware resolution strategies
- **Adapter** - Adapters for converting types to PSR-15
- **Resolver** - Resolvers for dependency injection
- **Attribute** - PHP 8+ attributes for declarative mapping

### Execution flow

1. **Middleware resolution** - Factory tries strategies in order
2. **Adaptation** - Converting to PSR-15 MiddlewareInterface
3. **Dependency injection** - Resolving parameters through container  
4. **Data mapping** - Extracting data from request by attributes
5. **Execution** - Calling middleware with prepared parameters

## Real-world examples

### REST API controller

```php
use Bermuda\DI\Attribute\Config;
use Bermuda\DI\Attribute\Inject;

class ProductController
{
    public function list(
        #[MapQueryParameter] int $page = 1,
        #[MapQueryParameter('per_page')] int $perPage = 10,
        #[MapQueryString(['sort' => 'sortBy', 'filter' => 'filters'])] array $query = [],
        #[Config('products.default_limit', 50)] int $maxLimit,        // Maximum limit from config
        #[Inject('product.search')] ProductSearchService $search      // Search service
    ): ResponseInterface {
        // Limit restriction
        $perPage = min($perPage, $maxLimit);
        
        $products = $search->paginate($page, $perPage, $query);
        return new JsonResponse(['data' => $products]);
    }
    
    public function create(
        #[MapRequestPayload] CreateProductRequest $request,
        #[RequestAttribute] User $currentUser,
        #[Config('products.auto_publish')] bool $autoPublish,         // Auto-publish
        #[Inject('product.factory')] ProductFactory $factory,         // Product factory
        #[Inject('event.dispatcher')] EventDispatcher $events         // Event dispatcher
    ): ResponseInterface {
        $product = $factory->create($request, $currentUser);
        
        if ($autoPublish) {
            $product->publish();
        }
        
        $this->productService->save($product);
        
        // Dispatch event
        $events->dispatch(new ProductCreated($product));
        
        return new JsonResponse(['product' => $product], 201);
    }
    
    public function update(
        #[RequestAttribute('route.id')] int $id,
        #[MapRequestPayload] UpdateProductRequest $request,
        #[Config('products.versioning.enabled')] bool $versioningEnabled,  // Versioning
        #[Inject('product.versioning')] ?VersioningService $versioning = null
    ): ResponseInterface {
        $product = $this->productService->findById($id);
        
        if ($versioningEnabled && $versioning) {
            $versioning->createSnapshot($product);
        }
        
        $product = $this->productService->update($product, $request);
        
        return new JsonResponse(['product' => $product]);
    }
    
    public function uploadImage(
        #[RequestAttribute('route.id')] int $productId,
        #[MapRequestPayload] UploadImageRequest $uploadRequest,
        #[Config('upload.images.max_size')] int $maxSize,              // Maximum size
        #[Config('upload.images.quality', 85)] int $quality,           // Compression quality
        #[Config('cdn.base_url')] string $cdnUrl,                      // CDN URL
        #[Inject('image.processor')] ImageProcessor $processor,        // Image processor
        #[Inject('cdn.uploader')] CdnUploader $uploader               // CDN uploader
    ): ResponseInterface {
        $product = $this->productService->findById($productId);
        
        // Process image
        $processedImage = $processor->process($uploadRequest->getFile(), [
            'max_size' => $maxSize,
            'quality' => $quality
        ]);
        
        // Upload to CDN
        $cdnPath = $uploader->upload($processedImage, "products/{$productId}");
        $imageUrl = $cdnUrl . '/' . $cdnPath;
        
        // Save URL to product
        $product->addImage($imageUrl);
        $this->productService->save($product);
        
        return new JsonResponse(['image_url' => $imageUrl]);
    }
}
```

### Middleware with dependency injection

```php
use Bermuda\DI\Attribute\Config;
use Bermuda\DI\Attribute\Inject;

$authMiddleware = $factory->makeMiddleware(function(
    ServerRequestInterface $request,
    #[Inject('auth.service')] AuthService $auth,
    #[Config('auth.token_header', 'Authorization')] string $tokenHeader,
    #[Config('auth.token_prefix', 'Bearer ')] string $tokenPrefix,
    #[MapQueryParameter] ?string $token = null
): ResponseInterface|ServerRequestInterface {
    // Extract token from various sources
    $token = $token 
        ?? str_replace($tokenPrefix, '', $request->getHeaderLine($tokenHeader))
        ?? $request->getCookieParams()['auth_token'] ?? null;
    
    if (!$token || !$auth->validateToken($token)) {
        return new JsonResponse(['error' => 'Unauthorized'], 401);
    }
    
    $user = $auth->getUserByToken($token);
    return $request->withAttribute('currentUser', $user);
});

$rateLimitMiddleware = $factory->makeMiddleware(function(
    ServerRequestInterface $request,
    #[Config('rate_limit.requests_per_minute')] int $maxRequests,
    #[Config('rate_limit.window_seconds', 60)] int $windowSeconds,
    #[Inject('cache.redis')] RedisInterface $redis,
    #[Inject('rate_limiter')] RateLimiter $limiter,
    #[RequestAttribute] ?User $user = null
): ?ResponseInterface {
    $clientId = $user?->getId() ?? $request->getClientIp();
    $key = "rate_limit:{$clientId}";
    
    if ($limiter->isExceeded($key, $maxRequests, $windowSeconds)) {
        return new JsonResponse([
            'error' => 'Rate limit exceeded',
            'retry_after' => $windowSeconds
        ], 429);
    }
    
    return null; // Continue execution
});

$loggingMiddleware = $factory->makeMiddleware(function(
    ServerRequestInterface $request,
    #[Config('logging.enabled')] bool $loggingEnabled,
    #[Config('logging.level', 'info')] string $logLevel,
    #[Config('logging.include_headers', false)] bool $includeHeaders,
    #[Inject('logger')] LoggerInterface $logger,
    callable $next
): ResponseInterface {
    $startTime = microtime(true);
    
    if ($loggingEnabled) {
        $context = [
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri(),
            'user_agent' => $request->getHeaderLine('User-Agent')
        ];
        
        if ($includeHeaders) {
            $context['headers'] = $request->getHeaders();
        }
        
        $logger->log($logLevel, 'Request started', $context);
    }
    
    $response = $next($request);
    
    if ($loggingEnabled) {
        $duration = (microtime(true) - $startTime) * 1000;
        $logger->log($logLevel, 'Request completed', [
            'status' => $response->getStatusCode(),
            'duration_ms' => round($duration, 2)
        ]);
    }
    
    return $response;
});
```

### Complex data processing

```php
use Bermuda\DI\Attribute\Config;
use Bermuda\DI\Attribute\Inject;

$processingMiddleware = $factory->makeMiddleware(function(
    #[MapRequestPayload] array $data,
    #[Config('processing.strict_mode')] bool $strictMode,
    #[Config('processing.rules')] array $globalRules,
    #[Config('processing.max_errors', 10)] int $maxErrors,
    #[Inject('data.processor')] DataProcessorInterface $processor,
    #[Inject('rule.factory')] RuleFactory $ruleFactory,
    #[RequestAttribute('route.action')] string $action,
    #[RequestAttribute] ?User $user = null
): ?ResponseInterface {
    // Get processing rules for specific action
    $actionRules = $globalRules[$action] ?? [];
    $rules = $ruleFactory->buildRules($actionRules, $user, $strictMode);
    
    // Execute processing
    $result = $processor->process($data, $rules, ['max_errors' => $maxErrors]);
    
    if (!$result->isValid()) {
        $errors = $result->getErrors();
        
        // In strict mode return all errors
        if ($strictMode) {
            return new JsonResponse([
                'error' => 'Processing failed',
                'errors' => $errors,
                'strict_mode' => true
            ], 422);
        }
        
        // In normal mode return first N errors
        return new JsonResponse([
            'error' => 'Processing failed', 
            'errors' => array_slice($errors, 0, $maxErrors)
        ], 422);
    }
    
    return null; // Continue execution
});

$cachingMiddleware = $factory->makeMiddleware(function(
    ServerRequestInterface $request,
    #[Config('cache.enabled')] bool $cacheEnabled,
    #[Config('cache.ttl', 3600)] int $cacheTtl,
    #[Config('cache.key_prefix', 'api')] string $keyPrefix,
    #[Inject('cache.redis')] CacheInterface $cache,
    callable $next
): ResponseInterface {
    if (!$cacheEnabled) {
        return $next($request);
    }
    
    // Generate cache key
    $cacheKey = $keyPrefix . ':' . md5($request->getUri() . serialize($request->getQueryParams()));
    
    // Try to get from cache
    $cachedResponse = $cache->get($cacheKey);
    if ($cachedResponse !== null) {
        return new JsonResponse($cachedResponse)->withHeader('X-Cache', 'HIT');
    }
    
    // Execute request
    $response = $next($request);
    
    // Cache successful responses
    if ($response->getStatusCode() === 200) {
        $responseData = json_decode($response->getBody()->getContents(), true);
        $cache->set($cacheKey, $responseData, $cacheTtl);
    }
    
    return $response->withHeader('X-Cache', 'MISS');
});
```

## Best Practices

### 1. Strategy order
Register more specific strategies first:

```php
$factory->addStrategy(new CustomStrategy(), true);     // First
$factory->addStrategy(new ClassNameStrategy());       // Default
$factory->addStrategy(new CallableStrategy());        // Default  
```

### 2. Error handling
Always wrap middleware creation in try-catch:

```php
try {
    $middleware = $factory->makeMiddleware($definition);
} catch (MiddlewareResolutionException $e) {
    // Logging and error handling
    $logger->error('Middleware resolution failed', [
        'middleware' => $e->middleware,
        'message' => $e->getMessage()
    ]);
    throw $e;
}
```

### 3. Parameter typing
Use strict typing for automatic type conversion:

```php
public function handle(
    #[MapQueryParameter] int $page,        // Automatic cast to int
    #[MapQueryParameter] bool $active,     // Automatic cast to bool
    #[MapRequestPayload] CreateUserRequest $request // Strict DTO typing
): ResponseInterface {
    // Guaranteed correct types
}
```

### 4. Documenting attributes
Document used attributes for clarity:

```php
/**
 * Creates a new user
 * 
 * @param CreateUserRequest $request User data from request body
 * @param User $currentUser Current user from auth middleware  
 * @param string $role Role from query parameter 'role'
 * @param int $maxUsers Maximum users from configuration
 * @param UserFactory $factory User factory from DI container
 */
public function createUser(
    #[MapRequestPayload] CreateUserRequest $request,
    #[RequestAttribute] User $currentUser,
    #[MapQueryParameter] string $role = 'user',
    #[Config('users.max_count', 1000)] int $maxUsers,
    #[Inject('user.factory')] UserFactory $factory
): ResponseInterface {
    // ...
}
```

### 5. Configuration usage
Group related settings and use default values:

```php
public function processImage(
    #[Config('image.processing.max_width', 1920)] int $maxWidth,
    #[Config('image.processing.max_height', 1080)] int $maxHeight,
    #[Config('image.processing.quality', 85)] int $quality,
    #[Config('image.processing.format', 'webp')] string $format,
    #[Inject('image.processor')] ImageProcessor $processor
): ResponseInterface {
    // Settings with reasonable defaults
}
```

### 6. Service injection
Use meaningful service names in the container:

```php
// Good - clear service names
#[Inject('payment.stripe.gateway')] StripeGateway $stripe,
#[Inject('payment.paypal.gateway')] PayPalGateway $paypal,
#[Inject('notification.email')] EmailService $emailService,
#[Inject('notification.sms')] SmsService $smsService

// Bad - unclear names
#[Inject('service1')] SomeService $service,
#[Inject('gateway')] Gateway $gateway
```

## Performance

The factory is optimized for performance:

- **Lazy creation** - Middleware created only when needed
- **Strategy caching** - Strategies registered once
- **Minimal reflection** - Reflection used only for signature analysis
- **Efficient adaptation** - Minimal overhead when adapting types

## Testing

```php
use PHPUnit\Framework\TestCase;
use Bermuda\DI\Attribute\Config;
use Bermuda\DI\Attribute\Inject;
use Bermuda\MiddlewareFactory\MiddlewareGroup;
use Bermuda\MiddlewareFactory\Strategy\MiddlewarePipelineStrategy;

class MiddlewareFactoryTest extends TestCase
{
    public function testCreateMiddlewareFromCallable(): void
    {
        $factory = MiddlewareFactory::createFromContainer($this->container);
        
        $callable = function(ServerRequestInterface $request, callable $next): ResponseInterface {
            return $next($request);
        };
        
        $middleware = $factory->makeMiddleware($callable);
        
        $this->assertInstanceOf(MiddlewareInterface::class, $middleware);
    }
    
    public function testParameterMapping(): void
    {
        $middleware = $this->factory->makeMiddleware(function(
            #[MapQueryParameter] string $name
        ): ResponseInterface {
            return new JsonResponse(['name' => $name]);
        });
        
        $request = $this->createRequest('GET', '/?name=John');
        $response = $middleware->process($request, $this->handler);
        
        $this->assertEquals(['name' => 'John'], json_decode($response->getBody(), true));
    }
    
    public function testConfigAndInjectAttributes(): void
    {
        // Setup container for testing
        $this->container->set('config', new ArrayObject([
            'app' => ['name' => 'Test App', 'debug' => true]
        ]));
        $this->container->set('test.service', new TestService());
        
        $middleware = $this->factory->makeMiddleware(function(
            #[Config('app.name')] string $appName,
            #[Config('app.debug')] bool $debug,
            #[Config('app.timeout', 30)] int $timeout,
            #[Inject('test.service')] TestService $service,
            #[MapQueryParameter] string $action
        ): ResponseInterface {
            return new JsonResponse([
                'app_name' => $appName,
                'debug' => $debug,
                'timeout' => $timeout,
                'service_id' => $service->getId(),
                'action' => $action
            ]);
        });
        
        $request = $this->createRequest('GET', '/?action=test');
        $response = $middleware->process($request, $this->handler);
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals('Test App', $data['app_name']);
        $this->assertTrue($data['debug']);
        $this->assertEquals(30, $data['timeout']); // default value
        $this->assertEquals('test-service-123', $data['service_id']);
        $this->assertEquals('test', $data['action']);
    }
    
    public function testMiddlewareGroup(): void
    {
        $group = new MiddlewareGroup([
            function($request, $next) { return $next($request); },
            'test.middleware'
        ]);
        
        $middleware = $this->factory->makeMiddleware($group);
        
        $this->assertInstanceOf(MiddlewareInterface::class, $middleware);
    }
    
    public function testMiddlewareFactoryAwareStrategy(): void
    {
        $strategy = new MiddlewarePipelineStrategy();
        
        // Strategy should receive factory when added
        $this->factory->addStrategy($strategy);
        
        // Create middleware group
        $group = new MiddlewareGroup(['test.middleware']);
        
        // Strategy should successfully handle group
        $middleware = $strategy->makeMiddleware($group);
        $this->assertInstanceOf(MiddlewareInterface::class, $middleware);
    }
    
    public function testStrategyWithoutFactoryThrowsException(): void
    {
        $strategy = new MiddlewarePipelineStrategy(); // Without factory
        $group = new MiddlewareGroup(['test.middleware']);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('MiddlewareFactory is required to convert MiddlewareGroup to Pipeline');
        
        $strategy->makeMiddleware($group);
    }
    
    public function testNestedConfigAccess(): void
    {
        $this->container->set('config', new ArrayObject([
            'database' => [
                'connections' => [
                    'mysql' => ['host' => 'localhost', 'port' => 3306],
                    'redis' => ['host' => '127.0.0.1', 'port' => 6379]
                ]
            ]
        ]));
        
        $middleware = $this->factory->makeMiddleware(function(
            #[Config('database.connections.mysql.host')] string $mysqlHost,
            #[Config('database.connections.redis.port')] int $redisPort
        ): ResponseInterface {
            return new JsonResponse([
                'mysql_host' => $mysqlHost,
                'redis_port' => $redisPort
            ]);
        });
        
        $request = $this->createRequest('GET', '/');
        $response = $middleware->process($request, $this->handler);
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals('localhost', $data['mysql_host']);
        $this->assertEquals(6379, $data['redis_port']);
    }
}
```

## License

MIT License
