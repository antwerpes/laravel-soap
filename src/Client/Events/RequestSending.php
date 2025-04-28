<?php declare(strict_types=1);

namespace Antwerpes\Soap\Client\Events;

use Antwerpes\Soap\Client\Request;

class RequestSending
{
    public function __construct(
        public Request $request,
    ) {}
}
