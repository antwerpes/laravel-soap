<?php declare(strict_types=1);

namespace Antwerpes\Soap\Exceptions;

use Antwerpes\Soap\Client\Response;
use Exception;

class RequestException extends Exception
{
    public Response $response;

    public function __construct(Response $response)
    {
        parent::__construct(
            "Soap request error with status code {$response->status()}:\n {$response->body()}",
            $response->status(),
        );

        $this->response = $response;
    }
}
