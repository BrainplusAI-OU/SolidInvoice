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

namespace SolidInvoice\CoreBundle\Currency;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use DateTimeImmutable;
use DateTimeInterface;
use Money\Currency;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The reference rate between two currencies on a day, from a Frankfurter server: the European
 * Central Bank's daily rates, free and keyless. SOLIDINVOICE_EXCHANGE_RATE_URL points at
 * another Frankfurter server, or turns the lookup off when empty.
 *
 * Returns null whenever no rate can be had; an invoice is never held back for want of one.
 *
 * @see \SolidInvoice\CoreBundle\Tests\Currency\ExchangeRateProviderTest
 */
final readonly class ExchangeRateProvider
{
    public const string SOURCE = 'ECB';

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        #[Autowire(env: 'SOLIDINVOICE_EXCHANGE_RATE_URL')]
        private string $baseUrl = 'https://api.frankfurter.app',
    ) {
    }

    /**
     * @return array{rate: BigDecimal, date: DateTimeImmutable}|null How many `$to` one `$from` buys, and the day of that rate
     */
    public function rate(Currency $from, Currency $to, DateTimeInterface $date): ?array
    {
        if (trim($this->baseUrl) === '' || $from->equals($to)) {
            return null;
        }

        try {
            $data = $this->httpClient->request('GET', rtrim($this->baseUrl, '/') . '/' . $date->format('Y-m-d'), [
                'query' => ['from' => $from->getCode(), 'to' => $to->getCode()],
                'timeout' => 5,
            ])->toArray();

            $rate = $data['rates'][$to->getCode()] ?? null;

            if (! is_int($rate) && ! is_float($rate) && ! is_string($rate)) {
                return null;
            }

            // The ECB publishes on working days only; the server answers with the last one before.
            $day = DateTimeImmutable::createFromFormat('!Y-m-d', is_string($data['date'] ?? null) ? $data['date'] : '');

            return [
                'rate' => BigDecimal::of((string) $rate),
                'date' => $day instanceof DateTimeImmutable ? $day : DateTimeImmutable::createFromInterface($date),
            ];
        } catch (ExceptionInterface | MathException $e) {
            $this->logger->warning('No exchange rate for {from} to {to}', ['from' => $from->getCode(), 'to' => $to->getCode(), 'exception' => $e]);

            return null;
        }
    }
}
