<?php declare(strict_types=1);

namespace Antwerpes\Soap\Client;

use Antwerpes\Soap\SoapFactory;
use GuzzleHttp\Promise\PromiseInterface;
use OutOfBoundsException;

class ResponseSequence
{
    /** Indicates that invoking this sequence when it is empty should throw an exception. */
    protected bool $failWhenEmpty = true;

    /** The response that should be returned when the sequence is empty. */
    protected ?PromiseInterface $emptyResponse = null;

    public function __construct(
        /** The responses in the sequence. */
        protected array $responses,
    ) {}

    /**
     * Get the next response in the sequence.
     */
    public function __invoke(): mixed
    {
        if ($this->failWhenEmpty && count($this->responses) === 0) {
            throw new OutOfBoundsException('A request was made, but the response sequence is empty.');
        }

        if (! $this->failWhenEmpty && count($this->responses) === 0) {
            return value($this->emptyResponse ?? SoapFactory::response());
        }

        return array_shift($this->responses);
    }

    /**
     * Push a response to the sequence.
     */
    public function push(array|string $body = '', int $status = 200, array $headers = []): static
    {
        return $this->pushResponse(SoapFactory::response($body, $status, $headers));
    }

    /**
     * Push a response to the sequence.
     */
    public function pushResponse(mixed $response): static
    {
        $this->responses[] = $response;

        return $this;
    }

    /**
     * Push a response with the given status code to the sequence.
     */
    public function pushStatus(int $status, array $headers = []): static
    {
        return $this->pushResponse(SoapFactory::response('', $status, $headers));
    }

    /**
     * Push response with the contents of a file as the body to the sequence.
     */
    public function pushFile(string $filePath, int $status = 200, array $headers = []): static
    {
        $string = file_get_contents($filePath);

        return $this->pushResponse(SoapFactory::response($string, $status, $headers));
    }

    /**
     * Make the sequence return a default response when it is empty.
     */
    public function dontFailWhenEmpty(): static
    {
        return $this->whenEmpty(SoapFactory::response());
    }

    /**
     * Make the sequence return a default response when it is empty.
     */
    public function whenEmpty(PromiseInterface $response): static
    {
        $this->failWhenEmpty = false;
        $this->emptyResponse = $response;

        return $this;
    }

    /**
     * Indicate that this sequence has depleted all of its responses.
     */
    public function isEmpty(): bool
    {
        return count($this->responses) === 0;
    }
}
