<?php declare(strict_types=1);

namespace Antwerpes\Soap\Client;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;
use LogicException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Soap\Psr18Transport\HttpBinding\SoapActionDetector;
use Soap\Xml\Locator\SoapBodyLocator;

use function VeeWee\Xml\Dom\Configurator\traverse;

use VeeWee\Xml\Dom\Document;
use VeeWee\Xml\Dom\Traverser\Visitor\RemoveNamespaces;

use function VeeWee\Xml\Encoding\element_decode;

use VeeWee\Xml\Encoding\Exception\EncodingException;

class Request
{
    use Macroable;

    /** The decoded payload for the request. */
    protected array $data;

    /**
     * Create a new request instance.
     */
    public function __construct(
        /** The underlying PSR request. */
        protected RequestInterface $request,
    ) {}

    /**
     * Get the soap action for soap 1.1 and 1.2.
     */
    public function action(): string
    {
        return Str::remove('"', SoapActionDetector::detectFromRequest($this->request));
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    /**
     * Get the URL of the request.
     */
    public function url(): string
    {
        return (string) $this->request->getUri();
    }

    /**
     * Determine if the request has a given header.
     */
    public function hasHeader(string $key, mixed $value = null): bool
    {
        if ($value === null) {
            return ! empty($this->request->getHeaders()[$key]);
        }

        $headers = $this->headers();

        if (! Arr::has($headers, $key)) {
            return false;
        }

        $value = is_array($value) ? $value : [$value];

        return empty(array_diff($value, $headers[$key]));
    }

    /**
     * Determine if the request has the given headers.
     */
    public function hasHeaders(array|string $headers): bool
    {
        if (is_string($headers)) {
            $headers = [$headers => null];
        }

        foreach ($headers as $key => $value) {
            if (! $this->hasHeader($key, $value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the values for the header with the given name.
     */
    public function header(string $key): array
    {
        return Arr::get($this->headers(), $key, []);
    }

    /**
     * Get the request headers.
     */
    public function headers(): array
    {
        return $this->request->getHeaders();
    }

    /**
     * Get the body of the request.
     */
    public function body(): string
    {
        return (string) $this->request->getBody();
    }

    /**
     * Return complete xml request body.
     */
    public function xmlContent(): string
    {
        return $this->request->getBody()->getContents();
    }

    /**
     * Return request arguments.
     *
     * @throws EncodingException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function arguments(): array
    {
        $doc = Document::fromXmlString($this->body());
        $wrappedArguments = config()->get('soap.call.wrap_arguments_in_array', true);
        $method = $doc->locate(new SoapBodyLocator);

        if ($wrappedArguments) {
            $method = $method?->firstElementChild;
        }

        return Arr::wrap(
            Arr::get(element_decode($method, traverse(new RemoveNamespaces)), $wrappedArguments ? 'node' : 'Body', []),
        );
    }

    /**
     * Set the decoded data on the request.
     */
    public function withData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Get the underlying PSR compliant request instance.
     */
    public function toPsrRequest(): RequestInterface
    {
        return $this->request;
    }

    /**
     * Determine if the given offset exists.
     *
     * @throws ContainerExceptionInterface
     * @throws EncodingException
     * @throws NotFoundExceptionInterface
     */
    public function offsetExists(string $offset): bool
    {
        return isset($this->arguments()[$offset]);
    }

    /**
     * Get the value for a given offset.
     *
     * @throws ContainerExceptionInterface
     * @throws EncodingException
     * @throws NotFoundExceptionInterface
     */
    public function offsetGet(string $offset): mixed
    {
        return $this->arguments()[$offset];
    }

    /**
     * Set the value at the given offset.
     *
     * @throws LogicException
     */
    public function offsetSet(): void
    {
        throw new LogicException('Request data may not be mutated using array access.');
    }

    /**
     * Unset the value at the given offset.
     *
     * @throws LogicException
     */
    public function offsetUnset(): void
    {
        throw new LogicException('Request data may not be mutated using array access.');
    }
}
