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

namespace SolidInvoice\InvoiceBundle\Tests\Listener;

use DateTimeImmutable;
use Psr\Log\NullLogger;
use SolidInvoice\CoreBundle\Currency\ExchangeRateProvider;
use SolidInvoice\InstallBundle\Test\EnsureApplicationInstalled;
use SolidInvoice\InvoiceBundle\Entity\Invoice;
use SolidInvoice\InvoiceBundle\Enum\InvoiceStatus;
use SolidInvoice\InvoiceBundle\Listener\FreezeExchangeRateListener;
use SolidInvoice\InvoiceBundle\Model\Graph;
use SolidInvoice\InvoiceBundle\Twig\Extension\TaxInvoiceExtension;
use SolidInvoice\SettingsBundle\SystemConfig;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Workflow\Event\TransitionEvent;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Transition;

final class FreezeExchangeRateListenerTest extends KernelTestCase
{
    use EnsureApplicationInstalled;

    public function testAnInvoiceInAnotherCurrencyKeepsTheRateOfItsDate(): void
    {
        $invoice = $this->invoice('USD');

        $this->listener(32.457)($this->accept($invoice));

        self::assertSame('32.45700000', $invoice->getExchangeRate());
        self::assertSame('THB', $invoice->getExchangeRateCurrency());
        self::assertSame('2026-11-20', $invoice->getExchangeRateDate()?->format('Y-m-d'));
        self::assertSame('ECB', $invoice->getExchangeRateSource());

        // USD 1,000.00 is THB 32,457.00.
        self::assertSame('3245700', (string) new TaxInvoiceExtension()->inExchangeRateCurrency($invoice, $invoice->getTotal()));
    }

    public function testARateGivenWithTheInvoiceIsKept(): void
    {
        $invoice = $this->invoice('USD');
        $invoice->setExchangeRate('32.10000000')->setExchangeRateCurrency('THB')->setExchangeRateSource('BOT');

        $this->listener(32.457)($this->accept($invoice));

        self::assertSame('32.10000000', $invoice->getExchangeRate());
        self::assertSame('BOT', $invoice->getExchangeRateSource());
    }

    public function testAnInvoiceInTheCompanyCurrencyNeedsNoRate(): void
    {
        $invoice = $this->invoice('THB');

        $this->listener(1.0)($this->accept($invoice));

        self::assertNull($invoice->getExchangeRate());
    }

    public function testCurrenciesWithDifferentDecimalsConvertInMinorUnits(): void
    {
        // JPY has no minor unit, THB has satang: JPY 1,000 at 0.2235 is THB 223.50.
        $invoice = $this->invoice('JPY');
        $invoice->setExchangeRate('0.22350000')->setExchangeRateCurrency('THB');

        self::assertSame('22350', (string) new TaxInvoiceExtension()->inExchangeRateCurrency($invoice, \Brick\Math\BigInteger::of(1000)));
    }

    private function listener(float $rate): FreezeExchangeRateListener
    {
        $config = self::getContainer()->get(SystemConfig::class);
        self::assertInstanceOf(SystemConfig::class, $config);
        $config->set(SystemConfig::CURRENCY_CONFIG_PATH, 'THB');

        $client = new MockHttpClient(new JsonMockResponse(['date' => '2026-11-20', 'rates' => ['THB' => $rate]]));

        return new FreezeExchangeRateListener(new ExchangeRateProvider($client, new NullLogger(), 'https://rates.test'), $config);
    }

    private function invoice(string $currency): Invoice
    {
        $invoice = new Invoice();
        $invoice->setStatus(InvoiceStatus::Draft);
        $invoice->setCurrencyCode($currency);
        $invoice->setInvoiceDate(new DateTimeImmutable('2026-11-20'));
        $invoice->setTotal(100000);

        return $invoice;
    }

    /**
     * @return TransitionEvent<Invoice>
     */
    private function accept(Invoice $invoice): TransitionEvent
    {
        return new TransitionEvent($invoice, new Marking([InvoiceStatus::Draft->value => 1]), new Transition(Graph::TRANSITION_ACCEPT, InvoiceStatus::Draft->value, InvoiceStatus::Pending->value));
    }
}
