<?php

namespace Bermuda\MiddlewareFactory;

use RuntimeException;
use Throwable;

/**
 * MiddlewareResolutionException is thrown when a middleware cannot be resolved.
 *
 * This exception class implements MiddlewareResolutionExceptionInterface, which requires
 * that the original middleware definition causing the error is stored. If no explicit
 * message is provided, a default message is generated based on the provided middleware.
 */
class MiddlewareResolutionException extends RuntimeException implements MiddlewareResolutionExceptionInterface, BacktraceAwareInterface
{
    /**
     * Constructor.
     *
     * If the $message parameter is empty, a default message is generated based on the provided middleware.
     *
     * @param mixed $middleware The middleware definition that could not be resolved.
     * @param string $message Optional exception message. If empty, a default message is generated.
     * @param \Throwable|null $previous Previous exception for chaining, if any.
     */
    public function __construct(
        public readonly mixed $middleware,
        string $message = "",
        ?\Throwable $previous = null
    ) {
        if (empty($message)) {
            $message = $this->buildDefaultMessage($middleware);
        }
        parent::__construct($message, $previous?->getCode() ?? 0, $previous);
    }

    /**
     * Builds a default error message based on the provided middleware.
     *
     * The default message varies depending on the type of the middleware:
     * - If it's a string, the string is appended to the message.
     * - If it's an object, the class name is used.
     * - If it's an array, a generic note is provided.
     * - Otherwise, a var_exported representation is used.
     *
     * @param mixed $middleware The middleware definition that could not be resolved.
     * @return string The generated error message.
     */
    protected function buildDefaultMessage(mixed $middleware): string
    {
        if (is_string($middleware)) {
            return "Failed to resolve middleware: {$middleware}";
        }
        if (is_object($middleware)) {
            return "Failed to resolve middleware of type: " . get_class($middleware);
        }
        if (is_array($middleware)) {
            return "Failed to resolve middleware defined as an array.";
        }

        if (is_callable($middleware)) {
            return "Failed to resolve middleware defined as an callable.";
        }

        return "Failed to resolve middleware";
    }

    /**
     * Sets the backtrace information.
     *
     * The $backtrace parameter should be the result of calling debug_backtrace(),
     * and is expected to be an array containing the keys 'file' and 'line'.
     *
     * @param array $backtrace The backtrace array obtained from debug_backtrace().
     * @return self Returns the current instance for chaining.
     */
    public function setBacktrace(array $backtrace): self
    {
        $this->file = $backtrace['file'];
        $this->line = $backtrace['line'];

        return $this;
    }

    /**
     * Creates a new MiddlewareResolutionException instance based on a previous exception.
     *
     * This static factory method wraps a previous exception, appending its message to a default error message.
     *
     * @param mixed $middleware The middleware definition that could not be resolved.
     * @param \Throwable $previous The previous exception that triggered this error.
     * @return self Returns a new instance of MiddlewareResolutionException.
     */
    public static function createFromPrev(mixed $middleware, \Throwable $previous): self
    {
        $message = "Failed to resolve middleware due to previous error: " . $previous->getMessage();
        return new self($middleware, $message, $previous);
    }
}
