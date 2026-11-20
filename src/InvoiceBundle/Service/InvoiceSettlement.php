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

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTimeImmutable;
use SolidInvoice\ClientBundle\Entity\Client;
use SolidInvoice\ClientBundle\Repository\CreditRepository;
use SolidInvoice\InvoiceBundle\Entity\Invoice;
use SolidInvoice\InvoiceBundle\Enum\InvoiceStatus;
use SolidInvoice\InvoiceBundle\Model\Graph;
use SolidInvoice\PaymentBundle\Repository\PaymentRepository;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Brings an issued invoice's status in line with what it now owes, after a credit note or a
 * withholding certificate lowered it.
 *
 * Credited in full with nothing paid or certified: cancelled. Covered by what was paid and
 * certified: paid, and any payment beyond what it owes becomes the client's credit, as for any
 * overpayment. Already paid: the overpayment it now carries becomes credit.
 *
 * @see \SolidInvoice\InvoiceBundle\Tests\Service\CreditNoteIssuerTest
 * @see \SolidInvoice\InvoiceBundle\Tests\Service\WithholdingCertificateRecorderTest
 */
final readonly class InvoiceSettlement
{
    public function __construct(
        private PaymentRepository $paymentRepository,
        private CreditRepository $creditRepository,
        private WorkflowInterface $invoiceStateMachine,
    ) {
    }

    public function paid(Invoice $invoice): BigDecimal
    {
        return $this->paymentRepository->getTotalPaidForInvoice($invoice)->toBigDecimal();
    }

    /**
     * What the client paid beyond what the invoice owes.
     */
    public function excess(BigDecimal $paid, Invoice $invoice): BigDecimal
    {
        $excess = $paid->minus($invoice->getAmountOwed());

        return $excess->isPositive() ? $excess : BigDecimal::zero();
    }

    /**
     * @param BigDecimal $paid         What the client had paid, before the change
     * @param BigDecimal $excessBefore {@see excess()} before the change
     */
    public function settle(Invoice $invoice, BigDecimal $paid, BigDecimal $excessBefore): void
    {
        if ($invoice->getStatus() === InvoiceStatus::Paid) {
            $refund = $this->excess($paid, $invoice)->minus($excessBefore);
            $client = $invoice->getClient();

            if ($refund->isPositive() && $client instanceof Client) {
                $this->creditRepository->addCredit($client, $refund->toScale(0, RoundingMode::HalfUp)->toBigInteger());
            }

            return;
        }

        if ($paid->isZero() && $invoice->getCertifiedWithholding()->isZero() && CreditNoteIssuer::creditableAmount($invoice)->isZero()) {
            $this->invoiceStateMachine->apply($invoice, Graph::TRANSITION_CANCEL);

            return;
        }

        if (! $invoice->getBalance()->isPositive() && $this->invoiceStateMachine->can($invoice, Graph::TRANSITION_PAY)) {
            // InvoicePaidListener turns any excess into credit for the client.
            $invoice->setPaidDate(new DateTimeImmutable());
            $this->invoiceStateMachine->apply($invoice, Graph::TRANSITION_PAY);
        }
    }
}
