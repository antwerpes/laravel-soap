<?php declare(strict_types=1);

namespace Antwerpes\Soap\Exceptions;

use RuntimeException;
use Throwable;

class SoapException extends RuntimeException
{
    public static function fromThrowable(Throwable $throwable): self
    {
        return new self($throwable->getMessage(), $throwable->getCode(), $throwable);
    }
}
