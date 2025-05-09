<?php declare(strict_types=1);

namespace Antwerpes\Soap\Tests\Unit\Xml;

use Antwerpes\Soap\Tests\TestCase;
use Antwerpes\Soap\Xml\XMLSerializer;

class XmlSerializerTest extends TestCase
{
    protected $xml = <<<'XML'
        <?xml version="1.0"?>
        <SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">
            <SOAP-ENV:Body>
                <SOAP-ENV:prename>Code</SOAP-ENV:prename>
                <SOAP-ENV:lastname>dredd</SOAP-ENV:lastname>
            </SOAP-ENV:Body>
        </SOAP-ENV:Envelope>
        XML;
    protected $array = [
        'prename' => 'Code',
        'lastname' => 'dredd',
    ];

    public function test_array_to_soap_xml(): void
    {
        $soapXml = XMLSerializer::arrayToSoapXml($this->array);

        $this->assertXmlStringEqualsXmlString($this->xml, $soapXml);
    }
}
