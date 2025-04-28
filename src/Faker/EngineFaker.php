<?php declare(strict_types=1);

namespace Antwerpes\Soap\Faker;

use Antwerpes\Soap\Xml\XMLSerializer;
use Soap\Engine\Driver;
use Soap\Engine\Engine;
use Soap\Engine\HttpBinding\SoapRequest;
use Soap\Engine\Metadata\Metadata;
use Soap\Engine\Transport;
use Soap\ExtSoapEngine\ExtSoapOptions;

readonly class EngineFaker implements Engine
{
    public function __construct(
        private Driver $driver,
        private Transport $transport,
        private ExtSoapOptions $options,
    ) {}

    public function request(string $method, array $arguments)
    {
        $request = new SoapRequest(
            XMLSerializer::arrayToSoapXml($arguments),
            $this->options->getWsdl(),
            $method,
            $this->options->getOptions()['soap_version'] ?? SOAP_1_1,
        );
        $response = $this->transport->request($request);

        return json_decode($response->getPayload());
    }

    public function getMetadata(): Metadata
    {
        return $this->driver->getMetadata();
    }
}
