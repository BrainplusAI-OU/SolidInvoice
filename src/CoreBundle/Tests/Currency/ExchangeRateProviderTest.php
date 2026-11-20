<?php

declare(strict_types=1);

/*
 * This file is part of SolidInvoice project.
 *
 * (c) Pierre du Plessis <open-source@solidworx.co>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace SolidInvoice\CoreBundle\Tests\Currency;

use DateTimeImmutable;
use Money\Currency;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use SolidInvoice\CoreBundle\Currency\ExchangeRateProvider;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ExchangeRateProviderTest extends TestCase
{
    public function testItReadsTheRateAndTheDayItWasPublished(): void
    {
        $client = new MockHttpClient(static function (string $method, string $url): JsonMockResponse {
            self::assertSame('GET', $method);
            self::assertSame('https://rates.test/2026-11-22?from=USD&to=THB', $url);

            // A Sunday: the server answers with Friday's rate.
            return new JsonMockResponse(['amount' => 1.0, 'base' => 'USD', 'date' => '2026-11-20', 'rates' => ['THB' => 32.457]]);
        });

        $found = $this->provider($client)->rate(new Currency('USD'), new Currency('THB'), new DateTimeImmutable('2026-11-22'));

        self::assertNotNull($found);
        self::assertSame('32.457', (string) $found['rate']);
        self::assertSame('2026-11-20', $found['date']->format('Y-m-d'));
    }

    public function testNoRateIsNotAnError(): void
    {
        $date = new DateTimeImmutable('2026-11-20');

        self::assertNull($this->provider(new MockHttpClient(new MockResponse('', ['http_code' => 404])))->rate(new Currency('USD'), new Currency('THB'), $date));
        self::assertNull($this->provider(new MockHttpClient(new JsonMockResponse(['rates' => []])))->rate(new Currency('USD'), new Currency('THB'), $date));
        self::assertNull($this->provider(new MockHttpClient(), '')->rate(new Currency('USD'), new Currency('THB'), $date));
        self::assertNull($this->provider(new MockHttpClient())->rate(new Currency('THB'), new Currency('THB'), $date));
    }

    private function provider(MockHttpClient $client, string $url = 'https://rates.test'): ExchangeRateProvider
    {
        return new ExchangeRateProvider($client, new NullLogger(), $url);
    }
}
