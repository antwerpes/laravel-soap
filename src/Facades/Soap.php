<?php declare(strict_types=1);

namespace Antwerpes\Soap\Facades;

use Antwerpes\Soap\Client\Response;
use Antwerpes\Soap\Client\ResponseSequence;
use Antwerpes\Soap\SoapClient;
use Antwerpes\Soap\SoapFactory;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Facades\Facade;

/**
 * @method static SoapClient baseWsdl(string $wsdl)
 * @method static SoapClient stub(callable $callback)
 * @method static SoapClient buildClient(string $setup = '')
 * @method static SoapClient byConfig(string $setup = '')
 * @method static SoapClient withOptions(array $options)
 * @method static SoapClient withHeaders(array $options)
 * @method static SoapClient withGuzzleClientOptions(array $options)
 * @method static SoapClient withWsse(array $config)
 * @method static SoapClient withWsa()
 * @method static SoapClient withRemoveEmptyNodes()
 * @method static SoapClient withBasicAuth(string $username, string $password)
 * @method Response call(string $method, array $arguments = [])
 * @method static PromiseInterface response($body = null, $status = 200, array $headers = [])
 * @method static ResponseSequence sequence(array $responses = [])
 * @method static ResponseSequence fakeSequence(string $urlPattern = '*')
 * @method static SoapFactory fake($callback = null)
 * @method static assertSent(callable $callback)
 * @method static assertNotSent(callable $callback)
 * @method static assertActionCalled(string $action)
 * @method static assertNothingSent()
 * @method static assertSequencesAreEmpty()
 * @method static assertSentCount($count)
 */
class Soap extends Facade
{
    /**
     * {@inheritDoc}
     */
    protected static function getFacadeAccessor(): string
    {
        return SoapFactory::class;
    }
}
