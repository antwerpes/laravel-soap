<?php declare(strict_types=1);

namespace Antwerpes\Soap\Client\Events;

use Antwerpes\Soap\Client\Request;
use Antwerpes\Soap\Client\Response;

class ResponseReceived
{
    public function __construct(
        public Request $request,
        public Response $response,
    ) {}
}
