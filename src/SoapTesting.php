<?php declare(strict_types=1);

namespace Antwerpes\Soap;

use Antwerpes\Soap\Client\Request;
use PHPUnit\Framework\Assert as PHPUnit;

class SoapTesting
{
    public function __construct(
        protected ?SoapFactory $factory = null,
    ) {}

    /**
     * Assert that a request / response pair was not recorded matching a given truth test.
     */
    public function assertNotSent(callable $callback): void
    {
        PHPUnit::assertFalse($this->factory->recorded($callback)->count() > 0, 'Unexpected request was recorded.');
    }

    /**
     * Assert that no request / response pair was recorded.
     */
    public function assertNothingSent(): void
    {
        PHPUnit::assertEmpty($this->factory->getRecorded(), 'Requests were recorded.');
    }

    /**
     * Assert that every created response sequence is empty.
     */
    public function assertSequencesAreEmpty(): void
    {
        foreach ($this->factory->getResponseSequences() as $responseSequence) {
            PHPUnit::assertTrue($responseSequence->isEmpty(), 'Not all response sequences are empty.');
        }
    }

    /**
     * Assert that a given soap action is called with optional arguments.
     */
    public function assertActionCalled(string $action): void
    {
        $this->assertSent(fn (Request $request) => $request->action() === $action);
    }

    /**
     * Assert that a request / response pair was recorded matching a given truth test.
     */
    public function assertSent(callable $callback): void
    {
        PHPUnit::assertTrue(
            $this->factory->recorded($callback)->count() > 0,
            'An expected request was not recorded.',
        );
    }

    /**
     * Assert how many requests have been recorded.
     */
    public function assertSentCount(int $count): void
    {
        PHPUnit::assertCount($count, $this->factory->getRecorded());
    }

    /**
     * Assert that the given request was sent in the given order.
     */
    public function assertSentInOrder(array $callbacks): void
    {
        $this->assertSentCount(count($callbacks));

        foreach ($callbacks as $index => $url) {
            $callback = is_callable($url)
                ? $url
                : fn ($request) => $request->url() === $url;

            PHPUnit::assertTrue($callback(
                $this->factory->getRecorded()[$index][0],
                $this->factory->getRecorded()[$index][1],
            ), 'An expected request (#'.($index + 1).') was not recorded.');
        }
    }
}
