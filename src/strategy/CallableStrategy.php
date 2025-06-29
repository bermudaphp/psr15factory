<?php

namespace Bermuda\MiddlewareFactory\Strategy;

use Bermuda\MiddlewareFactory\Adapter\CallableAdapter;
use Bermuda\DI\CallableExecutorInterface;
use Bermuda\DI\CallableResolverExceptionInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Bermuda\Reflection\Reflection;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Strategy for resolving callable middleware with automatic signature detection and adaptation.
 *
 * CallableStrategy adapts middleware defined as callables into PSR-15 compliant MiddlewareInterface
 * instances. It uses reflection to analyze the callable's signature and automatically determines
 * the most appropriate adaptation pattern based on the parameter types and count.
 *
 * This strategy supports three main callable middleware patterns:
 *
 * 1. **Single-pass middleware**: function(ServerRequestInterface $request, callable $next)
 *    - Two parameters where the second parameter is typed as 'callable'
 *    - Common in frameworks like Slim and ExpressJS-style middleware
 *    - The 'next' callable represents the continuation of the middleware pipeline
 *
 * 2. **Double-pass middleware**: function(ServerRequestInterface $request, ResponseInterface $response, callable $next)
 *    - Three parameters with specific type hints: request, response, and callable
 *    - Traditional pattern used in older PHP frameworks
 *    - Provides a base response object that can be modified
 *
 * 3. **Standard callable**: Any other callable signature
 *    - Wrapped in a generic CallableAdapter
 *    - Uses dependency injection for parameter resolution
 *    - Most flexible option supporting custom parameter patterns
 *
 * The strategy leverages reflection to inspect callable signatures at runtime, ensuring
 * that the most appropriate adapter is selected automatically without requiring explicit
 * configuration from the developer.
 *
 */
final class CallableStrategy implements StrategyInterface
{
    /**
     * Creates a new callable strategy with required dependencies.
     *
     * @param CallableExecutorInterface $executor Service responsible for resolving and executing callables
     * @param ResponseFactoryInterface $responseFactory Factory for creating PSR-7 response objects (used in double-pass)
     */
    public function __construct(
        private readonly CallableExecutorInterface $executor,
        private readonly ResponseFactoryInterface $responseFactory,
    ) {
    }

    /**
     * Creates a MiddlewareInterface instance from the given callable middleware definition.
     *
     * This method performs the following resolution process:
     * 1. Attempts to resolve the middleware definition into a callable using the executor
     * 2. Uses reflection to analyze the callable's parameter signature
     * 3. Determines the appropriate middleware pattern based on parameter count and types
     * 4. Creates and returns the corresponding adapter
     *
     * The signature analysis follows these rules:
     * - First parameter must be compatible with ServerRequestInterface
     * - Two parameters with second being 'callable' → Single-pass middleware
     * - Three parameters (request, ResponseInterface, callable) → Double-pass middleware
     * - All other signatures → Standard callable adapter with dependency injection
     *
     * @param mixed $middleware The middleware callable or middleware definition to resolve.
     *                         Can be a closure, array callable, string, or any resolvable definition.
     * @return MiddlewareInterface|null Returns the adapted middleware instance or null if resolution failed.
     *                                  Null return indicates this strategy cannot handle the provided definition.
     * @throws CallableResolverExceptionInterface When callable resolution fails due to invalid definitions.
     *
     * @example Single-pass middleware detection
     * ```php
     * $middleware = function(ServerRequestInterface $request, callable $next) {
     *     // Pre-processing logic
     *     $response = $next($request);
     *     // Post-processing logic
     *     return $response;
     * };
     * // Results in CallableAdapter::singlePassMiddleware()
     * ```
     *
     * @example Double-pass middleware detection
     * ```php
     * $middleware = function(ServerRequestInterface $request, ResponseInterface $response, callable $next) {
     *     // Middleware logic with base response
     *     return $next($request);
     * };
     * // Results in CallableAdapter::doublePassMiddleware()
     * ```
     */
    public function makeMiddleware(mixed $middleware): ?MiddlewareInterface
    {
        $callable = $this->executor->resolve($middleware);
        if (!$callable) {
            return null;
        }

        $reflector = Reflection::callable($callable);
        $parameters = $reflector->getParameters();
        $parameterCount = count($parameters);

        // Ensure first parameter is compatible with ServerRequestInterface
        if ($parameterCount > 0 && $this->isParameterTypeCompatible($parameters[0], ServerRequestInterface::class)) {

            // Check for single-pass middleware pattern
            if ($this->isSinglePassMiddleware($parameterCount, $parameters)) {
                return CallableAdapter::singlePassMiddleware($middleware, $this->executor);
            }

            // Check for double-pass middleware pattern
            if ($this->isDoublePassMiddleware($parameterCount, $parameters)) {
                return CallableAdapter::doublePassMiddleware($middleware, $this->executor, $this->responseFactory);
            }
        }

        // Fall back to standard callable adapter with dependency injection
        return new CallableAdapter($callable, $this->executor);
    }

    /**
     * Determines if the callable signature matches single-pass middleware pattern.
     *
     * Single-pass middleware is characterized by:
     * - Exactly 2 parameters
     * - Second parameter typed as 'callable'
     *
     * @param int $parameterCount Total number of parameters in the callable
     * @param array<ReflectionParameter> $parameters Array of reflection parameters
     * @return bool True if the signature matches single-pass pattern
     */
    private function isSinglePassMiddleware(int $parameterCount, array $parameters): bool
    {
        return $parameterCount === 2 && $this->declaresCallable($parameters[1]);
    }

    /**
     * Determines if the callable signature matches double-pass middleware pattern.
     *
     * Double-pass middleware is characterized by:
     * - Exactly 3 parameters
     * - Second parameter compatible with ResponseInterface
     * - Third parameter typed as 'callable'
     *
     * @param int $parameterCount Total number of parameters in the callable
     * @param array<ReflectionParameter> $parameters Array of reflection parameters
     * @return bool True if the signature matches double-pass pattern
     */
    private function isDoublePassMiddleware(int $parameterCount, array $parameters): bool
    {
        return $parameterCount === 3
            && $this->isParameterTypeCompatible($parameters[1], ResponseInterface::class)
            && $this->declaresCallable($parameters[2]);
    }

    /**
     * Checks if a parameter type is compatible with the specified class or interface.
     *
     * This method performs type compatibility checking that includes:
     * - Exact type name matching
     * - Inheritance hierarchy checking (is_subclass_of)
     * - Interface implementation checking
     *
     * Only named types are supported; union types and other complex type declarations
     * are not considered compatible to maintain simplicity and predictability.
     *
     * @param ReflectionParameter $parameter The parameter to check
     * @param string $expectedType The fully-qualified class or interface name to check against
     * @return bool True if the parameter type is compatible with the expected type
     *
     * @example Type compatibility examples
     * ```php
     * // Direct match
     * isParameterTypeCompatible($param, ServerRequestInterface::class) // true for ServerRequestInterface
     *
     * // Inheritance
     * isParameterTypeCompatible($param, ResponseInterface::class) // true for JsonResponse extends ResponseInterface
     *
     * // Interface implementation
     * isParameterTypeCompatible($param, MiddlewareInterface::class) // true for any middleware implementation
     * ```
     */
    private function isParameterTypeCompatible(ReflectionParameter $parameter, string $expectedType): bool
    {
        $reflectionType = $parameter->getType();

        // Only handle named types (no union, intersection, etc.)
        if (!$reflectionType instanceof ReflectionNamedType) {
            return false;
        }

        $actualTypeName = $reflectionType->getName();

        // Check for exact match or inheritance/interface implementation
        return $actualTypeName === $expectedType || is_subclass_of($actualTypeName, $expectedType);
    }

    /**
     * Determines if a parameter is declared with 'callable' type.
     *
     * This method handles both simple named types and union types that include 'callable'.
     * It's essential for detecting middleware patterns that use callable parameters for
     * representing the next middleware in the chain.
     *
     * For union types (e.g., callable|null), the method checks if any of the union
     * members is the 'callable' type.
     *
     * @param ReflectionParameter $parameter The parameter to inspect
     * @return bool True if the parameter is typed as callable (or union containing callable)
     *
     * @example Callable type detection
     * ```php
     * function middleware(ServerRequestInterface $request, callable $next) {}
     * // declaresCallable($nextParameter) returns true
     *
     * function middleware(ServerRequestInterface $request, callable|null $next) {}
     * // declaresCallable($nextParameter) returns true (union with callable)
     *
     * function middleware(ServerRequestInterface $request, \Closure $next) {}
     * // declaresCallable($nextParameter) returns false (specific closure type)
     * ```
     */
    private function declaresCallable(ReflectionParameter $parameter): bool
    {
        $reflectionType = $parameter->getType();

        // No type declaration
        if (!$reflectionType) {
            return false;
        }

        // Handle union types (e.g., callable|null)
        if ($reflectionType instanceof \ReflectionUnionType) {
            $unionTypes = $reflectionType->getTypes();

            // Check if any union member is 'callable'
            foreach ($unionTypes as $type) {
                if ($type instanceof ReflectionNamedType && $type->getName() === 'callable') {
                    return true;
                }
            }

            return false;
        }

        // Handle simple named types
        if ($reflectionType instanceof ReflectionNamedType) {
            return $reflectionType->getName() === 'callable';
        }

        // Other type kinds (intersection, etc.) are not supported
        return false;
    }

    /**
     * Factory method for creating CallableStrategy from a PSR-11 container.
     *
     * This static factory method provides a convenient way to instantiate the strategy
     * when using dependency injection containers. It retrieves the required dependencies
     * from the container and constructs a properly configured CallableStrategy instance.
     *
     * Required container services:
     * - CallableExecutorInterface: For resolving and executing callables
     * - ResponseFactoryInterface: For creating response objects in double-pass middleware
     *
     * @param ContainerInterface $container The PSR-11 container containing required dependencies
     * @return CallableStrategy A fully configured CallableStrategy instance
     * @throws ContainerExceptionInterface If there is an error while retrieving a service from the container
     * @throws NotFoundExceptionInterface If a required dependency service is not registered in the container
     *
     * @example Container-based instantiation
     * ```php
     * // In your container configuration
     * $container->set(CallableStrategy::class, function(ContainerInterface $c) {
     *     return CallableStrategy::createFromContainer($c);
     * });
     *
     * // Or using auto-wiring
     * $strategy = CallableStrategy::createFromContainer($container);
     * $factory->addStrategy($strategy);
     * ```
     */
    public static function createFromContainer(ContainerInterface $container): CallableStrategy
    {
        return new static(
            $container->get(CallableExecutorInterface::class),
            $container->get(ResponseFactoryInterface::class)
        );
    }
}