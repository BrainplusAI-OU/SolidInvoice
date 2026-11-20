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

namespace SolidInvoice\InvoiceBundle\Tests\Twig\Extension;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SolidInvoice\CoreBundle\Entity\Company;
use SolidInvoice\InvoiceBundle\Entity\Invoice;
use SolidInvoice\InvoiceBundle\Twig\Extension\TaxInvoiceExtension;
use Symfony\Component\Uid\Ulid;

final class TaxInvoiceExtensionTest extends TestCase
{
    /**
     * @return iterable<string, array{?string, int, string}>
     */
    public static function invoiceProvider(): iterable
    {
        yield 'Thai company charging VAT' => ['TH', 7000, 'invoice.pdf.tax_invoice_th'];
        yield 'Thai company charging no VAT' => ['TH', 0, 'invoice.pdf.title'];
        yield 'company elsewhere' => ['FR', 7000, 'invoice.pdf.title'];
        yield 'company with no country' => [null, 7000, 'invoice.pdf.title'];
    }

    #[DataProvider('invoiceProvider')]
    public function testOnlyAThaiCompanyChargingVatIssuesATaxInvoice(?string $country, int $tax, string $key): void
    {
        $company = $this->createStub(Company::class);
        $company->method('getId')->willReturn(new Ulid());
        $company->method('getCountry')->willReturn($country);

        $invoice = new Invoice();
        $invoice->setCompany($company);
        $invoice->setTax($tax);

        self::assertSame($key, new TaxInvoiceExtension()->titleKey($invoice));
    }

    public function testAnInvoiceWithNoCompanyIsAPlainInvoice(): void
    {
        $invoice = new Invoice();
        $invoice->setTax(7000);

        self::assertSame('invoice.pdf.title', new TaxInvoiceExtension()->titleKey($invoice));
    }
}
