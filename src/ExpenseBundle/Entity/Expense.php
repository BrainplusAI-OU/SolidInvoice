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

namespace SolidInvoice\ExpenseBundle\Entity;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use Brick\Math\BigInteger;
use Brick\Math\BigNumber;
use Brick\Math\RoundingMode;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Money\Currency;
use Money\Money;
use SolidInvoice\ClientBundle\Entity\Client;
use SolidInvoice\CoreBundle\Doctrine\Type\BigIntegerType;
use SolidInvoice\CoreBundle\Traits\Entity\CompanyAware;
use SolidInvoice\CoreBundle\Traits\Entity\TimeStampable;
use SolidInvoice\ExpenseBundle\Repository\ExpenseRepository;
use SolidInvoice\InvoiceBundle\Entity\Invoice;
use Stringable;
use Symfony\Bridge\Doctrine\IdGenerator\UlidGenerator;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Money the company spent. `amount` is what was paid, tax included, in the minor unit of
 * `currencyCode`; `taxAmount` is the part of it that is tax.
 * @see \SolidInvoice\ExpenseBundle\Tests\Entity\ExpenseTest
 */
#[ORM\Table(name: Expense::TABLE_NAME)]
#[ORM\Index(name: 'idx_expenses_company_date', columns: ['company_id', 'expense_date'])]
#[ORM\Entity(repositoryClass: ExpenseRepository::class)]
#[ApiFilter(SearchFilter::class, properties: ['vendor' => 'exact', 'category' => 'exact', 'client' => 'exact', 'invoice' => 'exact', 'description' => 'partial'])]
#[ApiFilter(DateFilter::class, properties: ['date', 'created', 'updated'])]
#[ApiFilter(OrderFilter::class, properties: ['date', 'created', 'updated'])]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Post(),
        new Get(),
        new Patch(),
        new Delete(),
    ],
    normalizationContext: [
        'groups' => ['expense_api:read'],
        AbstractObjectNormalizer::SKIP_NULL_VALUES => false,
    ],
    denormalizationContext: [
        'groups' => ['expense_api:write'],
        AbstractObjectNormalizer::SKIP_NULL_VALUES => false,
    ],
)]
class Expense implements Stringable
{
    final public const string TABLE_NAME = 'expenses';

    use TimeStampable;
    use CompanyAware;

    #[ORM\Column(name: 'id', type: UlidType::NAME)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UlidGenerator::class)]
    #[Groups(['expense_api:read'])]
    private ?Ulid $id = null;

    #[ORM\Column(name: 'expense_date', type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull]
    #[Groups(['expense_api:read', 'expense_api:write'])]
    private DateTimeImmutable $date;

    #[ORM\Column(name: 'description', type: Types::STRING, length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['expense_api:read', 'expense_api:write'])]
    private string $description = '';

    #[ORM\ManyToOne(targetEntity: Vendor::class)]
    #[ORM\JoinColumn(name: 'vendor_id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['expense_api:read', 'expense_api:write'])]
    private ?Vendor $vendor = null;

    #[ORM\ManyToOne(targetEntity: ExpenseCategory::class)]
    #[ORM\JoinColumn(name: 'category_id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['expense_api:read', 'expense_api:write'])]
    private ?ExpenseCategory $category = null;

    #[ORM\Column(name: 'currency_code', type: Types::STRING, length: 3)]
    #[Assert\NotBlank]
    #[Assert\Currency]
    #[Groups(['expense_api:read', 'expense_api:write'])]
    private string $currencyCode = '';

    #[ORM\Column(name: 'amount', type: BigIntegerType::NAME)]
    #[Assert\NotNull]
    #[Groups(['expense_api:read', 'expense_api:write'])]
    #[ApiProperty(openapiContext: ['type' => 'number'], jsonSchemaContext: ['type' => 'number'])]
    private BigNumber $amount;

    #[ORM\Column(name: 'tax_amount', type: BigIntegerType::NAME, options: ['default' => 0])]
    #[Groups(['expense_api:read', 'expense_api:write'])]
    #[ApiProperty(openapiContext: ['type' => 'number'], jsonSchemaContext: ['type' => 'number'])]
    private BigNumber $taxAmount;

    #[ORM\Column(name: 'payment_method', type: Types::STRING, length: 64, nullable: true)]
    #[Assert\Length(max: 64)]
    #[Groups(['expense_api:read', 'expense_api:write'])]
    private ?string $paymentMethod = null;

    #[ORM\Column(name: 'reference', type: Types::STRING, length: 125, nullable: true)]
    #[Assert\Length(max: 125)]
    #[Groups(['expense_api:read', 'expense_api:write'])]
    private ?string $reference = null;

    #[ORM\Column(name: 'notes', type: Types::TEXT, nullable: true)]
    #[Groups(['expense_api:read', 'expense_api:write'])]
    private ?string $notes = null;

    /**
     * The client the expense was incurred for, when it is to be re-billed.
     */
    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(name: 'client_id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['expense_api:read', 'expense_api:write'])]
    private ?Client $client = null;

    #[ORM\Column(name: 'billable', type: Types::BOOLEAN, options: ['default' => false])]
    #[Groups(['expense_api:read', 'expense_api:write'])]
    private bool $billable = false;

    /**
     * The invoice the expense was re-billed on.
     */
    #[ORM\ManyToOne(targetEntity: Invoice::class)]
    #[ORM\JoinColumn(name: 'invoice_id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['expense_api:read', 'expense_api:write'])]
    private ?Invoice $invoice = null;

    /**
     * @var Collection<int, ExpenseAttachment>
     */
    #[ORM\OneToMany(targetEntity: ExpenseAttachment::class, mappedBy: 'expense', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['created' => 'ASC'])]
    #[Groups(['expense_api:read'])]
    private Collection $attachments;

    public function __construct()
    {
        $this->date = new DateTimeImmutable('today');
        $this->amount = BigInteger::zero();
        $this->taxAmount = BigInteger::zero();
        $this->attachments = new ArrayCollection();
    }

    public function getId(): ?Ulid
    {
        return $this->id;
    }

    public function getDate(): DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(DateTimeInterface $date): self
    {
        $this->date = $date instanceof DateTimeImmutable ? $date : DateTimeImmutable::createFromInterface($date);

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getVendor(): ?Vendor
    {
        return $this->vendor;
    }

    public function setVendor(?Vendor $vendor): self
    {
        $this->vendor = $vendor;

        return $this;
    }

    public function getCategory(): ?ExpenseCategory
    {
        return $this->category;
    }

    public function setCategory(?ExpenseCategory $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function getCurrencyCode(): string
    {
        return $this->currencyCode;
    }

    public function setCurrencyCode(string $currencyCode): self
    {
        $this->currencyCode = strtoupper($currencyCode);

        return $this;
    }

    public function getCurrency(): ?Currency
    {
        return $this->currencyCode === '' ? null : new Currency($this->currencyCode);
    }

    public function getAmount(): BigNumber
    {
        return $this->amount;
    }

    public function setAmount(BigNumber | int | string $amount): self
    {
        $this->amount = BigNumber::of($amount);

        return $this;
    }

    public function getTaxAmount(): BigNumber
    {
        return $this->taxAmount;
    }

    public function setTaxAmount(BigNumber | int | string | null $taxAmount): self
    {
        $this->taxAmount = BigNumber::of($taxAmount ?? 0);

        return $this;
    }

    /**
     * What was paid less the tax on it, in the minor unit.
     */
    #[Groups(['expense_api:read'])]
    #[ApiProperty(writable: false, openapiContext: ['type' => 'number'], jsonSchemaContext: ['type' => 'number'])]
    public function getNetAmount(): BigNumber
    {
        return $this->amount->toBigDecimal()->minus($this->taxAmount->toBigDecimal());
    }

    /**
     * The total as Money, for display. Null while the currency is not set.
     */
    public function getTotal(): ?Money
    {
        $currency = $this->getCurrency();

        if (! $currency instanceof Currency) {
            return null;
        }

        return new Money((string) $this->amount->toScale(0, RoundingMode::HalfEven), $currency);
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(?string $paymentMethod): self
    {
        $this->paymentMethod = $paymentMethod;

        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): self
    {
        $this->reference = $reference;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;

        return $this;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): self
    {
        $this->client = $client;

        return $this;
    }

    public function isBillable(): bool
    {
        return $this->billable;
    }

    public function setBillable(bool $billable): self
    {
        $this->billable = $billable;

        return $this;
    }

    public function getInvoice(): ?Invoice
    {
        return $this->invoice;
    }

    public function setInvoice(?Invoice $invoice): self
    {
        $this->invoice = $invoice;

        return $this;
    }

    /**
     * @return Collection<int, ExpenseAttachment>
     */
    public function getAttachments(): Collection
    {
        return $this->attachments;
    }

    public function addAttachment(ExpenseAttachment $attachment): self
    {
        if (! $this->attachments->contains($attachment)) {
            $this->attachments->add($attachment);
            $attachment->setExpense($this);
        }

        return $this;
    }

    public function removeAttachment(ExpenseAttachment $attachment): self
    {
        $this->attachments->removeElement($attachment);

        return $this;
    }

    #[Assert\Callback]
    public function validateTaxAmount(ExecutionContextInterface $context): void
    {
        // Tax is a part of the amount, so it cannot outweigh it. A refund (negative amount)
        // carries a negative tax part, hence the comparison on magnitudes.
        if ($this->taxAmount->abs()->isGreaterThan($this->amount->abs())) {
            $context->buildViolation('expense.tax_amount.exceeds_amount')
                ->atPath('taxAmount')
                ->addViolation();
        }
    }

    public function __toString(): string
    {
        return (string) $this->description;
    }
}
