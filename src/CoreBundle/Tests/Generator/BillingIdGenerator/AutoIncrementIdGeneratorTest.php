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

namespace SolidInvoice\CoreBundle\Tests\Generator\BillingIdGenerator;

use PHPUnit\Framework\Attributes\CoversClass;
use SolidInvoice\ClientBundle\Test\Factory\ClientFactory;
use SolidInvoice\CoreBundle\Generator\BillingIdGenerator\AutoIncrementIdGenerator;
use SolidInvoice\InstallBundle\Test\EnsureApplicationInstalled;
use SolidInvoice\InvoiceBundle\Entity\Invoice;
use SolidInvoice\InvoiceBundle\Test\Factory\InvoiceFactory;
use SolidInvoice\QuoteBundle\Entity\Quote;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(AutoIncrementIdGenerator::class)]
final class AutoIncrementIdGeneratorTest extends KernelTestCase
{
    use EnsureApplicationInstalled;

    public function testItGeneratesTheSameIdWhenNotSavingAnyEntities(): void
    {
        $generator = new AutoIncrementIdGenerator(self::getContainer()->get('doctrine'));

        self::assertSame('1', $generator->generate(new Invoice(), ['field' => 'invoiceId']));
        self::assertSame('1', $generator->generate(new Invoice(), ['field' => 'invoiceId']));

        self::assertSame('1', $generator->generate(new Quote(), ['field' => 'quoteId']));
        self::assertSame('1', $generator->generate(new Quote(), ['field' => 'quoteId']));
    }

    public function testItIncrementsTheId(): void
    {
        $client = ClientFactory::new([]);

        InvoiceFactory::createOne(['client' => $client, 'invoiceId' => '1']);

        $generator = new AutoIncrementIdGenerator(self::getContainer()->get('doctrine'));

        self::assertSame('2', $generator->generate(new Invoice(), ['field' => 'invoiceId']));

        InvoiceFactory::createOne(['client' => $client, 'invoiceId' => '2']);

        self::assertSame('3', $generator->generate(new Invoice(), ['field' => 'invoiceId']));

        InvoiceFactory::createOne(['client' => $client, 'invoiceId' => '101']);

        self::assertSame('102', $generator->generate(new Invoice(), ['field' => 'invoiceId']));
        self::assertSame('1', $generator->generate(new Quote(), ['field' => 'quoteId']));
    }

    public function testItIncrementsTheIdWithPrefix(): void
    {
        $client = ClientFactory::new([]);

        InvoiceFactory::createOne(['client' => $client, 'invoiceId' => 'INV-1']);

        $generator = new AutoIncrementIdGenerator(self::getContainer()->get('doctrine'));

        self::assertSame('2', $generator->generate(new Invoice(), ['field' => 'invoiceId', 'prefix' => 'INV-', 'suffix' => '']));

        InvoiceFactory::createOne(['client' => $client, 'invoiceId' => 'INV-2']);

        self::assertSame('3', $generator->generate(new Invoice(), ['field' => 'invoiceId', 'prefix' => 'INV-', 'suffix' => '']));

        InvoiceFactory::createOne(['client' => $client, 'invoiceId' => 'INV-101']);

        self::assertSame('102', $generator->generate(new Invoice(), ['field' => 'invoiceId', 'prefix' => 'INV-', 'suffix' => '']));
    }

    public function testItIncrementsTheIdWithSuffix(): void
    {
        $client = ClientFactory::new([]);

        InvoiceFactory::createOne(['client' => $client, 'invoiceId' => '1-00']);

        $generator = new AutoIncrementIdGenerator(self::getContainer()->get('doctrine'));

        self::assertSame('2', $generator->generate(new Invoice(), ['field' => 'invoiceId', 'prefix' => '', 'suffix' => '-00']));

        InvoiceFactory::createOne(['client' => $client, 'invoiceId' => '2-00']);

        self::assertSame('3', $generator->generate(new Invoice(), ['field' => 'invoiceId', 'prefix' => '', 'suffix' => '-00']));

        InvoiceFactory::createOne(['client' => $client, 'invoiceId' => '101-00']);

        self::assertSame('102', $generator->generate(new Invoice(), ['field' => 'invoiceId', 'prefix' => '', 'suffix' => '-00']));
    }

    public function testItIncrementsTheIdWithPrefixAndSuffix(): void
    {
        $client = ClientFactory::new([]);

        InvoiceFactory::createOne(['client' => $client, 'invoiceId' => 'INV-1-00']);

        $generator = new AutoIncrementIdGenerator(self::getContainer()->get('doctrine'));

        self::assertSame('2', $generator->generate(new Invoice(), ['field' => 'invoiceId', 'prefix' => 'INV-', 'suffix' => '-00']));

        InvoiceFactory::createOne(['client' => $client, 'invoiceId' => 'INV-2-00']);

        self::assertSame('3', $generator->generate(new Invoice(), ['field' => 'invoiceId', 'prefix' => 'INV-', 'suffix' => '-00']));

        InvoiceFactory::createOne(['client' => $client, 'invoiceId' => 'INV-101-00']);

        self::assertSame('102', $generator->generate(new Invoice(), ['field' => 'invoiceId', 'prefix' => 'INV-', 'suffix' => '-00']));
    }

    public function testItIgnoresANumberThatIsNotWrittenInTheConfiguredFormat(): void
    {
        $client = ClientFactory::new([]);

        // Typed by hand with no prefix configured. MySQL, MariaDB and SQLite cast it to
        // 2026, which used to make the next number "2027".
        InvoiceFactory::createOne(['client' => $client, 'invoiceId' => '2026-0001']);

        $generator = new AutoIncrementIdGenerator(self::getContainer()->get('doctrine'));

        self::assertSame('1', $generator->generate(new Invoice(), ['field' => 'invoiceId', 'prefix' => '', 'suffix' => '']));

        InvoiceFactory::createOne(['client' => $client, 'invoiceId' => '7']);

        self::assertSame('8', $generator->generate(new Invoice(), ['field' => 'invoiceId', 'prefix' => '', 'suffix' => '']));
    }

    public function testItKeepsTheZeroPaddingOfTheHighestNumber(): void
    {
        $client = ClientFactory::new([]);

        InvoiceFactory::createOne(['client' => $client, 'invoiceId' => '2026-0001']);

        $generator = new AutoIncrementIdGenerator(self::getContainer()->get('doctrine'));
        $options = ['field' => 'invoiceId', 'prefix' => '2026-', 'suffix' => ''];

        self::assertSame('0002', $generator->generate(new Invoice(), $options));

        InvoiceFactory::createOne(['client' => $client, 'invoiceId' => '2026-0009']);

        self::assertSame('0010', $generator->generate(new Invoice(), $options));

        InvoiceFactory::createOne(['client' => $client, 'invoiceId' => '2026-9999']);

        self::assertSame('10000', $generator->generate(new Invoice(), $options));
    }

    public function testANewPrefixStartsItsOwnSequence(): void
    {
        $client = ClientFactory::new([]);

        InvoiceFactory::createOne(['client' => $client, 'invoiceId' => '2026-0041']);

        $generator = new AutoIncrementIdGenerator(self::getContainer()->get('doctrine'));

        self::assertSame('1', $generator->generate(new Invoice(), ['field' => 'invoiceId', 'prefix' => '2027-', 'suffix' => '']));

        InvoiceFactory::createOne(['client' => $client, 'invoiceId' => '2027-0001']);

        self::assertSame('0002', $generator->generate(new Invoice(), ['field' => 'invoiceId', 'prefix' => '2027-', 'suffix' => '']));
        self::assertSame('0042', $generator->generate(new Invoice(), ['field' => 'invoiceId', 'prefix' => '2026-', 'suffix' => '']));
    }
}
