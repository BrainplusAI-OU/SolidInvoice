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

namespace SolidInvoice\InvoiceBundle\Service;

use Brick\Math\BigInteger;
use Brick\Math\BigNumber;
use Brick\Math\RoundingMode;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use ReflectionClass;
use SolidInvoice\CoreBundle\Billing\TotalCalculator;
use SolidInvoice\CoreBundle\Generator\BillingIdGenerator\AutoIncrementIdGenerator;
use SolidInvoice\InvoiceBundle\Entity\CreditNote;
use SolidInvoice\InvoiceBundle\Entity\Invoice;
use SolidInvoice\InvoiceBundle\Enum\InvoiceStatus;
use SolidInvoice\InvoiceBundle\Exception\CreditNoteException;

/**
 * Issues a credit note against an issued invoice and settles what it changes.
 *
 * The reduction is given before tax. Its tax and withholding are the invoice's own, in the same
 * proportion; the credit note that brings the invoice to zero takes what is left of them, so
 * rounding never leaves a cent of tax behind.
 *
 * Afterwards the invoice owes less, and {@see InvoiceSettlement} brings its status in line.
 *
 * @see \SolidInvoice\InvoiceBundle\Tests\Service\CreditNoteIssuerTest
 */
final readonly class CreditNoteIssuer
{
    public const string NUMBER_PREFIX = 'CN-';

    private const int NUMBER_WIDTH = 4;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private TotalCalculator $totalCalculator,
        private InvoiceSettlement $settlement,
        private AutoIncrementIdGenerator $numberGenerator,
    ) {
    }

    public static function canBeCredited(Invoice $invoice): bool
    {
        return in_array($invoice->getStatus(), [InvoiceStatus::Pending, InvoiceStatus::Overdue, InvoiceStatus::Paid], true)
            && self::creditableAmount($invoice)->isPositive();
    }

    /**
     * The invoice's value before tax that is not credited yet.
     */
    public static function creditableAmount(Invoice $invoice): BigInteger
    {
        return self::netTotal($invoice)->minus($invoice->getCreditedAmount());
    }

    /**
     * @throws CreditNoteException
     */
    public function issue(Invoice $invoice, BigInteger $amount, string $reason, DateTimeImmutable $date): CreditNote
    {
        if (! self::canBeCredited($invoice)) {
            throw new CreditNoteException('credit_note.error.not_creditable');
        }

        if (trim($reason) === '') {
            throw new CreditNoteException('credit_note.error.reason_required');
        }

        $creditable = self::creditableAmount($invoice);

        if (! $amount->isPositive() || $amount->isGreaterThan($creditable)) {
            throw new CreditNoteException('credit_note.error.amount_out_of_range');
        }

        $paid = $this->settlement->paid($invoice);
        $excessBefore = $this->settlement->excess($paid, $invoice);

        $creditNote = new CreditNote(
            $invoice,
            $this->nextNumber($date),
            $date,
            trim($reason),
            $invoice->getCurrency(),
            $creditable,
            $amount,
            $this->share($invoice, $invoice->getTax(), $this->creditedTax($invoice), $amount, $creditable),
            $this->share($invoice, $invoice->getWithholdingAmount(), $this->creditedWithholding($invoice), $amount, $creditable),
        );

        $this->entityManager->persist($creditNote);
        $invoice->addCreditNote($creditNote);
        $this->totalCalculator->calculateTotals($invoice);

        $this->settlement->settle($invoice, $paid, $excessBefore);

        $this->entityManager->flush();

        return $creditNote;
    }

    private static function netTotal(Invoice $invoice): BigInteger
    {
        return self::whole($invoice->getTotal()->toBigDecimal()->minus($invoice->getTax()));
    }

    /**
     * Amounts are whole minor units once stored; one still being calculated can carry decimals.
     */
    private static function whole(BigNumber $value): BigInteger
    {
        return $value->toBigDecimal()->toScale(0, RoundingMode::HalfUp)->toBigInteger();
    }

    /**
     * The part of `$invoiceValue` that goes with a reduction of `$amount`.
     */
    private function share(Invoice $invoice, BigNumber $invoiceValue, BigInteger $alreadyCredited, BigInteger $amount, BigInteger $creditable): BigInteger
    {
        $value = $invoiceValue->toBigDecimal();

        if ($amount->isEqualTo($creditable)) {
            return self::whole($value->minus($alreadyCredited));
        }

        $net = self::netTotal($invoice);

        if ($net->isZero()) {
            return BigInteger::zero();
        }

        return $value->multipliedBy($amount)->dividedBy($net, 0, RoundingMode::HalfUp)->toBigInteger();
    }

    private function creditedTax(Invoice $invoice): BigInteger
    {
        $credited = BigInteger::zero();

        foreach ($invoice->getCreditNotes() as $creditNote) {
            $credited = $credited->plus($creditNote->getTax());
        }

        return $credited;
    }

    private function creditedWithholding(Invoice $invoice): BigInteger
    {
        $credited = BigInteger::zero();

        foreach ($invoice->getCreditNotes() as $creditNote) {
            $credited = $credited->plus($creditNote->getWithholding());
        }

        return $credited;
    }

    /**
     * CN-2026-0001, numbered on from the highest credit note of the year.
     */
    private function nextNumber(DateTimeImmutable $date): string
    {
        $prefix = self::NUMBER_PREFIX . $date->format('Y') . '-';
        // The generator only reads the entity's class.
        $sequence = $this->numberGenerator->generate(new ReflectionClass(CreditNote::class)->newInstanceWithoutConstructor(), ['field' => 'number', 'prefix' => $prefix, 'suffix' => '']);

        return $prefix . str_pad($sequence, self::NUMBER_WIDTH, '0', STR_PAD_LEFT);
    }
}
