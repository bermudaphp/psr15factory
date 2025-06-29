<?php

declare(strict_types=1);

namespace Bermuda\MiddlewareFactory\Tests;

use Bermuda\MiddlewareFactory\Attribute\RequestAttribute;
use Bermuda\MiddlewareFactory\Resolver\RequestAttributeResolver;
use Bermuda\MiddlewareFactory\Resolver\RequestParameter;
use Bermuda\ParameterResolver\ParameterResolutionException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Tests for RequestAttributeResolver class
 */
class RequestAttributeResolverTest extends TestCase
{
    private RequestAttributeResolver $resolver;
    private ServerRequestInterface|MockObject $request;

    protected function setUp(): void
    {
        $this->resolver = new RequestAttributeResolver();
        $this->request = $this->createMock(ServerRequestInterface::class);
    }

    public function testResolveWithoutAttribute(): void
    {
        $testFunction = function (string $testParam): void {};
        $reflection = new \ReflectionFunction($testFunction);
        $parameter = $reflection->getParameters()[0];

        $result = $this->resolver->resolve($parameter, [], []);

        $this->assertNull($result);
    }

    public function testResolveWithMissingRequest(): void
    {
        $testFunction = function (#[RequestAttribute] string $testParam): void {};
        $reflection = new \ReflectionFunction($testFunction);
        $parameter = $reflection->getParameters()[0];

        $this->expectException(ParameterResolutionException::class);
        $this->expectExceptionMessage('No PSR-7 request instance found');

        $this->resolver->resolve($parameter, [], []);
    }

    public function testResolveWithMissingAttribute(): void
    {
        $testFunction = function (#[RequestAttribute] string $missing_attribute): void {};
        $reflection = new \ReflectionFunction($testFunction);
        $parameter = $reflection->getParameters()[0];

        $providedParams = RequestParameter::set([], $this->request);

        $this->request->method('getAttributes')->willReturn([]);

        $this->expectException(ParameterResolutionException::class);
        $this->expectExceptionMessage('The required request attribute [missing_attribute] is not set');

        $this->resolver->resolve($parameter, $providedParams, []);
    }

    public function testResolveWithExistingAttribute(): void
    {
        $testFunction = function (#[RequestAttribute] string $existing_attribute): void {};
        $reflection = new \ReflectionFunction($testFunction);
        $parameter = $reflection->getParameters()[0];

        $providedParams = RequestParameter::set([], $this->request);
        $expectedValue = 'test_value';

        $attributes = ['existing_attribute' => $expectedValue];
        $this->request->method('getAttributes')->willReturn($attributes);
        $this->request->method('getAttribute')
            ->willReturnCallback(function($name, $default = null) use ($attributes) {
                return $attributes[$name] ?? $default;
            });

        $result = $this->resolver->resolve($parameter, $providedParams, []);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals(0, $result[0]); // position
        $this->assertEquals($expectedValue, $result[1]); // value
    }

    public function testResolveWithCustomAttributeName(): void
    {
        $testFunction = function (#[RequestAttribute('custom_name')] string $paramName): void {};
        $reflection = new \ReflectionFunction($testFunction);
        $parameter = $reflection->getParameters()[0];

        $providedParams = RequestParameter::set([], $this->request);
        $expectedValue = 'custom_value';

        $attributes = ['custom_name' => $expectedValue];
        $this->request->method('getAttributes')->willReturn($attributes);
        $this->request->method('getAttribute')
            ->willReturnCallback(function($name, $default = null) use ($attributes) {
                return $attributes[$name] ?? $default;
            });

        $result = $this->resolver->resolve($parameter, $providedParams, []);

        $this->assertIsArray($result);
        $this->assertEquals(0, $result[0]); // position
        $this->assertEquals($expectedValue, $result[1]); // value
    }

    public function testResolveWithMissingCustomAttribute(): void
    {
        $testFunction = function (#[RequestAttribute('missing_custom')] string $paramName): void {};
        $reflection = new \ReflectionFunction($testFunction);
        $parameter = $reflection->getParameters()[0];

        $providedParams = RequestParameter::set([], $this->request);

        $this->request->method('getAttributes')->willReturn(['other_attribute' => 'value']);

        $this->expectException(ParameterResolutionException::class);
        $this->expectExceptionMessage('The required request attribute [missing_custom] is not set');

        $this->resolver->resolve($parameter, $providedParams, []);
    }

    public function testResolveWithNullValueForNonNullableParameter(): void
    {
        $testFunction = function (#[RequestAttribute] string $non_nullable_param): void {};
        $reflection = new \ReflectionFunction($testFunction);
        $parameter = $reflection->getParameters()[0];

        $providedParams = RequestParameter::set([], $this->request);

        $attributes = ['non_nullable_param' => null];
        $this->request->method('getAttributes')->willReturn($attributes);
        $this->request->method('getAttribute')
            ->willReturnCallback(function($name, $default = null) use ($attributes) {
                return array_key_exists($name, $attributes) ? $attributes[$name] : $default;
            });

        $this->expectException(ParameterResolutionException::class);
        $this->expectExceptionMessage('The request attribute [non_nullable_param] has null value but parameter \'non_nullable_param\' does not allow null');

        $this->resolver->resolve($parameter, $providedParams, []);
    }

    public function testResolveWithNullValueForNullableParameter(): void
    {
        $testFunction = function (#[RequestAttribute] ?string $nullable_param): void {};
        $reflection = new \ReflectionFunction($testFunction);
        $parameter = $reflection->getParameters()[0];

        $providedParams = RequestParameter::set([], $this->request);

        $attributes = ['nullable_param' => null];
        $this->request->method('getAttributes')->willReturn($attributes);
        $this->request->method('getAttribute')
            ->willReturnCallback(function($name, $default = null) use ($attributes) {
                return array_key_exists($name, $attributes) ? $attributes[$name] : $default;
            });

        $result = $this->resolver->resolve($parameter, $providedParams, []);

        $this->assertIsArray($result);
        $this->assertEquals(0, $result[0]); // position
        $this->assertNull($result[1]); // value should be null
    }

    public function testResolveWithDifferentValueTypes(): void
    {
        // Test with integer value
        $testFunction = function (#[RequestAttribute] int $integer_attr): void {};
        $reflection = new \ReflectionFunction($testFunction);
        $parameter = $reflection->getParameters()[0];

        $providedParams = RequestParameter::set([], $this->request);
        $expectedValue = 42;

        $attributes = ['integer_attr' => $expectedValue];
        $this->request->method('getAttributes')->willReturn($attributes);
        $this->request->method('getAttribute')
            ->willReturnCallback(function($name, $default = null) use ($attributes) {
                return $attributes[$name] ?? $default;
            });

        $result = $this->resolver->resolve($parameter, $providedParams, []);

        $this->assertIsArray($result);
        $this->assertEquals($expectedValue, $result[1]);
    }

    public function testResolveWithArrayValue(): void
    {
        $testFunction = function (#[RequestAttribute] array $array_attr): void {};
        $reflection = new \ReflectionFunction($testFunction);
        $parameter = $reflection->getParameters()[0];

        $providedParams = RequestParameter::set([], $this->request);
        $expectedValue = ['key' => 'value'];

        $attributes = ['array_attr' => $expectedValue];
        $this->request->method('getAttributes')->willReturn($attributes);
        $this->request->method('getAttribute')
            ->willReturnCallback(function($name, $default = null) use ($attributes) {
                return $attributes[$name] ?? $default;
            });

        $result = $this->resolver->resolve($parameter, $providedParams, []);

        $this->assertIsArray($result);
        $this->assertEquals($expectedValue, $result[1]);
    }

    public function testResolveWithObjectValue(): void
    {
        $testFunction = function (#[RequestAttribute] object $object_attr): void {};
        $reflection = new \ReflectionFunction($testFunction);
        $parameter = $reflection->getParameters()[0];

        $providedParams = RequestParameter::set([], $this->request);
        $expectedValue = new \stdClass();

        $attributes = ['object_attr' => $expectedValue];
        $this->request->method('getAttributes')->willReturn($attributes);
        $this->request->method('getAttribute')
            ->willReturnCallback(function($name, $default = null) use ($attributes) {
                return $attributes[$name] ?? $default;
            });

        $result = $this->resolver->resolve($parameter, $providedParams, []);

        $this->assertIsArray($result);
        $this->assertSame($expectedValue, $result[1]);
    }

    public function testResolveWithBooleanValue(): void
    {
        $testFunction = function (#[RequestAttribute] bool $boolean_attr): void {};
        $reflection = new \ReflectionFunction($testFunction);
        $parameter = $reflection->getParameters()[0];

        $providedParams = RequestParameter::set([], $this->request);
        $expectedValue = true;

        $attributes = ['boolean_attr' => $expectedValue];
        $this->request->method('getAttributes')->willReturn($attributes);
        $this->request->method('getAttribute')
            ->willReturnCallback(function($name, $default = null) use ($attributes) {
                return $attributes[$name] ?? $default;
            });

        $result = $this->resolver->resolve($parameter, $providedParams, []);

        $this->assertIsArray($result);
        $this->assertEquals($expectedValue, $result[1]);
    }

    public function testResolveWithFloatValue(): void
    {
        $testFunction = function (#[RequestAttribute] float $float_attr): void {};
        $reflection = new \ReflectionFunction($testFunction);
        $parameter = $reflection->getParameters()[0];

        $providedParams = RequestParameter::set([], $this->request);
        $expectedValue = 3.14;

        $attributes = ['float_attr' => $expectedValue];
        $this->request->method('getAttributes')->willReturn($attributes);
        $this->request->method('getAttribute')
            ->willReturnCallback(function($name, $default = null) use ($attributes) {
                return $attributes[$name] ?? $default;
            });

        $result = $this->resolver->resolve($parameter, $providedParams, []);

        $this->assertIsArray($result);
        $this->assertEquals($expectedValue, $result[1]);
    }

    public function testResolveWithBooleanFalseValue(): void
    {
        $testFunction = function (#[RequestAttribute] bool $boolean_attr): void {};
        $reflection = new \ReflectionFunction($testFunction);
        $parameter = $reflection->getParameters()[0];

        $providedParams = RequestParameter::set([], $this->request);
        $expectedValue = false;

        $attributes = ['boolean_attr' => $expectedValue];
        $this->request->method('getAttributes')->willReturn($attributes);
        $this->request->method('getAttribute')
            ->willReturnCallback(function($name, $default = null) use ($attributes) {
                return array_key_exists($name, $attributes) ? $attributes[$name] : $default;
            });

        $result = $this->resolver->resolve($parameter, $providedParams, []);

        $this->assertIsArray($result);
        $this->assertFalse($result[1]);
    }

    public function testResolveWithZeroValue(): void
    {
        $testFunction = function (#[RequestAttribute] int $zero_attr): void {};
        $reflection = new \ReflectionFunction($testFunction);
        $parameter = $reflection->getParameters()[0];

        $providedParams = RequestParameter::set([], $this->request);
        $expectedValue = 0;

        $attributes = ['zero_attr' => $expectedValue];
        $this->request->method('getAttributes')->willReturn($attributes);
        $this->request->method('getAttribute')
            ->willReturnCallback(function($name, $default = null) use ($attributes) {
                return array_key_exists($name, $attributes) ? $attributes[$name] : $default;
            });

        $result = $this->resolver->resolve($parameter, $providedParams, []);

        $this->assertIsArray($result);
        $this->assertEquals(0, $result[1]);
    }
}