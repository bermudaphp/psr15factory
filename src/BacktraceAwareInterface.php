<?php

namespace Bermuda\MiddlewareFactory;

interface BacktraceAwareInterface
{
    /**
     * Sets the backtrace information.
     *
     * The $backtrace parameter should be the result of calling debug_backtrace(),
     * and is expected to be an array containing the keys 'file' and 'line'.
     *
     * @param array $backtrace The backtrace array obtained from debug_backtrace().
     * @return self Returns the current instance for chaining.
     */
    public function setBacktrace(array $backtrace): self;
}