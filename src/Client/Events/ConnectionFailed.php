<?php declare(strict_types=1);

namespace Antwerpes\Soap\Client\Events;

use Antwerpes\Soap\Client\Request;

class ConnectionFailed
{
    public function __construct(
        public Request $request,
    ) {}
}
