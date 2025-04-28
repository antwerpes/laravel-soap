<?php declare(strict_types=1);

namespace Antwerpes\Soap\Driver\ExtSoap;

use Antwerpes\Soap\Faker\EngineFaker;
use Soap\Engine\SimpleEngine;
use Soap\Engine\Transport;
use Soap\ExtSoapEngine\ExtSoapDriver;
use Soap\ExtSoapEngine\ExtSoapOptions;

class ExtSoapEngineFactory
{
    public static function fromOptionsWithHandler(
        ExtSoapOptions $options,
        Transport $transport,
        $withMocking = false,
    ): EngineFaker|SimpleEngine {
        $driver = ExtSoapDriver::createFromOptions($options);

        return $withMocking
            ? new EngineFaker($driver, $transport, $options)
            : new SimpleEngine($driver, $transport);
    }
}
