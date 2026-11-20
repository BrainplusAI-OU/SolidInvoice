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

namespace SolidInvoice\ClientBundle\Tests\DataGrid;

use Brick\Math\BigNumber;
use Money\Currency;
use Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SolidInvoice\ClientBundle\DataGrid\BaseClientGrid;
use SolidInvoice\ClientBundle\Entity\Client;
use SolidInvoice\InvoiceBundle\Entity\Invoice;

#[CoversClass(BaseClientGrid::class)]
final class BaseClientGridTest extends TestCase
{
    public function testAmountsAreInTheClientCurrencyNotTheSystemOne(): void
    {
        $client = (new Client())->setCurrencyCode('EUR');

        $sum = BaseClientGrid::sumByCurrency(
            [$this->invoice($client, 9600000), $this->invoice($client, 12500000)],
            static fn (Invoice $invoice): BigNumber => $invoice->getTotal(),
            $client,
        );

        self::assertEquals(new Money(22100000, new Currency('EUR')), $sum);
    }

    public function testAnIssuedInvoiceKeepsItsFrozenCurrency(): void
    {
        $client = (new Client())->setCurrencyCode('EUR');
        $frozen = $this->invoice($client, 4500000);
        $frozen->setCurrencyCode('THB');

        $sum = BaseClientGrid::sumByCurrency(
            [$this->invoice($client, 1000000), $frozen],
            static fn (Invoice $invoice): BigNumber => $invoice->getTotal(),
            $client,
        );

        self::assertEquals(
            [new Money(1000000, new Currency('EUR')), new Money(4500000, new Currency('THB'))],
            $sum,
        );
    }

    public function testNoInvoiceGivesZeroInTheClientCurrency(): void
    {
        $client = (new Client())->setCurrencyCode('USD');

        $sum = BaseClientGrid::sumByCurrency([], static fn (Invoice $invoice): BigNumber => $invoice->getTotal(), $client);

        self::assertEquals(new Money(0, new Currency('USD')), $sum);
    }

    private function invoice(Client $client, int $total): Invoice
    {
        $invoice = new Invoice();
        $invoice->setClient($client);
        $invoice->setTotal($total);

        return $invoice;
    }
}
