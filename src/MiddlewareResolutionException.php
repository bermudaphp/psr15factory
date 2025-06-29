<?php

namespace Bermuda\MiddlewareFactory;

use RuntimeException;
use Throwable;

/**
 * Exception thrown when a middleware cannot be resolved to a valid PSR-15 MiddlewareInterface.
 *
 * This exception provides detailed information about the middleware definition that failed
 * to resolve, including context-aware error messages and backtrace information to help
 * developers identify and fix resolution issues.
 */
class MiddlewareResolutionException extends RuntimeException implements MiddlewareResolutionExceptionInterface, BacktraceAwareInterface
{
    /**
     * Creates a new middleware resolution exception.
     *
     * @param mixed $middleware The middleware definition that could not be resolved
     * @param string $message Optional custom error message. If empty, generates a default message
     * @param Throwable|null $previous Previous exception in the chain, if any
     */
    public function __construct(
        public readonly mixed $middleware,
        string $message = "",
        ?Throwable $previous = null
    ) {
        if (empty($message)) {
            $message = $this->buildDefaultMessage($middleware);
        }
        parent::__construct($message, $previous?->getCode() ?? 0, $previous);
    }

    /**
     * Generates a context-aware error message based on the middleware type.
     *
     * The error message provides specific guidance depending on the type of middleware
     * that failed to resolve, helping developers understand what went wrong.
     *
     * @param mixed $middleware The middleware definition that failed to resolve
     * @return string Descriptive error message with troubleshooting hints
     */
    protected function buildDefaultMessage(mixed $middleware): string
    {
        return match(true) {
            is_string($middleware) => sprintf(
                "Failed to resolve middleware class '%s'. Ensure the class exists, implements MiddlewareInterface or RequestHandlerInterface, and is registered in the container.",
                $middleware
            ),
            is_object($middleware) && !is_callable($middleware) => sprintf(
                "Failed to resolve middleware object of type '%s'. The object must implement MiddlewareInterface or RequestHandlerInterface.",
                get_class($middleware)
            ),
            is_callable($middleware) => sprintf(
                "Failed to resolve callable middleware. Ensure the callable signature is compatible with PSR-15 middleware patterns or check parameter resolution dependencies."
            ),
            is_array($middleware) => sprintf(
                "Failed to resolve middleware array with %d elements. Ensure all array elements are valid middleware definitions (strings, callables, or middleware objects).",
                count($middleware)
            ),
            $middleware === null => "Cannot resolve null middleware. Middleware definition cannot be null.",
            default => sprintf(
                "Failed to resolve middleware of type '%s'. Supported types: string (class name), callable, MiddlewareInterface, RequestHandlerInterface, or iterable.",
                get_debug_type($middleware)
            )
        };
    }

    /**
     * Sets the backtrace information for better error location tracking.
     *
     * This method updates the exception's file and line information based on the
     * provided backtrace, making it easier to identify where the resolution failure occurred.
     *
     * @param array $backtrace Backtrace array from debug_backtrace()
     * @return self Returns the current instance for method chaining
     */
    public function setBacktrace(array $backtrace): self
    {
        if (isset($backtrace['file'], $backtrace['line'])) {
            $this->file = $backtrace['file'];
            $this->line = $backtrace['line'];
        }

        return $this;
    }

    /**
     * Creates a new exception instance wrapping a previous exception.
     *
     * This factory method is useful when a middleware resolution fails due to an
     * underlying error (e.g., dependency injection failure, class instantiation error).
     *
     * @param mixed $middleware The middleware definition that failed to resolve
     * @param Throwable $previous The underlying exception that caused the failure
     * @return self New exception instance with wrapped error context
     */
    public static function createFromPrev(mixed $middleware, Throwable $previous): self
    {
        $middlewareInfo = is_string($middleware)
            ? "middleware class '{$middleware}'"
            : sprintf("middleware of type '%s'", get_debug_type($middleware));

        $message = sprintf(
            "Failed to resolve %s due to underlying error: %s",
            $middlewareInfo,
            $previous->getMessage()
        );

        return new self($middleware, $message, $previous);
    }
}