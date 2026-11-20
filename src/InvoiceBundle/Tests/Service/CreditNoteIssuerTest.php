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
use SolidInvoice\ClientBundle\Entity\Client;
use SolidInvoice\ClientBundle\Test\Factory\ClientFactory;
use SolidInvoice\InstallBundle\Test\EnsureApplicationInstalled;
use SolidInvoice\InvoiceBundle\Entity\Invoice;
use SolidInvoice\InvoiceBundle\Entity\Line;
use SolidInvoice\InvoiceBundle\Enum\InvoiceStatus;
use SolidInvoice\InvoiceBundle\Exception\CreditNoteException;
use SolidInvoice\InvoiceBundle\Service\CreditNoteIssuer;
use SolidInvoice\PaymentBundle\Enum\PaymentStatus;
use SolidInvoice\PaymentBundle\Test\Factory\PaymentFactory;
use SolidInvoice\TaxBundle\Entity\InvoiceTax;
use SolidInvoice\TaxBundle\Entity\LineTax;
use SolidInvoice\TaxBundle\Entity\Tax;
use SolidInvoice\TaxBundle\Enum\TaxDirection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(CreditNoteIssuer::class)]
final class CreditNoteIssuerTest extends KernelTestCase
{
    use EnsureApplicationInstalled;

    private const string DATE = '2026-11-20';

    public function testAPartialCreditNoteCarriesItsShareOfTheTaxAndLowersTheBalance(): void
    {
        // 1,000.00 THB, 7% VAT, 3% withheld: total 1,070.00, client pays 1,040.00.
        $invoice = $this->issuedInvoice();

        $creditNote = $this->issuer()->issue($invoice, BigInteger::of(40000), 'Two days not delivered', new DateTimeImmutable(self::DATE));

        self::assertSame('CN-2026-0001', $creditNote->getNumber());
        self::assertSame('THB', $creditNote->getCurrency()->getCode());
        self::assertSame('100000', (string) $creditNote->getOriginalAmount());
        self::assertSame('60000', (string) $creditNote->getCorrectedAmount());
        self::assertSame('2800', (string) $creditNote->getTax());
        self::assertSame('42800', (string) $creditNote->getTotal());
        self::assertSame('1200', (string) $creditNote->getWithholding());
        self::assertSame('41600', (string) $creditNote->getPayableAmount());

        self::assertSame(InvoiceStatus::Pending, $invoice->getStatus());
        self::assertSame('64200', (string) $invoice->getBalance()->toBigInteger());
        self::assertSame('60000', (string) CreditNoteIssuer::creditableAmount($invoice));
    }

    public function testTheLastCreditNoteTakesWhatIsLeftOfTheTaxAndCancelsAnUnpaidInvoice(): void
    {
        $invoice = $this->issuedInvoice();
        $issuer = $this->issuer();

        $issuer->issue($invoice, BigInteger::of(333), 'Rounding', new DateTimeImmutable(self::DATE));
        $last = $issuer->issue($invoice, BigInteger::of(99667), 'Order cancelled', new DateTimeImmutable(self::DATE));

        self::assertSame('CN-2026-0002', $last->getNumber());
        // 7% of 3.33 rounds to 0.23; the last credit note takes the 69.77 left.
        self::assertSame('6977', (string) $last->getTax());
        self::assertSame('2990', (string) $last->getWithholding());
        self::assertSame(InvoiceStatus::Cancelled, $invoice->getStatus());
        self::assertTrue($invoice->getBalance()->isZero());
        self::assertFalse(CreditNoteIssuer::canBeCredited($invoice));
    }

    public function testACreditNoteOnAPaidInvoiceGivesTheClientCredit(): void
    {
        $invoice = $this->issuedInvoice();
        $this->pay($invoice, 107000);
        $invoice->setStatus(InvoiceStatus::Paid);

        $this->issuer()->issue($invoice, BigInteger::of(10000), 'Discount granted afterwards', new DateTimeImmutable(self::DATE));

        $client = $invoice->getClient();
        self::assertInstanceOf(Client::class, $client);
        self::assertSame('10700', (string) $client->getCredit()->getValue()->toBigInteger());
        self::assertSame(InvoiceStatus::Paid, $invoice->getStatus());
    }

    public function testAPartlyPaidInvoiceThatIsNowCoveredIsMarkedPaid(): void
    {
        $invoice = $this->issuedInvoice();
        $this->pay($invoice, 53500);

        $this->issuer()->issue($invoice, BigInteger::of(50000), 'Half the order returned', new DateTimeImmutable(self::DATE));

        self::assertSame(InvoiceStatus::Paid, $invoice->getStatus());
        self::assertTrue($invoice->getBalance()->isZero());
    }

    public function testItRefusesMoreThanIsLeftToCredit(): void
    {
        $invoice = $this->issuedInvoice();

        $this->expectException(CreditNoteException::class);
        $this->expectExceptionMessage('credit_note.error.amount_out_of_range');

        $this->issuer()->issue($invoice, BigInteger::of(100001), 'Too much', new DateTimeImmutable(self::DATE));
    }

    public function testItRefusesADraft(): void
    {
        $invoice = $this->issuedInvoice(InvoiceStatus::Draft);

        $this->expectException(CreditNoteException::class);
        $this->expectExceptionMessage('credit_note.error.not_creditable');

        $this->issuer()->issue($invoice, BigInteger::of(100), 'Draft', new DateTimeImmutable(self::DATE));
    }

    private function issuer(): CreditNoteIssuer
    {
        $issuer = self::getContainer()->get(CreditNoteIssuer::class);
        self::assertInstanceOf(CreditNoteIssuer::class, $issuer);

        return $issuer;
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
