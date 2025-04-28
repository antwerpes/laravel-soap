<?php declare(strict_types=1);

namespace Antwerpes\Soap;

use Antwerpes\Soap\Client\Request;
use Antwerpes\Soap\Client\Response;
use Antwerpes\Soap\Client\ResponseSequence;
use Closure;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;

class SoapFactory
{
    use Macroable {
        __call as macroCall;
    }

    /** The client class name. */
    public static string $clientClass = 'Antwerpes\Soap\SoapClient';

    /** The stub callables that will handle requests. */
    protected Collection $stubCallbacks;

    /** Indicates if the factory is recording requests and responses. */
    protected bool $recording = false;

    /** The recorded response array. */
    protected array $recorded = [];

    /** All created response sequences. */
    protected array $responseSequences = [];

    /**
     * Create a new factory instance.
     */
    public function __construct()
    {
        $this->stubCallbacks = collect();
    }

    /**
     * Create a new response instance for use during stubbing.
     */
    public static function response(
        null|array|string $body = null,
        int $status = 200,
        array $headers = [],
    ): PromiseInterface {
        if (is_array($body)) {
            $body = json_encode($body);
        } elseif (is_string($body)) {
            $body = json_encode([
                'response' => $body,
            ]);
        }

        return Create::promiseFor(new Psr7Response($status, $headers, $body));
    }

    /**
     * Set the client class name.
     */
    public static function useClientClass(string $clientClass): void
    {
        static::$clientClass = $clientClass;
    }

    public function isRecording(): bool
    {
        return $this->recording;
    }

    public function getResponseSequences(): array
    {
        return $this->responseSequences;
    }

    public function getRecorded(): array
    {
        return $this->recorded;
    }

    /**
     * Record a request response pair.
     */
    public function recordRequestResponsePair(Request $request, Response $response): void
    {
        if ($this->recording) {
            $this->recorded[] = [$request, $response];
        }
    }

    /**
     * Register a response sequence for the given URL pattern.
     */
    public function fakeSequence(string $url = '*'): ResponseSequence
    {
        return tap($this->sequence(), function ($sequence) use ($url): void {
            $this->fake([$url => $sequence]);
        });
    }

    /**
     * Get an invokable object that returns a sequence of responses in order for use during stubbing.
     */
    public function sequence(array $responses = []): ResponseSequence
    {
        $sequence = new ResponseSequence($responses);
        $this->responseSequences[] = $sequence;

        return $sequence;
    }

    /**
     * Register a stub callable that will intercept requests and be able to return stub responses.
     */
    public function fake(null|array|callable $callback = null): static
    {
        $this->record();

        if ($callback === null) {
            $callback = fn () => static::response();
        }

        if (is_array($callback)) {
            $callback['*'] ??= self::response();

            foreach ($callback as $method => $callable) {
                $this->stubMethod($method, $callable);
            }

            return $this;
        }
        $this->stubCallbacks->push($callback instanceof Closure ? $callback : fn () => $callback);

        return $this;
    }

    /**
     * Stub the given URL using the given callback.
     */
    public function stubMethod(string $method, callable|PromiseInterface|Response $callback): static
    {
        return $this->fake(function (Request $request, $options) use ($method, $callback) {
            if (! Str::is(Str::start($method, '*'), $request->action())) {
                return;
            }

            return $callback instanceof Closure || $callback instanceof ResponseSequence
                ? $callback($request, $options)
                : $callback;
        });
    }

    /**
     * Get a collection of the request / response pairs matching the given truth test.
     */
    public function recorded(callable $callback): Collection
    {
        if (empty($this->recorded)) {
            return collect();
        }

        return collect($this->recorded)->filter(fn ($pair) => $callback($pair[0], $pair[1]));
    }

    /**
     * Get a new client class instance.
     */
    public function client(): SoapClient
    {
        return new static::$clientClass($this);
    }

    /**
     * Begin recording request / response pairs.
     */
    protected function record(): static
    {
        $this->recording = true;

        return $this;
    }

    /**
     * Execute a method against a new pending request instance.
     *
     * @param string $method
     * @param array $parameters
     *
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if (static::hasMacro($method)) {
            return $this->macroCall($method, $parameters);
        }

        if (Str::contains($method, 'assert')) {
            return (new SoapTesting($this))->{$method}(...$parameters);
        }

        return tap($this->client(), function (SoapClient $client): void {
            $client->stub($this->stubCallbacks);
        })->{$method}(...$parameters);
    }
}
