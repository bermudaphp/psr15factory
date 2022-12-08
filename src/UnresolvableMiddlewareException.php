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
    public function __construct(?string $message = null, public readonly mixed $middleware = null, ?Throwable $prev = null)
    {
        if (!$message && is_string($middleware)) {
            $message = 'Unresolvable middleware: ' . $middleware;
        }

        parent::__construct($message ?? 'Unresolvable middleware', 0, $prev);
    }

    /**
     * @param UnresolvableMiddlewareException $e
     * @param array $backtrace
     * @return void
     */
    public static function reThrow(UnresolvableMiddlewareException $e, array $backtrace): void
    {
        $self = new self($e->getMessage(), $e->getMiddleware(), $e->getPrevious());

        $self->file = $backtrace['file'];
        $self->line = $backtrace['line'];

        throw $self;
    }

    /**
     * @return mixed|null
     */
    public function getMiddleware()
    {
        return $this->middleware;
    }

    /**
     * @param Throwable $e
     * @param $middleware
     * @return static
     */
    public static function fromPrevious(Throwable $e, $middleware): self
    {
        return new self(
            sprintf('Code execution failed in file: %s on line: %s',
                $e->getFile(), $e->getLine()),
            $middleware, $e);
    }

    /**
     * @param $any
     * @return static
     */
    public static function notCreatable($any): self
    {
        $type = Type::gettype($any, Type::objectAsClass);

        if ($type == Type::callable) {
            $type = static::getTypeForCallable($any);
        }

        return new self('Cannot create middleware for this type: ' . $type, $any);
    }

    /**
     * @param callable $any
     * @return string
     * @throws \ReflectionException
     */
    private static function getTypeForCallable(callable $any): string
    {
        if (is_object($any)) {
            return get_class($any);
        }

        if (is_array($any)) {
            return (new ReflectionMethod($any[0], $any[1]))->getName();
        }

        if (str_contains($any, '::')) {
            return (new ReflectionMethod($any))->getName();
        }

        return (new ReflectionFunction($any))->getName();
    }

    /**
     * @param callable $any
     * @param string $returnType
     * @return static
     */
    public static function invalidReturnType(callable $any, string $returnType): self
    {
        return new self(
            sprintf('Callable middleware should return an %s or %s. Returned: %s',
                ResponseInterface::class, MiddlewareInterface::class, $returnType),
            $any
        );
    }
}
