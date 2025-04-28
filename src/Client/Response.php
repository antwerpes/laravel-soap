<?php declare(strict_types=1);

namespace Antwerpes\Soap\Client;

use Antwerpes\Soap\Exceptions\RequestException;
use ArrayAccess;
use GuzzleHttp\Psr7\Response as Psr7Response;
use GuzzleHttp\TransferStats;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;
use LogicException;
use Phpro\SoapClient\Type\ResultInterface;

use function Psl\Type\string;

use Psr\Http\Message\ResponseInterface;
use SoapFault;
use VeeWee\Xml\Dom\Document;

class Response implements ArrayAccess, ResultInterface
{
    use Macroable {
        __call as macroCall;
    }
    protected ?TransferStats $transferStats = null;
    protected array $cookies = [];

    /** The decoded JSON response. */
    protected ?array $decoded = null;

    /**
     * Create a new response instance.
     */
    public function __construct(
        /** The underlying PSR response. */
        protected ResponseInterface $response,
    ) {}

    public static function fromSoapResponse(mixed $result, int $status = 200): self
    {
        return new self(new Psr7Response($status, [], json_encode($result)));
    }

    public static function fromSoapFault(SoapFault $soapFault): self
    {
        return new self(new Psr7Response(400, [], $soapFault->getMessage()));
    }

    public function setCookies(array $cookies): void
    {
        $this->cookies = $cookies;
    }

    public function setTransferStats(?TransferStats $transferStats): void
    {
        $this->transferStats = $transferStats;
    }

    /**
     * Get the underlying PSR response for the response.
     */
    public function toPsrResponse(): ResponseInterface
    {
        return $this->response;
    }

    /**
     * Get the JSON decoded body of the response as an object.
     */
    public function object(): object
    {
        return json_decode($this->body(), false);
    }

    /**
     * Get the JSON decoded body of the response as a collection.
     */
    public function collect(?string $key = null): Collection
    {
        return Collection::make($this->json($key));
    }

    /**
     * Get the body of the response.
     */
    public function body(bool $transformXml = true, bool $sanitizeXmlFaultMessage = true): string
    {
        $body = (string) $this->response->getBody();

        if ($transformXml && Str::contains($body, '<?xml')) {
            $message = Document::fromXmlString($body)
                ->xpath()
                ->evaluate('string(.//faultstring)', string())
                ?? 'No Fault Message found';

            return mb_trim($sanitizeXmlFaultMessage ? Str::after($message, 'Exception:') : $message);
        }

        return $body;
    }

    /**
     * Determine if the request was successful.
     */
    public function successful(): bool
    {
        return $this->status() >= 200 && $this->status() < 300;
    }

    /**
     * Get the status code of the response.
     */
    public function status(): int
    {
        return $this->response->getStatusCode();
    }

    /**
     * Determine if the response code was "OK".
     */
    public function ok(): bool
    {
        return $this->status() === 200;
    }

    /**
     * Determine if the response indicates a client or server error occurred.
     */
    public function failed(): bool
    {
        return $this->serverError() || $this->clientError();
    }

    /**
     * Determine if the response was a redirect.
     */
    public function redirect(): bool
    {
        return $this->status() >= 300 && $this->status() < 400;
    }

    /**
     * Throw an exception if a server or client error occurred.
     *
     * @throws RequestException
     */
    public function throw(): static
    {
        $callback = func_get_args()[0] ?? null;

        if ($this->failed()) {
            throw tap(new RequestException($this), function ($exception) use ($callback): void {
                if ($callback && is_callable($callback)) {
                    $callback($this, $exception);
                }
            });
        }

        return $this;
    }

    /**
     * Throw an exception if a server or client error occurred and the given condition evaluates to true.
     *
     * @throws RequestException
     */
    public function throwIf(bool $condition): static
    {
        return $condition ? $this->throw() : $this;
    }

    /**
     * Determine if the response indicates a server error occurred.
     */
    public function serverError(): bool
    {
        return $this->status() >= 500;
    }

    /**
     * Determine if the response indicates a client error occurred.
     */
    public function clientError(): bool
    {
        return $this->status() >= 400 && $this->status() < 500;
    }

    /**
     * Execute the given callback if there was a server or client error.
     */
    public function onError(callable $callback): static
    {
        if ($this->failed()) {
            $callback($this);
        }

        return $this;
    }

    /**
     * Get the handler stats of the response.
     */
    public function handlerStats(): array
    {
        return $this->transferStats?->getHandlerStats() ?? [];
    }

    /**
     * Get the JSON decoded body of the response as an array.
     *
     * @param null|mixed $key
     * @param null|mixed $default
     */
    public function json($key = null, $default = null): ?array
    {
        if (! $this->decoded) {
            $this->decoded = json_decode($this->body(), true);
        }

        if ($key === null) {
            return $this->decoded;
        }

        return data_get($this->decoded, $key, $default);
    }

    /**
     * Get a header from the response.
     */
    public function header(string $header): string
    {
        return $this->response->getHeaderLine($header);
    }

    /**
     * Get the headers from the response.
     */
    public function headers(): array
    {
        return $this->response->getHeaders();
    }

    /**
     * Determine if the given offset exists.
     *
     * @param string $offset
     */
    public function offsetExists($offset): bool
    {
        return isset($this->json()[$offset]);
    }

    /**
     * Get the value for a given offset.
     *
     * @param string $offset
     */
    public function offsetGet($offset): mixed
    {
        return $this->json()[$offset];
    }

    /**
     * Set the value at the given offset.
     *
     * @param string $offset
     *
     * @throws LogicException
     */
    public function offsetSet($offset, mixed $value): void
    {
        throw new LogicException('Response data may not be mutated using array access.');
    }

    /**
     * Unset the value at the given offset.
     *
     * @param string $offset
     *
     * @throws LogicException
     */
    public function offsetUnset($offset): void
    {
        throw new LogicException('Response data may not be mutated using array access.');
    }

    /**
     * Get the body of the response.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->body();
    }

    /**
     * Dynamically proxy other methods to the underlying response.
     *
     * @param string $method
     * @param array $parameters
     *
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return static::hasMacro($method)
            ? $this->macroCall($method, $parameters)
            : $this->response->{$method}(...$parameters);
    }
}
