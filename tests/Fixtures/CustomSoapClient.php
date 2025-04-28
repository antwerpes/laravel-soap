<?php declare(strict_types=1);

namespace Antwerpes\Soap\Tests\Fixtures;

use Antwerpes\Soap\SoapClient;

class CustomSoapClient extends SoapClient
{
    public function buildClient(string $setup = ''): static
    {
        $this->baseWsdl(__DIR__.'/Wsdl/weather.wsdl');
        $this->withGuzzleClientOptions([
            'handler' => $this->buildHandlerStack(),
        ]);
        $this->refreshEngine();
        $this->isClientBuilt = true;

        return $this;
    }
}
