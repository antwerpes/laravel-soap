<?php declare(strict_types=1);

namespace Antwerpes\Soap;

use Antwerpes\Soap\Client\Events\ConnectionFailed;
use Antwerpes\Soap\Client\Events\RequestSending;
use Antwerpes\Soap\Client\Events\ResponseReceived;
use Antwerpes\Soap\Client\Request;
use Antwerpes\Soap\Client\Response;
use Antwerpes\Soap\Driver\ExtSoap\ExtSoapEngineFactory;
use Antwerpes\Soap\Exceptions\NotFoundConfigurationException;
use Antwerpes\Soap\Exceptions\SoapException;
use Antwerpes\Soap\Middleware\WsseMiddleware;
use Closure;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as Psr7Response;
use GuzzleHttp\TransferStats;
use Http\Client\Common\PluginClient;
use Http\Client\Exception\HttpException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;
use Phpro\SoapClient\Type\ResultInterface;
use Phpro\SoapClient\Type\ResultProviderInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Soap\Engine\Engine;
use Soap\Engine\Transport;
use Soap\ExtSoapEngine\AbusedClient;
use Soap\ExtSoapEngine\ExtSoapOptions;
use Soap\ExtSoapEngine\Transport\TraceableTransport;
use Soap\ExtSoapEngine\Wsdl\WsdlProvider;
use Soap\Psr18Transport\Middleware\RemoveEmptyNodesMiddleware;
use Soap\Psr18Transport\Psr18Transport;
use Soap\Psr18Transport\Wsdl\Psr18Loader;
use Soap\Psr18WsseMiddleware\WsaMiddleware;
use Soap\Psr18WsseMiddleware\WsaMiddleware2005;
use Soap\Wsdl\Loader\FlatteningLoader;
use Soap\Wsdl\Loader\StreamWrapperLoader;
use SoapFault;

class SoapClient
{
    use Conditionable;
    use Macroable {
        __call as macroCall;
    }
    protected ClientInterface $client;
    protected PluginClient $pluginClient;
    protected Engine $engine;
    protected array $options = [];
    protected ExtSoapOptions $extSoapOptions;
    protected TraceableTransport|Transport $transport;
    protected array $guzzleClientOptions = [];

    /** The transfer stats for the request. */
    protected ?TransferStats $transferStats = null;
    protected string $wsdl = '';
    protected bool $isClientBuilt = false;
    protected array $middlewares = [];
    protected FlatteningLoader|WsdlProvider $wsdlProvider;
    protected array $cookies = [];

    /** The callbacks that should execute before the request is sent. */
    protected Collection $beforeSendingCallbacks;

    /** The stub callables that will handle requests. */
    protected ?Collection $stubCallbacks;

    /** The request object, if a request has been made. */
    protected ?Request $request = null;

    public function __construct(
        protected ?SoapFactory $factory = null,
    ) {
        $this->client = new Client($this->guzzleClientOptions);
        $this->pluginClient = new PluginClient($this->client, $this->middlewares);
        $this->wsdlProvider = new FlatteningLoader(Psr18Loader::createForClient($this->pluginClient));
        $this->beforeSendingCallbacks = collect(
            [
                function (Request $request, array $options, SoapClient $soapClient): void {
                    $soapClient->request = $request;
                    $soapClient->cookies = Arr::wrap($options['cookies']);

                    $soapClient->dispatchRequestSendingEvent();
                }],
        );
    }

    public function refreshWsdlProvider(): static
    {
        $this->wsdlProvider = new FlatteningLoader(Psr18Loader::createForClient($this->pluginClient));

        return $this;
    }

    public function refreshPluginClient(): static
    {
        $this->pluginClient = new PluginClient($this->client, $this->middlewares);

        return $this;
    }

    public function getPluginClient(): PluginClient
    {
        return $this->pluginClient;
    }

    /**
     * Add the given headers to the request.
     */
    public function withHeaders(array $headers): static
    {
        return $this->withGuzzleClientOptions(array_merge_recursive($this->options, [
            'headers' => $headers,
        ]));
    }

    public function getTransport(): TraceableTransport|Transport
    {
        return $this->transport;
    }

    public function getClient(): Client|ClientInterface
    {
        return $this->client;
    }

    public function withGuzzleClientOptions(array ...$options): static
    {
        $this->guzzleClientOptions = array_merge_recursive($this->guzzleClientOptions, ...$options);
        $this->client = new Client($this->guzzleClientOptions);

        return $this;
    }

    public function getEngine(): Engine
    {
        return $this->engine;
    }

    public function withRemoveEmptyNodes(): static
    {
        $this->middlewares = array_merge_recursive($this->middlewares, [new RemoveEmptyNodesMiddleware]);

        return $this;
    }

    public function withBasicAuth(string $username, string $password): static
    {
        $this->withHeaders([
            'Authorization' => sprintf('Basic %s', base64_encode(sprintf('%s:%s', $username, $password))),
        ]);

        return $this;
    }

    public function withWsa(): static
    {
        $this->middlewares = array_merge_recursive($this->middlewares, [new WsaMiddleware]);

        return $this;
    }

    public function withWsa2005(): static
    {
        $this->middlewares = array_merge_recursive($this->middlewares, [new WsaMiddleware2005]);

        return $this;
    }

    public function withWsse(array $options): static
    {
        $this->middlewares = array_merge_recursive($this->middlewares, [new WsseMiddleware($options)]);

        return $this;
    }

    /**
     * Merge new options into the client.
     */
    public function withOptions(array $options): static
    {
        $this->options = array_merge_recursive($this->options, $options);

        return $this;
    }

    /**
     * Make it possible to debug the last request.
     */
    public function debugLastSoapRequest(): array
    {
        if ($this->transport instanceof TraceableTransport) {
            $lastRequestInfo = $this->transport->collectLastRequestInfo();

            return [
                'request' => [
                    'headers' => mb_trim($lastRequestInfo->getLastRequestHeaders()),
                    'body' => $lastRequestInfo->getLastRequest(),
                ],
                'response' => [
                    'headers' => mb_trim($lastRequestInfo->getLastResponseHeaders()),
                    'body' => $lastRequestInfo->getLastResponse(),
                ],
            ];
        }

        return [];
    }

    public function call(string $method, array|Validator $arguments = []): Response
    {
        try {
            if (! $this->isClientBuilt) {
                $this->buildClient();
            }
            $this->refreshEngine();

            if ($arguments instanceof Validator) {
                if ($arguments->fails()) {
                    return $this->failedValidation($arguments);
                }
                $arguments = $arguments->validated();
            }
            $arguments = config('soap.call.wrap_arguments_in_array', true) ? [$arguments] : $arguments;
            $result = $this->engine->request($method, $arguments);

            if ($result instanceof ResultProviderInterface) {
                return $this->buildResponse(Response::fromSoapResponse($result->getResult()));
            }

            if (! $result instanceof ResultInterface) {
                return $this->buildResponse(Response::fromSoapResponse($result));
            }

            return $this->buildResponse(new Response(new Psr7Response(200, [], $result)));
        } catch (Exception $exception) {
            if ($exception instanceof SoapFault) {
                return $this->buildResponse(Response::fromSoapFault($exception));
            }
            $previous = $exception->getPrevious();
            $this->dispatchConnectionFailedEvent();

            if ($previous instanceof HttpException) {
                return new Response($previous->getResponse());
            }

            throw SoapException::fromThrowable($exception);
        }
    }

    /**
     * Build the Soap client.
     *
     * @throws NotFoundConfigurationException
     */
    public function buildClient(string $setup = ''): static
    {
        $this->byConfig($setup);
        $this->withGuzzleClientOptions([
            'handler' => $this->buildHandlerStack(),
            'on_stats' => function ($transferStats): void {
                $this->transferStats = $transferStats;
            },
        ]);
        $this->isClientBuilt = true;

        return $this;
    }

    /**
     * @throws NotFoundConfigurationException
     */
    public function byConfig(string $setup): static
    {
        if (! empty($setup)) {
            $setup = config('soap.clients.'.$setup);

            if (! $setup) {
                throw new NotFoundConfigurationException($setup);
            }

            foreach ($setup as $setupItem => $setupItemConfig) {
                if (is_bool($setupItemConfig)) {
                    $this->{Str::camel($setupItem)}();
                } elseif (is_array($setupItemConfig)) {
                    $this->{Str::camel($setupItem)}($this->arrayKeysToCamel($setupItemConfig));
                } elseif (is_string($setupItemConfig)) {
                    $this->{Str::camel($setupItem)}($setupItemConfig);
                }
            }
        }

        return $this;
    }

    /**
     * Build the before sending handler stack.
     */
    public function buildHandlerStack(): HandlerStack
    {
        return tap(HandlerStack::create(), function ($stack): void {
            $stack->push($this->buildBeforeSendingHandler(), 'before_sending');
            $stack->push($this->buildRecorderHandler(), 'recorder');
            $stack->push($this->buildStubHandler(), 'stub');
        });
    }

    /**
     * Build the before sending handler.
     */
    public function buildBeforeSendingHandler(): Closure
    {
        return fn ($handler) => fn ($request, $options) => $handler($this->runBeforeSendingCallbacks(
            $request,
            $options,
        ), $options);
    }

    /**
     * Execute the "before sending" callbacks.
     */
    public function runBeforeSendingCallbacks(RequestInterface $request, array $options): RequestInterface
    {
        return tap($request, function ($request) use ($options): void {
            $this->beforeSendingCallbacks->each(fn (callable $callable) => $callable(
                new Request($request),
                $options,
                $this,
            ));
        });
    }

    /**
     * Build the recorder handler.
     */
    public function buildRecorderHandler(): Closure
    {
        return function ($handler) {
            return function ($request, $options) use ($handler) {
                $promise = $handler($request, $options);

                return $promise->then(function ($response) use ($request) {
                    optional($this->factory)->recordRequestResponsePair(
                        new Request($request),
                        new Response($response),
                    );

                    return $response;
                });
            };
        };
    }

    /**
     * Build the stub handler.
     */
    public function buildStubHandler(): Closure
    {
        return function (callable $handler) {
            return function ($request, $options) use ($handler) {
                $response = ($this->stubCallbacks ?? collect())
                    ->map(fn (callable $callback) => $callback(new Request($request), $options))
                    ->filter()
                    ->first();

                if ($response === null) {
                    return $handler($request, $options);
                }

                if (is_array($response)) {
                    return SoapFactory::response($response);
                }

                return $response;
            };
        };
    }

    public function baseWsdl(string $wsdl): static
    {
        $this->wsdl = $wsdl;

        return $this;
    }

    /**
     * Register a stub callable that will intercept requests and be able to return stub responses.
     */
    public function stub(callable|Collection $callback): static
    {
        if ($callback instanceof Collection) {
            $callback = $callback->all();
        }

        $this->stubCallbacks = collect($callback);

        return $this;
    }

    protected function setTransport(?Transport $handler = null): static
    {
        $soapClient = AbusedClient::createFromOptions(ExtSoapOptions::defaults($this->wsdl, $this->options));
        $transport = $handler ?? Psr18Transport::createForClient($this->pluginClient);

        $this->transport = $handler ?? new TraceableTransport($soapClient, $transport);

        return $this;
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator): Response
    {
        return Response::fromSoapResponse([
            'success' => false,
            'message' => __('Invalid data.'),
            'errors' => $validator->errors(),
        ]);
    }

    protected function buildResponse($response)
    {
        return tap($response, function ($result): void {
            $this->populateResponse($result);
            $this->dispatchResponseReceivedEvent($result);
        });
    }

    protected function arrayKeysToCamel(array $items): array
    {
        $changedItems = [];

        foreach ($items as $key => $value) {
            $changedItems[Str::camel($key)] = $value;
        }

        return $changedItems;
    }

    /**
     * Populate the given response with additional data.
     */
    protected function populateResponse(Response $response): Response
    {
        $response->setCookies($this->cookies);
        $response->setTransferStats($this->transferStats);

        return $response;
    }

    /**
     * Dispatch the RequestSending event if a dispatcher is available.
     */
    protected function dispatchRequestSendingEvent(): void
    {
        event(new RequestSending($this->request));
    }

    /**
     * Dispatch the ResponseReceived event if a dispatcher is available.
     */
    protected function dispatchResponseReceivedEvent(Response $response): void
    {
        if (! $this->request instanceof Request) {
            return;
        }

        event(new ResponseReceived($this->request, $response));
    }

    /**
     * Dispatch the ConnectionFailed event if a dispatcher is available.
     */
    protected function dispatchConnectionFailedEvent(): void
    {
        event(new ConnectionFailed($this->request));
    }

    protected function refreshEngine(): static
    {
        $this->refreshPluginClient();
        $this->setTransport();
        $this->refreshExtSoapOptions();
        $this->engine = ExtSoapEngineFactory::fromOptionsWithHandler(
            $this->extSoapOptions,
            $this->transport,
            $this->factory->isRecording(),
        );
        $this->refreshWsdlProvider();

        return $this;
    }

    protected function refreshExtSoapOptions(): void
    {
        $this->extSoapOptions = ExtSoapOptions::defaults($this->wsdl, $this->options);

        if ($this->factory->isRecording()) {
            $this->wsdlProvider = new FlatteningLoader(new StreamWrapperLoader);
        }
    }

    public function __call($method, $parameters)
    {
        if (static::hasMacro($method)) {
            return $this->macroCall($method, $parameters);
        }

        return $this->call($method, $parameters[0] ?? $parameters);
    }
}
