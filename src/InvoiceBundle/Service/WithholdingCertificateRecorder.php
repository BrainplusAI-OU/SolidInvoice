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
use Brick\Math\RoundingMode;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use SolidInvoice\CoreBundle\Billing\TotalCalculator;
use SolidInvoice\InvoiceBundle\Entity\Invoice;
use SolidInvoice\InvoiceBundle\Entity\WithholdingCertificate;
use SolidInvoice\InvoiceBundle\Enum\InvoiceStatus;
use SolidInvoice\InvoiceBundle\Exception\WithholdingCertificateException;
use function in_array;
use function strlen;
use function trim;

/**
 * Records the certificate a client gives for the tax it withheld (50 ทวิ in Thailand).
 *
 * The client pays the invoice less the withholding and pays the withholding to the Revenue
 * Department; until its certificate arrives, that part of the invoice is still owed. Recording
 * it settles that part: an invoice the client has paid net is then paid in full.
 *
 * @see \SolidInvoice\InvoiceBundle\Tests\Service\WithholdingCertificateRecorderTest
 */
final readonly class WithholdingCertificateRecorder
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TotalCalculator $totalCalculator,
        private InvoiceSettlement $settlement,
    ) {
    }

    public static function canBeCertified(Invoice $invoice): bool
    {
        return in_array($invoice->getStatus(), [InvoiceStatus::Pending, InvoiceStatus::Overdue, InvoiceStatus::Paid], true)
            && self::uncertifiedWithholding($invoice)->isPositive();
    }

    /**
     * The tax withheld from the invoice, less what credit notes took off it and what
     * certificates already cover.
     */
    public static function uncertifiedWithholding(Invoice $invoice): BigInteger
    {
        $withheld = $invoice->getWithholdingAmount()->toBigDecimal()->toScale(0, RoundingMode::HalfUp)->toBigInteger();

        foreach ($invoice->getCreditNotes() as $creditNote) {
            $withheld = $withheld->minus($creditNote->getWithholding());
        }

        return $withheld->minus($invoice->getCertifiedWithholding());
    }

    /**
     * @param array{name: string, content: string}|null $file the scanned certificate
     *
     * @throws WithholdingCertificateException
     */
    public function record(Invoice $invoice, string $number, DateTimeImmutable $date, BigInteger $amount, ?array $file = null): WithholdingCertificate
    {
        if (! self::canBeCertified($invoice)) {
            throw new WithholdingCertificateException('withholding_certificate.error.not_certifiable');
        }

        $number = trim($number);

        if ($number === '' || strlen($number) > 64) {
            throw new WithholdingCertificateException('withholding_certificate.error.number_required');
        }

        if (! $amount->isPositive() || $amount->isGreaterThan(self::uncertifiedWithholding($invoice))) {
            throw new WithholdingCertificateException('withholding_certificate.error.amount_out_of_range');
        }

        $paid = $this->settlement->paid($invoice);
        $excessBefore = $this->settlement->excess($paid, $invoice);

        $certificate = new WithholdingCertificate($invoice, $number, $date, $amount);

        if ($file !== null && $file['content'] !== '') {
            $certificate->attach($file['name'], $file['content']);
        }

        $this->entityManager->persist($certificate);
        $invoice->addWithholdingCertificate($certificate);
        $this->totalCalculator->calculateTotals($invoice);

        $this->settlement->settle($invoice, $paid, $excessBefore);

        $this->entityManager->flush();

        return $certificate;
    }
}
