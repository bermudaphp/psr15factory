# Bermuda PSR-15 Factory

Мощная и гибкая фабрика для создания PSR-15 совместимых middleware в PHP. Поддерживает различные типы middleware определений, автоматическое внедрение зависимостей, мапинг данных запроса и множество паттернов middleware.

## Требования

- PHP 8.4+
- PSR-7 HTTP Message Interface
- PSR-11 Container Interface  
- PSR-15 HTTP Server Request Handlers
- bermudaphp/di-resolver (для атрибутов `#[Config]` и `#[Inject]`)

## Установка

```bash
composer require bermudaphp/psr15factory
```

## Основные возможности

### 🎯 Универсальная фабрика middleware
- Автоматическое определение типа middleware
- Поддержка callables, классов, объектов и пайплайнов
- Стратегический паттерн для расширяемости

### 🔄 Адаптация различных паттернов
- **Single-pass middleware**: `function($request, $next)`
- **Double-pass middleware**: `function($request, $response, $next)`
- **Standard PSR-15**: `MiddlewareInterface` и `RequestHandlerInterface`

### 💉 Автоматическое внедрение зависимостей
- Резолвинг параметров через PSR-11 контейнер
- Мапинг данных запроса в параметры методов
- Поддержка PHP 8+ атрибутов

### 🗺️ Мапинг данных запроса
- Query параметры (`#[MapQueryParameter]`)
- Request payload - JSON, form data (`#[MapRequestPayload]`) 
- Request attributes (`#[RequestAttribute]`)
- Конфигурация приложения (`#[Config]`)
- DI контейнер инъекции (`#[Inject]`)
- Гибкое переименование полей

## Быстрый старт

### Базовая настройка

```php
use Bermuda\MiddlewareFactory\MiddlewareFactory;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Создание фабрики
$factory = MiddlewareFactory::createFromContainer($container);

// Создание middleware из различных определений
$middleware1 = $factory->makeMiddleware('App\\Middleware\\AuthMiddleware');
$middleware2 = $factory->makeMiddleware(function(ServerRequestInterface $request, callable $next): ResponseInterface {
    // Single-pass middleware
    return $next($request);
});
$middleware3 = $factory->makeMiddleware([$middlewareArray]);
```

### Пример с контроллером

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
        // Автоматически извлекает ?page=2&limit=20&q=john
        // $page = 2, $limit = 20, $search = "john"
        
        return new JsonResponse(['users' => $this->userService->find($search, $page, $limit)]);
    }
    
    public function createUser(
        #[MapRequestPayload(['full_name' => 'name'])] CreateUserRequest $request
    ): ResponseInterface {
        // Автоматически мапит JSON payload в DTO с переименованием полей
        return new JsonResponse(['user' => $this->userService->create($request)]);
    }
}
```

## Типы middleware

### 1. Class Name Strategy

Разрешает middleware по имени класса из контейнера:

```php
// Регистрация в контейнере
$container->set(AuthMiddleware::class, new AuthMiddleware());

// Использование
$middleware = $factory->makeMiddleware(AuthMiddleware::class);
```

### 2. Callable Strategy

Автоматически определяет и адаптирует различные callable паттерны. Поддерживает множество форматов callable благодаря встроенному CallableResolver:

#### Поддерживаемые форматы callable:

**1. Closures (замыкания)**
```php
$middleware = $factory->makeMiddleware(function(ServerRequestInterface $request, callable $next): ResponseInterface {
    return $next($request);
});
```

**2. Стандартные PHP callable**
```php
$middleware = $factory->makeMiddleware([$object, 'methodName']);
$middleware = $factory->makeMiddleware('globalFunction');
```

**3. Строковые представления:**

- **Статические методы классов**: `"Class::method"`
```php
$middleware = $factory->makeMiddleware('App\\Middleware\\AuthMiddleware::handle');
```

- **Методы сервисов из контейнера**: `"serviceId::method"`  
```php
$middleware = $factory->makeMiddleware('auth.service::authenticate');
```

- **Глобальные функции**: `"functionName"`
```php
$middleware = $factory->makeMiddleware('myGlobalMiddlewareFunction');
```

- **Сервисы-callable из контейнера**: `"serviceId"`
```php
// Сервис, который сам является callable
$middleware = $factory->makeMiddleware('custom.middleware.service');
```

**4. Массивы:**

- **Объект и метод**: `[object, method]`
```php
$authService = new AuthService();
$middleware = $factory->makeMiddleware([$authService, 'authenticate']);
```

- **ID сервиса и метод**: `[serviceId, method]`
```php
$middleware = $factory->makeMiddleware(['user.service', 'validateToken']);
```

#### Паттерны middleware (автоматическое определение):

##### Single-pass middleware
```php
$middleware = $factory->makeMiddleware(function(ServerRequestInterface $request, callable $next): ResponseInterface {
    // Предварительная обработка
    $request = $request->withAttribute('timestamp', time());
    
    // Вызов следующего middleware
    $response = $next($request);
    
    // Постобработка
    return $response->withHeader('X-Processing-Time', microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']);
});

// Или через сервис
$middleware = $factory->makeMiddleware('logging.middleware::process');
```

##### Double-pass middleware  
```php
$middleware = $factory->makeMiddleware(function(
    ServerRequestInterface $request, 
    ResponseInterface $response, 
    callable $next
): ResponseInterface {
    // Работа с базовым response объектом
    if ($request->getHeaderLine('Accept') === 'application/json') {
        return $next($request);
    }
    
    return $response->withStatus(406);
});

// Или через класс
$middleware = $factory->makeMiddleware('App\\Middleware\\LegacyMiddleware::handle');
```

##### Standard callable с DI
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

// Или через сервис с DI
$middleware = $factory->makeMiddleware('api.middleware::handleAuth');
```

#### Factory Callable

Для динамического создания middleware с использованием контейнера:

```php
// Factory callable - создается только при первом использовании
$middleware = $factory->makeMiddleware(static function(ContainerInterface $c) use ($uri, $permanent): RedirectMiddleware {         
    return new RedirectMiddleware($uri, $c->get(ResponseFactoryInterface::class), $permanent);
});

$middleware instanceof MiddlewareInterface; // true
$middleware instanceof RedirectMiddleware; // true

// Комплексный factory callable с конфигурацией
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
    
    // Возвращаем пустой middleware если компрессия отключена
    return new class implements MiddlewareInterface {
        public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
            return $handler->handle($request);
        }
    };
});
```

#### Примеры с различными форматами:

```php
// Контейнер с различными middleware сервисами
$container->set('auth.middleware', new AuthenticationMiddleware());
$container->set('auth.service', new AuthService());
$container->set('rate.limiter', new RateLimitMiddleware());

// Различные способы создания middleware:

// 1. Прямое замыкание
$middleware1 = $factory->makeMiddleware(function($request, $next) {
    return $next($request);
});

// 2. Сервис-middleware из контейнера
$middleware2 = $factory->makeMiddleware('auth.middleware');

// 3. Метод сервиса из контейнера
$middleware3 = $factory->makeMiddleware('auth.service::validateRequest');

// 4. Статический метод класса
$middleware4 = $factory->makeMiddleware('App\\Utils\\SecurityUtils::checkOrigin');

// 5. Массив с сервисом и методом
$middleware5 = $factory->makeMiddleware(['rate.limiter', 'handle']);

// 6. Глобальная функция
$middleware6 = $factory->makeMiddleware('customSecurityHandler');

// 7. Прямой объект и метод
$corsHandler = new CorsHandler();
$middleware7 = $factory->makeMiddleware([$corsHandler, 'process']);
```

### 3. Pipeline Strategy и MiddlewareGroup

Для комбинирования нескольких middleware в единый пайплайн:

```php
use Bermuda\MiddlewareFactory\MiddlewareGroup;

$group = new MiddlewareGroup([
    'App\\Middleware\\AuthMiddleware',
    ['cors.service', 'handle'],
    function($request, $next) { return $next($request); }
]);

$middleware = $factory->makeMiddleware($group);

// Добавление middleware
$newGroup = $group->add('rate.limiter::check');
$newGroup = $group->addMany(['cache.middleware', 'response.formatter']);

// Проверки
echo $group->count(); // Количество middleware
foreach ($group as $definition) {
    echo get_debug_type($definition) . "\n";
}
```

## Мапинг данных запроса

Фабрика поддерживает автоматическое извлечение и преобразование данных из PSR-7 запросов в параметры методов с помощью PHP 8+ атрибутов. Это обеспечивает чистое разделение между обработкой HTTP-данных и бизнес-логикой.

### Query параметры

Атрибут `#[MapQueryParameter]` извлекает отдельные параметры из query string:

```php
use Bermuda\MiddlewareFactory\Attribute\MapQueryParameter;

public function search(
    #[MapQueryParameter] string $query,           // ?query=something
    #[MapQueryParameter('p')] int $page = 1,      // ?p=2 (извлекает параметр 'p')
    #[MapQueryParameter] ?string $category = null // ?category=books (опционально)
): ResponseInterface {
    // $query = "something", $page = 2, $category = "books"
    
    $results = $this->searchService->search($query, $page, $category);
    return new JsonResponse(['results' => $results]);
}

// Продвинутое использование с типизацией
public function filter(
    #[MapQueryParameter] int $page = 1,              // Автоматическое приведение к int
    #[MapQueryParameter] bool $active = true,        // Строковые "true"/"false" → bool
    #[MapQueryParameter] array $tags = [],           // Множественные значения ?tags[]=php&tags[]=api
    #[MapQueryParameter('min_price')] ?float $minPrice = null  // Извлекает 'min_price' как float
): ResponseInterface {
    // Все параметры автоматически приведены к нужным типам
    
    $filters = compact('page', 'active', 'tags', 'minPrice');
    $products = $this->productService->filter($filters);
    
    return new JsonResponse(['products' => $products]);
}
```

### Query string (весь набор параметров)

Атрибут `#[MapQueryString]` извлекает все query параметры с возможностью переименования:

```php
use Bermuda\MiddlewareFactory\Attribute\MapQueryString;

public function advancedSearch(
    #[MapQueryString] array $filters
): ResponseInterface {
    // ?name=John&age=25&city=NYC&sort=name&dir=asc
    // $filters = ['name' => 'John', 'age' => '25', 'city' => 'NYC', 'sort' => 'name', 'dir' => 'asc']
    
    return $this->processFilters($filters);
}

// С переименованием полей
public function listProducts(
    #[MapQueryString(['sort' => 'sortBy', 'dir' => 'direction', 'q' => 'search'])]
    array $queryParams
): ResponseInterface {
    // ?sort=price&dir=asc&q=laptop&limit=10&offset=20
    // $queryParams = [
    //     'sortBy' => 'price',      // поле 'sort' переименовано в 'sortBy'
    //     'direction' => 'asc',     // поле 'dir' переименовано в 'direction'
    //     'search' => 'laptop',     // поле 'q' переименовано в 'search'
    //     'limit' => '10',          // сохранено как есть
    //     'offset' => '20'          // сохранено как есть
    // ]
    
    $products = $this->productService->search($queryParams);
    return new JsonResponse(['products' => $products]);
}

// Использование с DTO
public function searchWithDTO(
    #[MapQueryString(['q' => 'query', 'cat' => 'category'])]
    SearchCriteria $criteria
): ResponseInterface {
    // Query параметры автоматически мапятся в объект SearchCriteria
    // 'q' → 'query', 'cat' → 'category' при создании объекта
    
    $results = $this->searchService->search($criteria);
    return new JsonResponse($results);
}
```

### Request payload (тело запроса)

Атрибут `#[MapRequestPayload]` извлекает данные из тела запроса (JSON, form data, XML):

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

// С переименованием полей API → внутренние имена
public function updateProfile(
    #[MapRequestPayload(['full_name' => 'name', 'phone_number' => 'phone'])]
    array $profileData
): ResponseInterface {
    // POST /profile
    // {"full_name": "John Doe", "phone_number": "+1234567890", "bio": "Developer"}
    //
    // $profileData = [
    //     'name' => 'John Doe',        // поле 'full_name' переименовано в 'name'
    //     'phone' => '+1234567890',    // поле 'phone_number' переименовано в 'phone'
    //     'bio' => 'Developer'         // сохранено как есть
    // ]
    
    return $this->profileService->update($profileData);
}

// Прямое мапирование в DTO
public function createProduct(
    #[MapRequestPayload] CreateProductRequest $request,
    #[RequestAttribute] User $currentUser
): ResponseInterface {
    // JSON payload автоматически преобразуется в CreateProductRequest
    // через MiddlewareFactory с использованием DI контейнера
    
    $product = $this->productService->create($request, $currentUser);
    return new JsonResponse(['product' => $product], 201);
}

// Комплексное мапирование с валидацией
public function processOrder(
    #[MapRequestPayload([
        'customer_info' => 'customer',
        'payment_method' => 'payment',
        'shipping_address' => 'shipping'
    ])]
    ProcessOrderRequest $orderRequest
): ResponseInterface {
    // Сложные вложенные данные автоматически мапятся в структурированный DTO
    
    $order = $this->orderService->process($orderRequest);
    return new JsonResponse(['order' => $order], 201);
}
```

### Request attributes

Атрибут `#[RequestAttribute]` извлекает данные, установленные предыдущими middleware:

```php
use Bermuda\MiddlewareFactory\Attribute\RequestAttribute;

public function getProfile(
    #[RequestAttribute] User $currentUser,              // $request->getAttribute('currentUser')
    #[RequestAttribute('route.id')] int $userId         // $request->getAttribute('route.id')
): ResponseInterface {
    // Attributes обычно устанавливаются предыдущими middleware
    // например, аутентификацией или роутингом
    
    if ($currentUser->getId() !== $userId && !$currentUser->isAdmin()) {
        return new JsonResponse(['error' => 'Access denied'], 403);
    }
    
    $profile = $this->userService->getProfile($userId);
    return new JsonResponse(['profile' => $profile]);
}

// Работа с опциональными attributes
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

### Комбинирование различных источников данных

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
    // Данные из разных источников автоматически объединяются
    // Query: ?page=2&limit=20
    // Body: {"filter_data": {"category": "electronics", "price_min": 100}}
    // Attributes: currentUser, route.resource
    // Config: app.max_results
    // DI: SearchService
    
    $limit = min($limit, $maxResults); // Ограничиваем по конфигурации
    
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

### Обработка ошибок мапинга

```php
// Middleware автоматически обрабатывает ошибки мапинга
public function handleErrors(
    #[MapQueryParameter] int $requiredParam,        // Обязательный параметр
    #[MapRequestPayload] ValidatedRequest $request  // DTO с валидацией
): ResponseInterface {
    // Если requiredParam отсутствует в query string:
    // → OutOfBoundsException → ParameterResolutionException
    //
    // Если request body не может быть преобразован в ValidatedRequest:
    // → соответствующее исключение от MiddlewareFactory

    return new JsonResponse(['success' => true]);
}

// Кастомная обработка ошибок
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

### Конфигурация и DI контейнер

```php
use Bermuda\DI\Attribute\Config;
use Bermuda\DI\Attribute\Inject;

public function processPayment(
    #[Config('payment.api_key')] string $apiKey,                    // Из конфигурации
    #[Config('payment.timeout', 30)] int $timeout,                  // Со значением по умолчанию
    #[Inject('payment.gateway')] PaymentGateway $gateway,           // Сервис по имени
    #[Inject('logger')] LoggerInterface $logger,                    // Логгер из контейнера
    PaymentService $paymentService,                                 // Автоматически по типу
    #[MapRequestPayload] PaymentRequest $request                    // Данные из запроса
): ResponseInterface {
    $logger->info("Обработка платежа с timeout: {$timeout}s");
    
    $result = $gateway->processPayment($request, [
        'api_key' => $apiKey,
        'timeout' => $timeout
    ]);
    
    return new JsonResponse(['result' => $result]);
}
```

### Комплексный пример с конфигурацией

```php
public function handleUpload(
    #[Config('upload.max_size')] int $maxSize,                      // Максимальный размер файла
    #[Config('upload.allowed_types', ['jpg', 'png'])] array $types, // Разрешённые типы
    #[Config('storage.path')] string $storagePath,                  // Путь для хранения
    #[Inject('file.validator')] FileValidator $validator,           // Валидатор файлов
    #[Inject('storage.manager')] StorageManager $storage,           // Менеджер хранилища
    #[MapRequestPayload] UploadRequest $uploadData,                 // Данные загрузки
    #[RequestAttribute] User $currentUser                           // Текущий пользователь
): ResponseInterface {
    // Валидация размера файла
    if ($uploadData->getFileSize() > $maxSize) {
        return new JsonResponse(['error' => 'Файл слишком большой'], 413);
    }
    
    // Валидация типа файла  
    if (!in_array($uploadData->getFileType(), $types)) {
        return new JsonResponse(['error' => 'Недопустимый тип файла'], 415);
    }
    
    // Дополнительная валидация
    if (!$validator->validate($uploadData)) {
        return new JsonResponse(['error' => 'Файл не прошёл валидацию'], 422);
    }
    
    // Сохранение файла
    $savedFile = $storage->store($uploadData, $storagePath, $currentUser);
    
    return new JsonResponse(['file' => $savedFile], 201);
}
```

## Расширенное использование

### Создание кастомной стратегии

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
        
        return null; // Не можем обработать этот тип
    }
}

// Регистрация кастомной стратегии
$factory->addStrategy(new CustomStrategy(), true); // true = добавить в начало
```

### Конфигурация через контейнер

```php
use Bermuda\MiddlewareFactory\ConfigProvider;

// В конфигурации контейнера
return [
    ConfigProvider::CONFIG_KEY_STRATEGIES => [
        CustomStrategy::class,
        AnotherStrategy::class
    ]
];
```

### Обработка ошибок

```php
use Bermuda\MiddlewareFactory\MiddlewareResolutionException;

try {
    $middleware = $factory->makeMiddleware($invalidDefinition);
} catch (MiddlewareResolutionException $e) {
    echo "Не удалось создать middleware: " . $e->getMessage();
    echo "Тип middleware: " . get_debug_type($e->middleware);
    
    // Получение информации о вложенной ошибке
    if ($e->getPrevious()) {
        echo "Причина: " . $e->getPrevious()->getMessage();
    }
}
```

## Архитектура

### Основные компоненты

- **MiddlewareFactory** - Главная фабрика, координирующая стратегии
- **Strategy** - Интерфейс для стратегий разрешения middleware
- **Adapter** - Адаптеры для конвертации типов в PSR-15
- **Resolver** - Резолверы для внедрения зависимостей
- **Attribute** - PHP 8+ атрибуты для декларативного мапинга

### Поток выполнения

1. **Разрешение middleware** - Factory пробует стратегии по порядку
2. **Адаптация** - Приведение к PSR-15 MiddlewareInterface
3. **Внедрение зависимостей** - Резолвинг параметров через контейнер  
4. **Мапинг данных** - Извлечение данных из запроса по атрибутам
5. **Выполнение** - Вызов middleware с подготовленными параметрами

## Примеры реальных применений

### REST API контроллер

```php
use Bermuda\DI\Attribute\Config;
use Bermuda\DI\Attribute\Inject;

class ProductController
{
    public function list(
        #[MapQueryParameter] int $page = 1,
        #[MapQueryParameter('per_page')] int $perPage = 10,
        #[MapQueryString(['sort' => 'sortBy', 'filter' => 'filters'])] array $query = [],
        #[Config('products.default_limit', 50)] int $maxLimit,        // Максимальный лимит из конфига
        #[Inject('product.search')] ProductSearchService $search      // Сервис поиска
    ): ResponseInterface {
        // Ограничение лимита
        $perPage = min($perPage, $maxLimit);
        
        $products = $search->paginate($page, $perPage, $query);
        return new JsonResponse(['data' => $products]);
    }
    
    public function create(
        #[MapRequestPayload] CreateProductRequest $request,
        #[RequestAttribute] User $currentUser,
        #[Config('products.auto_publish')] bool $autoPublish,         // Автоматическая публикация
        #[Inject('product.factory')] ProductFactory $factory,         // Фабрика продуктов
        #[Inject('event.dispatcher')] EventDispatcher $events         // Диспетчер событий
    ): ResponseInterface {
        $product = $factory->create($request, $currentUser);
        
        if ($autoPublish) {
            $product->publish();
        }
        
        $this->productService->save($product);
        
        // Отправка события
        $events->dispatch(new ProductCreated($product));
        
        return new JsonResponse(['product' => $product], 201);
    }
    
    public function update(
        #[RequestAttribute('route.id')] int $id,
        #[MapRequestPayload] UpdateProductRequest $request,
        #[Config('products.versioning.enabled')] bool $versioningEnabled,  // Версионирование
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
        #[Config('upload.images.max_size')] int $maxSize,              // Максимальный размер
        #[Config('upload.images.quality', 85)] int $quality,           // Качество сжатия
        #[Config('cdn.base_url')] string $cdnUrl,                      // URL CDN
        #[Inject('image.processor')] ImageProcessor $processor,        // Обработчик изображений
        #[Inject('cdn.uploader')] CdnUploader $uploader               // Загрузчик в CDN
    ): ResponseInterface {
        $product = $this->productService->findById($productId);
        
        // Обработка изображения
        $processedImage = $processor->process($uploadRequest->getFile(), [
            'max_size' => $maxSize,
            'quality' => $quality
        ]);
        
        // Загрузка в CDN
        $cdnPath = $uploader->upload($processedImage, "products/{$productId}");
        $imageUrl = $cdnUrl . '/' . $cdnPath;
        
        // Сохранение URL в продукт
        $product->addImage($imageUrl);
        $this->productService->save($product);
        
        return new JsonResponse(['image_url' => $imageUrl]);
    }
}
```

### Middleware с внедрением зависимостей

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
    // Извлечение токена из различных источников
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
    
    return null; // Продолжить выполнение
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

### Комплексная обработка данных

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
    // Получение правил обработки для конкретного действия
    $actionRules = $globalRules[$action] ?? [];
    $rules = $ruleFactory->buildRules($actionRules, $user, $strictMode);
    
    // Выполнение обработки
    $result = $processor->process($data, $rules, ['max_errors' => $maxErrors]);
    
    if (!$result->isValid()) {
        $errors = $result->getErrors();
        
        // В строгом режиме возвращаем все ошибки
        if ($strictMode) {
            return new JsonResponse([
                'error' => 'Processing failed',
                'errors' => $errors,
                'strict_mode' => true
            ], 422);
        }
        
        // В обычном режиме возвращаем первые N ошибок
        return new JsonResponse([
            'error' => 'Processing failed', 
            'errors' => array_slice($errors, 0, $maxErrors)
        ], 422);
    }
    
    return null; // Продолжить выполнение
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
    
    // Генерация ключа кэша
    $cacheKey = $keyPrefix . ':' . md5($request->getUri() . serialize($request->getQueryParams()));
    
    // Попытка получить из кэша
    $cachedResponse = $cache->get($cacheKey);
    if ($cachedResponse !== null) {
        return new JsonResponse($cachedResponse)->withHeader('X-Cache', 'HIT');
    }
    
    // Выполнение запроса
    $response = $next($request);
    
    // Кэширование успешных ответов
    if ($response->getStatusCode() === 200) {
        $responseData = json_decode($response->getBody()->getContents(), true);
        $cache->set($cacheKey, $responseData, $cacheTtl);
    }
    
    return $response->withHeader('X-Cache', 'MISS');
});
```

## Лучшие практики

### 1. Порядок стратегий
Регистрируйте более специфичные стратегии первыми:

```php
$factory->addStrategy(new CustomStrategy(), true);     // Первой
$factory->addStrategy(new ClassNameStrategy());       // По умолчанию
$factory->addStrategy(new CallableStrategy());        // По умолчанию  
```

### 2. Обработка ошибок
Всегда оборачивайте создание middleware в try-catch:

```php
try {
    $middleware = $factory->makeMiddleware($definition);
} catch (MiddlewareResolutionException $e) {
    // Логирование и обработка ошибки
    $logger->error('Middleware resolution failed', [
        'middleware' => $e->middleware,
        'message' => $e->getMessage()
    ]);
    throw $e;
}
```

### 3. Типизация параметров
Используйте строгую типизацию для автоматического приведения типов:

```php
public function handle(
    #[MapQueryParameter] int $page,        // Автоматическое приведение к int
    #[MapQueryParameter] bool $active,     // Автоматическое приведение к bool
    #[MapRequestPayload] CreateUserRequest $request // Строгая типизация DTO
): ResponseInterface {
    // Гарантированно корректные типы
}
```

### Лучшие практики

### 1. Порядок стратегий
Регистрируйте более специфичные стратегии первыми:

```php
$factory->addStrategy(new CustomStrategy(), true);     // Первой
$factory->addStrategy(new ClassNameStrategy());       // По умолчанию
$factory->addStrategy(new CallableStrategy());        // По умолчанию  
```

### 2. Обработка ошибок
Всегда оборачивайте создание middleware в try-catch:

```php
try {
    $middleware = $factory->makeMiddleware($definition);
} catch (MiddlewareResolutionException $e) {
    // Логирование и обработка ошибки
    $logger->error('Middleware resolution failed', [
        'middleware' => $e->middleware,
        'message' => $e->getMessage()
    ]);
    throw $e;
}
```

### 3. Типизация параметров
Используйте строгую типизацию для автоматической валидации:

```php
public function handle(
    #[MapQueryParameter] int $page,        // Автоматическое приведение к int
    #[MapQueryParameter] bool $active,     // Автоматическое приведение к bool
    #[MapRequestPayload] CreateUserRequest $request // Строгая типизация DTO
): ResponseInterface {
    // Гарантированно корректные типы
}
```

### 4. Документирование атрибутов
Документируйте использованные атрибуты для ясности:

```php
/**
 * Создает нового пользователя
 * 
 * @param CreateUserRequest $request Данные пользователя из request body
 * @param User $currentUser Текущий пользователь из middleware авторизации  
 * @param string $role Роль из query параметра 'role'
 * @param int $maxUsers Максимальное количество пользователей из конфигурации
 * @param UserFactory $factory Фабрика пользователей из DI контейнера
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

### 5. Использование конфигурации
Группируйте связанные настройки и используйте значения по умолчанию:

```php
public function processImage(
    #[Config('image.processing.max_width', 1920)] int $maxWidth,
    #[Config('image.processing.max_height', 1080)] int $maxHeight,
    #[Config('image.processing.quality', 85)] int $quality,
    #[Config('image.processing.format', 'webp')] string $format,
    #[Inject('image.processor')] ImageProcessor $processor
): ResponseInterface {
    // Настройки с разумными значениями по умолчанию
}
```

### 6. Инъекция сервисов
Используйте осмысленные имена сервисов в контейнере:

```php
// Хорошо - понятные имена сервисов
#[Inject('payment.stripe.gateway')] StripeGateway $stripe,
#[Inject('payment.paypal.gateway')] PayPalGateway $paypal,
#[Inject('notification.email')] EmailService $emailService,
#[Inject('notification.sms')] SmsService $smsService

// Плохо - неясные имена
#[Inject('service1')] SomeService $service,
#[Inject('gateway')] Gateway $gateway
```

## Производительность

Фабрика оптимизирована для производительности:

- **Ленивое создание** - Middleware создается только при необходимости
- **Кэширование стратегий** - Стратегии регистрируются один раз
- **Минимальная рефлексия** - Рефлексия используется только для анализа сигнатур
- **Эффективная адаптация** - Минимальный overhead при адаптации типов

## Тестирование

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
        // Настройка контейнера для тестирования
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
        $this->assertEquals(30, $data['timeout']); // значение по умолчанию
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
        
        // Стратегия должна получить фабрику при добавлении
        $this->factory->addStrategy($strategy);
        
        // Создаем группу middleware
        $group = new MiddlewareGroup(['test.middleware']);
        
        // Стратегия должна успешно обработать группу
        $middleware = $strategy->makeMiddleware($group);
        $this->assertInstanceOf(MiddlewareInterface::class, $middleware);
    }
    
    public function testStrategyWithoutFactoryThrowsException(): void
    {
        $strategy = new MiddlewarePipelineStrategy(); // Без фабрики
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

## Лицензия

MIT License
