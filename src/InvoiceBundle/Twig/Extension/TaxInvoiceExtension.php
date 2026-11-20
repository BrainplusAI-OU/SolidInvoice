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

namespace SolidInvoice\InvoiceBundle\Twig\Extension;

use SolidInvoice\CoreBundle\Entity\Company;
use SolidInvoice\InvoiceBundle\Entity\BaseInvoice;
use SolidInvoice\InvoiceBundle\Entity\Invoice;
use Twig\Attribute\AsTwigFunction;

/**
 * Where an invoice is a tax invoice by law, and what it is then called.
 *
 * In Thailand a business that charges VAT must issue a ใบกำกับภาษี (Revenue Code, section 86/4),
 * and the document must say so in Thai, prominently. A company based in Thailand that charges
 * VAT on an invoice therefore gets that title; any other invoice keeps the plain one.
 *
 * @see \SolidInvoice\InvoiceBundle\Tests\Twig\Extension\TaxInvoiceExtensionTest
 */
final class TaxInvoiceExtension
{
    #[AsTwigFunction(name: 'is_thai_company')]
    public function isThaiCompany(BaseInvoice $document): bool
    {
        return $this->company($document)?->getCountry() === 'TH';
    }

    #[AsTwigFunction(name: 'is_tax_invoice')]
    public function isTaxInvoice(Invoice $invoice): bool
    {
        return $this->isThaiCompany($invoice) && $invoice->getTax()->isPositive();
    }

    #[AsTwigFunction(name: 'invoice_title_key')]
    public function titleKey(Invoice $invoice): string
    {
        return $this->isTaxInvoice($invoice) ? 'invoice.pdf.tax_invoice_th' : 'invoice.pdf.title';
    }

    private function company(BaseInvoice $document): ?Company
    {
        // A preview document is built in memory and may belong to no company.
        return $document->getCompanyId() !== null ? $document->getCompany() : null;
    }
}
