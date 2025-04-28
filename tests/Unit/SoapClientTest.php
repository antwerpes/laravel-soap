<?php declare(strict_types=1);

namespace Antwerpes\Soap\Tests\Unit;

use Antwerpes\Soap\Client\Events\ConnectionFailed;
use Antwerpes\Soap\Client\Events\RequestSending;
use Antwerpes\Soap\Client\Events\ResponseReceived;
use Antwerpes\Soap\Client\Request;
use Antwerpes\Soap\Client\Response;
use Antwerpes\Soap\Facades\Soap;
use Antwerpes\Soap\SoapClient;
use Antwerpes\Soap\SoapFactory;
use Antwerpes\Soap\Tests\Fixtures\CustomSoapClient;
use Antwerpes\Soap\Tests\TestCase;
use GuzzleHttp\RedirectMiddleware;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use JetBrains\PhpStorm\ArrayShape;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

class SoapClientTest extends TestCase
{
    #[ArrayShape([
        'without_fake_array' => 'array',
        'with_fake_array_wrong_method' => 'array',
        'with_fake_array' => 'array',
        'with_fake_string' => 'array',
    ])]
    public static function soapActionProvider(): array
    {
        $fakeResponse = [
            'GetWeatherInformation' => [
                'Response_Data' => [
                    'Users' => [
                        [
                            'name' => 'test',
                            'field' => 'bla',
                        ],
                    ],
                ],
            ],
            'GetCityForecastByZIP' => 'Test',
        ];

        return [
            'without_fake_array' => ['GetCityWeatherByZIP', null, null],
            'with_fake_array_wrong_method' => ['GetCityWeatherByZIP', $fakeResponse, null],
            'with_fake_array' => ['GetWeatherInformation', $fakeResponse, $fakeResponse['GetWeatherInformation']],
            'with_fake_string' => ['GetCityForecastByZIP', $fakeResponse, ['response' => 'Test']],
        ];
    }

    public static function soapHeaderProvider(): array
    {
        $header = [
            'test' => 'application/soap+xml; charset="utf-8"',
        ];

        return [
            'without_header' => [[], ''],
            'with_header' => [$header, $header['test']],
        ];
    }

    /**
     * Test that a "fake" terminal returns an instance of BuilderFake.
     */
    public function test_simple_call(): void
    {
        Soap::fake();
        Event::fake();
        Soap::assertNothingSent();
        $response = Soap::baseWsdl(dirname(__DIR__, 1).'/Fixtures/Wsdl/weather.wsdl')
            ->call('GetWeatherInformation');
        $this->assertTrue($response->ok());
        Soap::assertSent(fn (Request $request) => $request->action() === 'GetWeatherInformation');
        Soap::assertNotSent(fn (Request $request) => $request->action() === 'GetCityWeatherByZIPSoapOut');
        Soap::assertActionCalled('GetWeatherInformation');
    }

    public function test_magic_call_by_config(): void
    {
        Soap::fake();
        Event::fake();
        $response = Soap::buildClient('laravel_soap')->GetWeatherInformation();
        $this->assertTrue($response->ok());
    }

    public function test_wsse_with_wsa_call(): void
    {
        Soap::fake();
        $client = Soap::baseWsdl(dirname(__DIR__, 1).'/Fixtures/Wsdl/weather.wsdl')->withWsse([
            'userTokenName' => 'Test',
            'userTokenPassword' => 'passwordTest',
            'mustUnderstand' => false,
        ])->withWsa();
        $response = $client->GetWeatherInformation();
        Soap::assertSent(fn (Request $request) => ! Str::contains($request->xmlContent(), 'mustUnderstand'));
        $this->assertTrue($response->ok());
    }

    public function test_wsse_with_wsa2005_call(): void
    {
        Soap::fake();
        $client = Soap::baseWsdl(dirname(__DIR__, 1).'/Fixtures/Wsdl/weather.wsdl')->withWsse([
            'userTokenName' => 'Test',
            'userTokenPassword' => 'passwordTest',
            'mustUnderstand' => false,
        ])->withWsa2005();
        $response = $client->GetWeatherInformation();
        Soap::assertSent(fn (Request $request) => ! Str::contains($request->xmlContent(), 'mustUnderstand'));
        $this->assertTrue($response->ok());
    }

    public function test_array_access_response(): void
    {
        Soap::fakeSequence()->push('test');
        Event::fake();
        $response = Soap::buildClient('laravel_soap')->GetWeatherInformation()['response'];
        $this->assertSame('test', $response);
    }

    public function test_request_with_arguments(): void
    {
        Soap::fake();
        Event::fake();

        $arguments = [
            'prename' => 'Corona',
            'lastname' => 'Pandemic',
        ];

        /** @var Response $response */
        $response = Soap::buildClient('laravel_soap')->Submit_User($arguments);

        Event::assertDispatched(RequestSending::class);
        Event::assertDispatched(ResponseReceived::class);
        $this->assertTrue($response->ok());
        Soap::assertSent(fn (Request $request) => $request->arguments() === $arguments
                && $request->action() === 'Submit_User');
    }

    public function test_sequence_fake(): void
    {
        $responseFake = ['user' => 'test'];
        $responseFake2 = ['user' => 'test2'];
        Event::fake();
        Soap::fakeSequence()
            ->push($responseFake)
            ->whenEmpty(Soap::response($responseFake2));
        $client = Soap::buildClient('laravel_soap');
        $response = $client->Get_User();
        $response2 = $client->Get_User();
        $response3 = $client->Get_User();
        $this->assertTrue($response->ok());
        $this->assertSame($responseFake, $response->json());
        $this->assertSame($responseFake2, $response2->json());
        $this->assertSame($responseFake2, $response3->json());

        Soap::assertSentCount(3);
    }

    #[DataProvider('soapActionProvider')]
    public function test_soap_fake($action, $fake, $expected): void
    {
        $fake = collect($fake)->map(fn ($item) => Soap::response($item))->all();

        Soap::fake($fake);
        Event::fake();
        Event::assertNotDispatched(RequestSending::class);
        Event::assertNotDispatched(ResponseReceived::class);
        $response = Soap::baseWsdl(dirname(__DIR__, 1).'/Fixtures/Wsdl/weather.wsdl')
            ->call($action);
        Event::assertDispatched(RequestSending::class);
        Event::assertDispatched(ResponseReceived::class);
        Event::assertNotDispatched(ConnectionFailed::class);
        $this->assertSame($expected, $response->json());
    }

    public function test_soap_options(): void
    {
        Soap::fake();
        Event::fake();
        $client = Soap::withOptions(['soap_version' => SOAP_1_2])
            ->baseWsdl(dirname(__DIR__, 1).'/Fixtures/Wsdl/weather.wsdl');
        $response = $client->call('GetWeatherInformation');
        $this->assertTrue($response->ok());
        Soap::assertSent(fn (Request $request) => Str::contains(
            $request->getRequest()->getHeaderLine('Content-Type'),
            'application/soap+xml; charset="utf-8"',
        ));
        Soap::assertActionCalled('GetWeatherInformation');
    }

    #[Group('real')]
    public function test_real_soap_call(): void
    {
        $this->markTestSkipped('Real Soap Call Testing. Comment the line out for testing');
        // location has to be set because the wsdl has a wrong location declaration
        $client = Soap::baseWsdl('https://www.w3schools.com/xml/tempconvert.asmx?wsdl')
            ->withOptions([
                'soap_version' => SOAP_1_2,
                'location' => 'https://www.w3schools.com/xml/tempconvert.asmx?wsdl',
            ]);
        $result = $client->call('FahrenheitToCelsius', [
            'Fahrenheit' => 75,
        ]);
        $this->assertArrayHasKey('FahrenheitToCelsiusResult', $result->json());

        $result = $client->FahrenheitToCelsius([
            'Fahrenheit' => 75,
        ]);
        $this->assertArrayHasKey('FahrenheitToCelsiusResult', $result->json());
    }

    #[DataProvider('soapHeaderProvider')]
    public function test_soap_with_different_headers($header, $exspected): void
    {
        Soap::fake();
        Event::fake();
        $client = Soap::withHeaders($header)->baseWsdl(dirname(__DIR__, 1).'/Fixtures/Wsdl/weather.wsdl');
        $response = $client->call('GetWeatherInformation');
        Soap::assertSent(fn (Request $request) => $request->getRequest()->getHeaderLine('test') === $exspected);
        $this->assertTrue($response->ok());
        Soap::assertActionCalled('GetWeatherInformation');
    }

    public function test_arguments_can_be_called_twice(): void
    {
        Soap::fake();
        Event::fake();
        Soap::assertNothingSent();
        $response = Soap::baseWsdl(dirname(__DIR__, 1).'/Fixtures/Wsdl/weather.wsdl')
            ->call('GetWeatherInformation');
        $this->assertTrue($response->ok());
        Soap::assertSent(fn (Request $request) => $request->arguments() === $request->arguments());
    }

    public function test_soap_client_class_may_be_customized(): void
    {
        Soap::fake();
        Event::fake();
        $client = Soap::buildClient('laravel_soap');
        $this->assertInstanceOf(SoapClient::class, $client);
        SoapFactory::useClientClass(CustomSoapClient::class);
        $client = Soap::buildClient('laravel_soap');
        $this->assertInstanceOf(CustomSoapClient::class, $client);
    }

    public function test_handler_options(): void
    {
        Soap::fake();
        Event::fake();
        $client = Soap::baseWsdl(dirname(__DIR__, 1).'/Fixtures/Wsdl/weather.wsdl');
        $response = $client->call('GetWeatherInformation');
        $this->assertTrue($response->ok());
        $this->assertTrue($client->getClient()->getConfig()['verify']);
        $client = $client->withGuzzleClientOptions([
            'allow_redirects' => RedirectMiddleware::$defaultSettings,
            'http_errors' => true,
            'decode_content' => true,
            'verify' => false,
            'cookies' => false,
            'idn_conversion' => false,
        ]);
        $response = $client->call('GetWeatherInformation');
        $this->assertTrue($response->ok());
        $this->assertFalse($client->getClient()->getConfig()['verify']);
    }

    protected function setUp(): void
    {
        parent::setUp();
    }
}
