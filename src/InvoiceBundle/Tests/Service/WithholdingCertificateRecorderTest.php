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

namespace SolidInvoice\InvoiceBundle\Tests\Service;

use Brick\Math\BigInteger;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use SolidInvoice\ClientBundle\Test\Factory\ClientFactory;
use SolidInvoice\CoreBundle\Billing\TotalCalculator;
use SolidInvoice\InstallBundle\Test\EnsureApplicationInstalled;
use SolidInvoice\InvoiceBundle\Entity\Invoice;
use SolidInvoice\InvoiceBundle\Entity\Line;
use SolidInvoice\InvoiceBundle\Enum\InvoiceStatus;
use SolidInvoice\InvoiceBundle\Exception\WithholdingCertificateException;
use SolidInvoice\InvoiceBundle\Service\CreditNoteIssuer;
use SolidInvoice\InvoiceBundle\Service\WithholdingCertificateRecorder;
use SolidInvoice\PaymentBundle\Enum\PaymentStatus;
use SolidInvoice\PaymentBundle\Test\Factory\PaymentFactory;
use SolidInvoice\TaxBundle\Entity\InvoiceTax;
use SolidInvoice\TaxBundle\Entity\LineTax;
use SolidInvoice\TaxBundle\Entity\Tax;
use SolidInvoice\TaxBundle\Enum\TaxDirection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(WithholdingCertificateRecorder::class)]
final class WithholdingCertificateRecorderTest extends KernelTestCase
{
    use EnsureApplicationInstalled;

    private const string DATE = '2026-11-20';

    public function testAnInvoicePaidNetIsPaidOnceItsCertificateIsRecorded(): void
    {
        // 1,000.00 THB, 7% VAT, 3% withheld: total 1,070.00, the client pays 1,040.00.
        $invoice = $this->issuedInvoice();
        $this->pay($invoice, 104000);
        $this->calculate($invoice);

        self::assertSame(InvoiceStatus::Pending, $invoice->getStatus());
        self::assertSame('3000', (string) $invoice->getBalance()->toBigInteger());

        $certificate = $this->recorder()->record($invoice, 'Book 1 No. 42', new DateTimeImmutable(self::DATE), BigInteger::of(3000), ['name' => '50-tawi.pdf', 'content' => "%PDF-1.4\n%%EOF\n"]);

        self::assertSame('Book 1 No. 42', $certificate->getNumber());
        self::assertTrue($certificate->hasFile());
        self::assertSame('application/pdf', $certificate->getMimeType());
        self::assertSame(InvoiceStatus::Paid, $invoice->getStatus());
        self::assertNotNull($invoice->getPaidDate());
        self::assertTrue($invoice->getBalance()->isZero());
        self::assertFalse(WithholdingCertificateRecorder::canBeCertified($invoice));
    }

    public function testACertificateBeforePaymentOnlyLowersTheBalance(): void
    {
        $invoice = $this->issuedInvoice();

        $this->recorder()->record($invoice, 'WHT-7', new DateTimeImmutable(self::DATE), BigInteger::of(1000));

        self::assertSame(InvoiceStatus::Pending, $invoice->getStatus());
        self::assertSame('106000', (string) $invoice->getBalance()->toBigInteger());
        self::assertSame('2000', (string) WithholdingCertificateRecorder::uncertifiedWithholding($invoice));
    }

    public function testACreditNoteLowersTheWithholdingLeftToCertify(): void
    {
        $invoice = $this->issuedInvoice();
        $issuer = self::getContainer()->get(CreditNoteIssuer::class);
        self::assertInstanceOf(CreditNoteIssuer::class, $issuer);

        $issuer->issue($invoice, BigInteger::of(40000), 'Two days not delivered', new DateTimeImmutable(self::DATE));

        self::assertSame('1800', (string) WithholdingCertificateRecorder::uncertifiedWithholding($invoice));
    }

    public function testItRefusesMoreThanTheWithholdingLeft(): void
    {
        $invoice = $this->issuedInvoice();

        $this->expectException(WithholdingCertificateException::class);
        $this->expectExceptionMessage('withholding_certificate.error.amount_out_of_range');

        $this->recorder()->record($invoice, 'WHT-8', new DateTimeImmutable(self::DATE), BigInteger::of(3001));
    }

    public function testItRefusesAnEmptyNumber(): void
    {
        $invoice = $this->issuedInvoice();

        $this->expectException(WithholdingCertificateException::class);
        $this->expectExceptionMessage('withholding_certificate.error.number_required');

        $this->recorder()->record($invoice, '  ', new DateTimeImmutable(self::DATE), BigInteger::of(3000));
    }

    public function testItRefusesADraft(): void
    {
        $invoice = $this->issuedInvoice(InvoiceStatus::Draft);

        $this->expectException(WithholdingCertificateException::class);
        $this->expectExceptionMessage('withholding_certificate.error.not_certifiable');

        $this->recorder()->record($invoice, 'WHT-9', new DateTimeImmutable(self::DATE), BigInteger::of(3000));
    }

    private function recorder(): WithholdingCertificateRecorder
    {
        $recorder = self::getContainer()->get(WithholdingCertificateRecorder::class);
        self::assertInstanceOf(WithholdingCertificateRecorder::class, $recorder);

        return $recorder;
    }

    private function calculate(Invoice $invoice): void
    {
        $calculator = self::getContainer()->get(TotalCalculator::class);
        self::assertInstanceOf(TotalCalculator::class, $calculator);
        $calculator->calculateTotals($invoice);
    }

    private function issuedInvoice(InvoiceStatus $status = InvoiceStatus::Pending): Invoice
    {
        $vat = new Tax()->setName('VAT')->setRate(7)->setType(Tax::TYPE_EXCLUSIVE);

        $lineTax = new LineTax();
        $lineTax->snapshotFrom($vat);

        $line = new Line();
        $line->setDescription('Marketing services')->setQty(1)->setPrice(100000);
        $line->addTax($lineTax);

        $withholding = new InvoiceTax();
        $withholding->setNameSnapshot('WHT');
        $withholding->setRateSnapshot('3.0000');
        $withholding->setDirection(TaxDirection::Deductive);

        $invoice = new Invoice();
        $invoice->setClient(ClientFactory::createOne(['company' => $this->company, 'currencyCode' => 'THB']));
        $invoice->setCompany($this->company);
        $invoice->setStatus($status);
        $invoice->setCurrencyCode($status === InvoiceStatus::Draft ? null : 'THB');
        $invoice->setInvoiceId('2026-0001');
        $invoice->addLine($line);
        $invoice->addInvoiceTax($withholding);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $em->persist($vat);
        $em->persist($invoice);
        $em->flush();

        self::assertSame('107000', (string) $invoice->getTotal()->toBigInteger());
        self::assertSame('3000', (string) $invoice->getWithholdingAmount()->toBigInteger());

        return $invoice;
    }

    private function pay(Invoice $invoice, int $amount): void
    {
        $payment = PaymentFactory::createOne([
            'company' => $this->company,
            'client' => $invoice->getClient(),
            'invoice' => $invoice,
            'status' => PaymentStatus::Captured,
            'totalAmount' => $amount,
            'currencyCode' => 'THB',
        ]);
        $invoice->addPayment($payment);
    }
}
