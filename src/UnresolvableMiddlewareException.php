<?php

namespace Bermuda\MiddlewareFactory;

use Bermuda\CheckType\Type;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use ReflectionFunction;
use ReflectionMethod;
use RuntimeException;
use Throwable;

final class UnresolvableMiddlewareException extends RuntimeException
{
    private $middleware;

    public function __construct(?string $message = null, $middleware = null, ?Throwable $prev = null)
    {
        $this->middleware = $middleware;

        if (!$message && is_string($middleware)) {
            $message = 'Unresolvable middleware: ' . $middleware;
        }

        parent::__construct($message ?? 'Unresolvable middleware', 0, $prev);
    }

    public static function reThrow(UnresolvableMiddlewareException $e, array $backtrace): void
    {
        $self = new self($e->getMessage(), $e->getMiddleware(), $e->getPrevious());

        $self->file = $backtrace['file'];
        $self->line = $backtrace['line'];

        throw $self;
    }

    public function getMiddleware()
    {
        return $this->middleware;
    }

    public static function fromPrevious(Throwable $e, $middleware): self
    {
        return new self(
            sprintf('Code execution failed in file: %s on line: %s',
                $e->getFile(), $e->getLine()),
            $middleware, $e);
    }

    public static function notCreatable($any): self
    {
        $type = Type::gettype($any, Type::objectAsClass);

        if ($type == Type::callable) {
            $type = static::getTypeForCallable($any);
        }

        return new self('Cannot create middleware for this type: ' . $type, $any);
    }

    private static function getTypeForCallable(callable $any): string
    {
        if (is_object($any)) {
            return get_class($any);
        }

        if (is_array($any)) {
            return (new ReflectionMethod($any[0], $any[1]))->getName();
        }

        if (strpos($any, '::') !== false) {
            return (new ReflectionMethod($any))->getName();
        }

        return (new ReflectionFunction($any))->getName();
    }

    public static function invalidReturnType(callable $any, string $returnType): self
    {
        return new self(
            sprintf('Callable middleware should return an %s or %s. Returned: %s',
                ResponseInterface::class, MiddlewareInterface::class, $returnType),
            $any
        );
    }
}
