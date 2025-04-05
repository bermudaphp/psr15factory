<?php

namespace Bermuda\MiddlewareFactory;

use RuntimeException;
use Throwable;

final class UnresolvableMiddlewareException extends RuntimeException
{
    public function __construct(
        public readonly mixed $middleware,
        ?string $message = null,
        ?Throwable $prev = null
    ) {
        if (!$message && is_string($middleware)) {
            $message = 'Unresolvable middleware: ' . $middleware;
        }

        parent::__construct($message ?? 'Unresolvable middleware', 0, $prev);
    }

    public function setBacktrace(array $backtrace): self
    {
        $this->file = $backtrace['file'];
        $this->line = $backtrace['line'];

        return $this;
    }

    /**
     * @param Throwable $e
     * @param $middleware
     * @return static
     */
    public static function fromPrev(Throwable $e, $middleware): self
    {
        return new self($middleware, sprintf('Code execution failed in file: %s on line: %s',
            $e->getFile(), $e->getLine()), $e);
    }

    /**
     * @param mixed $middleware
     * @return self
     */
    public static function makeFrom(mixed $middleware): self
    {
        if ($middleware instanceof \Closure) {
            return new self($middleware, 'Cannot create middleware from closure');
        }
        if (is_callable($middleware)) return new self($middleware, 'Cannot create middleware from callable: '.self::getTypeForCallable($middleware));
        if (is_object($middleware)) return new self($middleware, 'Cannot create middleware from object: ' . $middleware::class);
        if (is_string($middleware)) return new self($middleware, 'Cannot create middleware from string: ' . $middleware);

        return new self('Cannot create middleware', $middleware);
    }

    private static function getTypeForCallable(callable $any): string
    {
        if (is_object($any)) return $any::class;
        if (is_array($any)) return is_object($any[0]) ? $any[0]::class . "::$any[1]" : "$any[0]::$any[1]";

        return $any;
    }
}
