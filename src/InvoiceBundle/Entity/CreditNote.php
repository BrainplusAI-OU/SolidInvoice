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

namespace SolidInvoice\InvoiceBundle\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Brick\Math\BigInteger;
use Brick\Math\BigNumber;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Money\Currency;
use SolidInvoice\CoreBundle\Doctrine\Type\BigIntegerType;
use SolidInvoice\CoreBundle\Traits\Entity\CompanyAware;
use SolidInvoice\CoreBundle\Traits\Entity\TimeStampable;
use SolidInvoice\InvoiceBundle\Repository\CreditNoteRepository;
use Stringable;
use Symfony\Bridge\Doctrine\IdGenerator\UlidGenerator;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Ulid;

/**
 * A reduction of an issued invoice: a mistake, a discount granted afterwards, a cancellation.
 *
 * An issued invoice is never changed; a credit note is the record that corrects it, the
 * ใบลดหนี้ of the Thai Revenue Code (section 86/10). It carries what that document must show:
 * the invoice it corrects, the value before the correction (`originalAmount`), the reduction
 * (`amount`) and the VAT on it (`tax`), and the reason. The value after the correction is
 * `originalAmount` minus `amount`.
 *
 * Amounts are in the minor unit of `currencyCode`, the invoice's frozen currency. They are set
 * once by {@see \SolidInvoice\InvoiceBundle\Service\CreditNoteIssuer} and never change.
 *
 * @see \SolidInvoice\InvoiceBundle\Tests\Service\CreditNoteIssuerTest
 */
#[ORM\Table(name: CreditNote::TABLE_NAME)]
#[ORM\UniqueConstraint(name: 'uniq_credit_notes_company_number', columns: ['company_id', 'number'])]
#[ORM\Entity(repositoryClass: CreditNoteRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
    ],
    normalizationContext: ['groups' => ['credit_note_api:read']],
)]
class CreditNote implements Stringable
{
    final public const string TABLE_NAME = 'credit_notes';

    use TimeStampable;
    use CompanyAware;

    #[ORM\Column(name: 'id', type: UlidType::NAME)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UlidGenerator::class)]
    #[Groups(['credit_note_api:read'])]
    private ?Ulid $id = null;

    #[ORM\ManyToOne(targetEntity: Invoice::class, inversedBy: 'creditNotes')]
    #[ORM\JoinColumn(name: 'invoice_id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['credit_note_api:read'])]
    private Invoice $invoice;

    #[ORM\Column(name: 'number', type: Types::STRING, length: 64)]
    #[Groups(['credit_note_api:read'])]
    private string $number;

    #[ORM\Column(name: 'credit_date', type: Types::DATE_IMMUTABLE)]
    #[Groups(['credit_note_api:read'])]
    private DateTimeImmutable $date;

    #[ORM\Column(name: 'reason', type: Types::TEXT)]
    #[Groups(['credit_note_api:read'])]
    private string $reason;

    #[ORM\Column(name: 'currency_code', type: Types::STRING, length: 3)]
    #[Groups(['credit_note_api:read'])]
    private string $currencyCode;

    /**
     * The invoice's value before tax, less earlier credit notes, when this one was issued.
     */
    #[ORM\Column(name: 'original_amount', type: BigIntegerType::NAME)]
    #[Groups(['credit_note_api:read'])]
    #[ApiProperty(openapiContext: ['type' => 'number'], jsonSchemaContext: ['type' => 'number'])]
    private BigNumber $originalAmount;

    /**
     * The reduction, before tax.
     */
    #[ORM\Column(name: 'amount', type: BigIntegerType::NAME)]
    #[Groups(['credit_note_api:read'])]
    #[ApiProperty(openapiContext: ['type' => 'number'], jsonSchemaContext: ['type' => 'number'])]
    private BigNumber $amount;

    /**
     * The tax on the reduction.
     */
    #[ORM\Column(name: 'tax', type: BigIntegerType::NAME)]
    #[Groups(['credit_note_api:read'])]
    #[ApiProperty(openapiContext: ['type' => 'number'], jsonSchemaContext: ['type' => 'number'])]
    private BigNumber $tax;

    /**
     * The tax withheld by the client on the reduction, which the client no longer withholds.
     */
    #[ORM\Column(name: 'withholding', type: BigIntegerType::NAME, options: ['default' => 0])]
    #[Groups(['credit_note_api:read'])]
    #[ApiProperty(openapiContext: ['type' => 'number'], jsonSchemaContext: ['type' => 'number'])]
    private BigNumber $withholding;

    public function __construct(
        Invoice $invoice,
        string $number,
        DateTimeImmutable $date,
        string $reason,
        Currency $currency,
        BigInteger $originalAmount,
        BigInteger $amount,
        BigInteger $tax,
        BigInteger $withholding,
    ) {
        $this->invoice = $invoice;
        $this->company = $invoice->getCompany();
        $this->number = $number;
        $this->date = $date;
        $this->reason = $reason;
        $this->currencyCode = $currency->getCode();
        $this->originalAmount = $originalAmount;
        $this->amount = $amount;
        $this->tax = $tax;
        $this->withholding = $withholding;
    }

    public function getId(): ?Ulid
    {
        return $this->id;
    }

    public function getInvoice(): Invoice
    {
        return $this->invoice;
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    public function getDate(): DateTimeImmutable
    {
        return $this->date;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getCurrency(): Currency
    {
        return new Currency($this->currencyCode);
    }

    public function getOriginalAmount(): BigInteger
    {
        return $this->originalAmount->toBigInteger();
    }

    public function getAmount(): BigInteger
    {
        return $this->amount->toBigInteger();
    }

    /**
     * The value before tax once this credit note is applied.
     */
    #[Groups(['credit_note_api:read'])]
    public function getCorrectedAmount(): BigInteger
    {
        return $this->getOriginalAmount()->minus($this->getAmount());
    }

    public function getTax(): BigInteger
    {
        return $this->tax->toBigInteger();
    }

    public function getWithholding(): BigInteger
    {
        return $this->withholding->toBigInteger();
    }

    /**
     * What the credit note takes off the invoice total: the reduction and its tax.
     */
    #[Groups(['credit_note_api:read'])]
    public function getTotal(): BigInteger
    {
        return $this->getAmount()->plus($this->getTax());
    }

    /**
     * What it takes off the amount the client pays, once the withholding is left out.
     */
    #[Groups(['credit_note_api:read'])]
    public function getPayableAmount(): BigInteger
    {
        return $this->getTotal()->minus($this->getWithholding());
    }

    public function __toString(): string
    {
        return $this->number;
    }
}
