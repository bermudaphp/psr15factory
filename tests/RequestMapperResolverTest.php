<?php

declare(strict_types=1);

namespace Bermuda\MiddlewareFactory\Tests;

use Bermuda\MiddlewareFactory\Attribute\MapQueryParameter;
use Bermuda\MiddlewareFactory\Attribute\MapQueryString;
use Bermuda\MiddlewareFactory\Attribute\MapRequestPayload;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ServerRequestInterface;
use Bermuda\DI\FactoryInterface;
use Bermuda\ParameterResolver\ParameterResolutionException;
use Bermuda\MiddlewareFactory\Resolver\RequestMapperResolver;
use Bermuda\MiddlewareFactory\Resolver\RequestParameter;

/**
 * Tests for RequestMapperResolver class with real attributes
 */
class RequestMapperResolverTest extends TestCase
{
    private RequestMapperResolver $resolver;
    private FactoryInterface|MockObject $factory;
    private ServerRequestInterface|MockObject $request;

    protected function setUp(): void
    {
        $this->factory = $this->createMock(FactoryInterface::class);
        $this->resolver = new RequestMapperResolver($this->factory);
        $this->request = $this->createMock(ServerRequestInterface::class);
    }

    public function testResolveWithoutMappingAttribute(): void
    {
        $testFunction = function (string $param): void {};
        $reflection = new \ReflectionFunction($testFunction);
        $parameter = $reflection->getParameters()[0];

        $providedParams = RequestParameter::set([], $this->request);

        $result = $this->resolver->resolve($parameter, $providedParams, []);
        $this->assertNull($result);
    }

    public function testResolveWithMissingRequest(): void
    {
        $testFunction = function (#[MapQueryParameter] string $user): void {};
        $reflection = new \ReflectionFunction($testFunction);
        $parameter = $reflection->getParameters()[0];

        $this->expectException(ParameterResolutionException::class);
        $this->expectExceptionMessage('PSR-7 request instance not found');

        $this->resolver->resolve($parameter, [], []);
    }

    public function testResolveWithMapQueryParameterForArray(): void
    {
        $testFunction = function (#[MapQueryParameter('user_id')] array $data): void {};
        $reflection = new \ReflectionFunction($testFunction);
        $parameter = $reflection->getParameters()[0];

        $this->request->method('getQueryParams')->willReturn(['user_id' => '123']);

        $providedParams = RequestParameter::set([], $this->request);

        $result = $this->resolver->resolve($parameter, $providedParams, []);

        $this->assertIsArray($result);
        $this->assertEquals(0, $result[0]); // position
        $this->assertEquals(['data' => '123'], $result[1]);
    }

    public function testResolveWithMapQueryParameterForClass(): void
    {
        $testFunction = function (#[MapQueryParameter('user_id')] TestUserDto $user): void {};
        $reflection = new \ReflectionFunction($testFunction);
        $parameter = $reflection->getParameters()[0];

        $this->request->method('getQueryParams')->willReturn(['user_id' => '123']);
        $this->request->method('getAttributes')->willReturn(['request_id' => 'abc']);

        $providedParams = RequestParameter::set([], $this->request);

        $expectedUser = new TestUserDto(123, 'Test User');

        $this->factory->expects($this->once())
            ->method('make')
            ->with(
                TestUserDto::class,
                $this->callback(function($params) {
                    return isset($params['request_id']) && $params['request_id'] === 'abc' &&
                        isset($params['user']) && $params['user'] === '123';
                })
            )
            ->willReturn($expectedUser);

        $result = $this->resolver->resolve($parameter, $providedParams, []);

        $this->assertEquals([0, $expectedUser], $result);
    }

    public function testResolveWithMapQueryStringForArray(): void
    {
        $testFunction = function (#[MapQueryString(['id' => 'user_id', 'name' => 'username'])] array $params): void {};
        $reflection = new \ReflectionFunction($testFunction);
        $parameter = $reflection->getParameters()[0];

        $this->request->method('getQueryParams')->willReturn([
            'id' => '456',
            'name' => 'John',
            'email' => 'john@example.com'
        ]);

        $providedParams = RequestParameter::set([], $this->request);

        $result = $this->resolver->resolve($parameter, $providedParams, []);

        $this->assertIsArray($result);
        $this->assertEquals(0, $result[0]);
        $expected = [
            'user_id' => '456',
            'username' => 'John',
            'email' => 'john@example.com'
        ];
        $this->assertEquals($expected, $result[1]);
    }

    public function testResolveWithMapRequestPayloadForArray(): void
    {
        $testFunction = function (#[MapRequestPayload(['email' => 'user_email'])] array $data): void {};
        $reflection = new \ReflectionFunction($testFunction);
        $parameter = $reflection->getParameters()[0];

        $this->request->method('getParsedBody')->willReturn([
            'email' => 'test@example.com',
            'password' => 'secret'
        ]);

        $providedParams = RequestParameter::set([], $this->request);

        $result = $this->resolver->resolve($parameter, $providedParams, []);

        $this->assertIsArray($result);
        $this->assertEquals(0, $result[0]);
        $expected = [
            'user_email' => 'test@example.com',
            'password' => 'secret'
        ];
        $this->assertEquals($expected, $result[1]);
    }

    public function testResolveWithMissingQueryParameter(): void
    {
        $testFunction = function (#[MapQueryParameter('missing_param')] string $user): void {};
        $reflection = new \ReflectionFunction($testFunction);
        $parameter = $reflection->getParameters()[0];

        $this->request->method('getQueryParams')->willReturn(['other_param' => 'value']);

        $providedParams = RequestParameter::set([], $this->request);

        $this->expectException(\OutOfBoundsException::class);
        $this->expectExceptionMessage("Required query parameter 'missing_param' is missing from the request");

        $this->resolver->resolve($parameter, $providedParams, []);
    }

    public function testResolveWithFactoryException(): void
    {
        $testFunction = function (#[MapQueryParameter('user_id')] TestUserDto $user): void {};
        $reflection = new \ReflectionFunction($testFunction);
        $parameter = $reflection->getParameters()[0];

        $this->request->method('getQueryParams')->willReturn(['user_id' => '123']);
        $this->request->method('getAttributes')->willReturn([]);

        $providedParams = RequestParameter::set([], $this->request);

        $factoryError = new \Exception('Factory creation failed');
        $this->factory->expects($this->once())
            ->method('make')
            ->willThrowException($factoryError);

        $this->expectException(ParameterResolutionException::class);

        $this->resolver->resolve($parameter, $providedParams, []);
    }

    public function testResolveWithNonExistentClass(): void
    {
        // Create a function that uses a non-existent class
        $testFunction = function (#[MapQueryParameter('user_id')] NonExistentClass $obj): void {};
        $reflection = new \ReflectionFunction($testFunction);
        $parameter = $reflection->getParameters()[0];

        $this->request->method('getQueryParams')->willReturn(['user_id' => '123']);

        $providedParams = RequestParameter::set([], $this->request);

        $result = $this->resolver->resolve($parameter, $providedParams, []);
        $this->assertNull($result);
    }

    public function testResolveWithNoTypeHint(): void
    {
        $testFunction = function (#[MapQueryParameter('user_id')] $param): void {};
        $reflection = new \ReflectionFunction($testFunction);
        $parameter = $reflection->getParameters()[0];

        $this->request->method('getQueryParams')->willReturn(['user_id' => '123']);

        $providedParams = RequestParameter::set([], $this->request);

        $result = $this->resolver->resolve($parameter, $providedParams, []);
        $this->assertNull($result);
    }

    public function testResolveWithEmptyRequestBody(): void
    {
        $testFunction = function (#[MapRequestPayload()] array $data): void {};
        $reflection = new \ReflectionFunction($testFunction);
        $parameter = $reflection->getParameters()[0];

        $this->request->method('getParsedBody')->willReturn(null);

        $providedParams = RequestParameter::set([], $this->request);

        $result = $this->resolver->resolve($parameter, $providedParams, []);

        $this->assertIsArray($result);
        $this->assertEquals(0, $result[0]);
        $this->assertEquals([], $result[1]);
    }

    public function testMapMethodWithComplexMapping(): void
    {
        $testFunction = function (#[MapQueryString([
            'p' => 'page',
            'per_page' => 'limit',
            'sort_by' => 'orderField'
        ])] array $pagination): void {};

        $reflection = new \ReflectionFunction($testFunction);
        $parameter = $reflection->getParameters()[0];

        $this->request->expects($this->once())
            ->method('getQueryParams')
            ->willReturn([
                'p' => '2',
                'per_page' => '20',
                'sort_by' => 'name',
                'order' => 'asc',
                'filter' => 'active'
            ]);

        $providedParams = RequestParameter::set([], $this->request);

        $result = $this->resolver->resolve($parameter, $providedParams, []);

        $this->assertIsArray($result);
        $expected = [
            'page' => '2',
            'limit' => '20',
            'orderField' => 'name',
            'order' => 'asc',
            'filter' => 'active'
        ];
        $this->assertEquals($expected, $result[1]);
    }

    public function testResolveWithMapQueryParameterUsingParameterName(): void
    {
        // Test when MapQueryParameter doesn't specify name, should use parameter name
        $testFunction = function (#[MapQueryParameter] array $userId): void {};
        $reflection = new \ReflectionFunction($testFunction);
        $parameter = $reflection->getParameters()[0];

        $this->request->method('getQueryParams')->willReturn(['userId' => '456']);

        $providedParams = RequestParameter::set([], $this->request);

        $result = $this->resolver->resolve($parameter, $providedParams, []);

        $this->assertIsArray($result);
        $this->assertEquals(['userId' => '456'], $result[1]);
    }

    public function testResolveWithMapRequestPayloadObjectType(): void
    {
        $testFunction = function (#[MapRequestPayload(['name' => 'username'])] TestUserDto $user): void {};
        $reflection = new \ReflectionFunction($testFunction);
        $parameter = $reflection->getParameters()[0];

        $this->request->method('getParsedBody')->willReturn(['name' => 'John', 'age' => 30]);
        $this->request->method('getAttributes')->willReturn(['session_id' => 'abc123']);

        $providedParams = RequestParameter::set([], $this->request);

        $expectedUser = new TestUserDto(30, 'John');

        $this->factory->expects($this->once())
            ->method('make')
            ->with(
                TestUserDto::class,
                $this->callback(function($params) {
                    return isset($params['session_id']) && $params['session_id'] === 'abc123' &&
                        isset($params['username']) && $params['username'] === 'John' &&
                        isset($params['age']) && $params['age'] === 30;
                })
            )
            ->willReturn($expectedUser);

        $result = $this->resolver->resolve($parameter, $providedParams, []);

        $this->assertEquals([0, $expectedUser], $result);
    }

    public function testResolveWithMapQueryStringNoMapping(): void
    {
        $testFunction = function (#[MapQueryString] array $params): void {};
        $reflection = new \ReflectionFunction($testFunction);
        $parameter = $reflection->getParameters()[0];

        $this->request->method('getQueryParams')->willReturn([
            'name' => 'John',
            'age' => '30',
            'city' => 'NYC'
        ]);

        $providedParams = RequestParameter::set([], $this->request);

        $result = $this->resolver->resolve($parameter, $providedParams, []);

        $this->assertIsArray($result);
        $this->assertEquals(0, $result[0]);
        // No mapping, so data should be passed through unchanged
        $expected = [
            'name' => 'John',
            'age' => '30',
            'city' => 'NYC'
        ];
        $this->assertEquals($expected, $result[1]);
    }

    public function testResolveWithMapRequestPayloadNoMapping(): void
    {
        $testFunction = function (#[MapRequestPayload] array $data): void {};
        $reflection = new \ReflectionFunction($testFunction);
        $parameter = $reflection->getParameters()[0];

        $this->request->method('getParsedBody')->willReturn([
            'email' => 'test@example.com',
            'password' => 'secret',
            'remember' => true
        ]);

        $providedParams = RequestParameter::set([], $this->request);

        $result = $this->resolver->resolve($parameter, $providedParams, []);

        $this->assertIsArray($result);
        $this->assertEquals(0, $result[0]);
        // No mapping, so data should be passed through unchanged
        $expected = [
            'email' => 'test@example.com',
            'password' => 'secret',
            'remember' => true
        ];
        $this->assertEquals($expected, $result[1]);
    }
}

// Mock class for testing
class TestUserDto
{
    public function __construct(
        public int $id = 0,
        public string $name = '',
        public string $email = ''
    ) {}
}

// This will be treated as non-existent for testing purposes
class_alias('stdClass', 'NonExistentClass');