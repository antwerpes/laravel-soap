<?php declare(strict_types=1);

namespace Antwerpes\Soap\Tests\Unit\Client;

use Antwerpes\Soap\Client\Response;
use Antwerpes\Soap\Tests\TestCase;
use GuzzleHttp\Psr7\Response as Psr7Response;

class ResponseTest extends TestCase
{
    public function test_body_from_soap_error(): void
    {
        $xml = file_get_contents(dirname(__DIR__, 2).'/Fixtures/Responses/SoapFault.xml');
        $soapResponse = new Response(new Psr7Response(400, [], $xml));
        $this->assertSame('Message was not SOAP 1.1 compliant', $soapResponse->body());
    }
}
