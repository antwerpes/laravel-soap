<?php declare(strict_types=1);

namespace Antwerpes\Soap\Xml;

use SimpleXMLElement;

class XMLSerializer
{
    /**
     * Return a valid SOAP Xml.
     */
    public static function arrayToSoapXml(array $array): string
    {
        $array = [
            'SOAP-ENV:Body' => $array,
        ];
        $xml = new SimpleXMLElement('<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"/>');
        self::addArrayToXml($array, $xml);

        return $xml->asXML();
    }

    public static function addArrayToXml(array $array, &$xml): void
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                if (is_int($key)) {
                    $key = 'node';
                }
                $label = $xml->addChild($key);
                self::addArrayToXml($value, $label);
            } else {
                $xml->addChild($key, $value);
            }
        }
    }
}
