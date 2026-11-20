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

namespace SolidInvoice\CatalogBundle\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use Brick\Math\BigDecimal;
use Brick\Math\BigNumber;
use Brick\Math\RoundingMode;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Money\Currency;
use Money\Money;
use SolidInvoice\CatalogBundle\Repository\CatalogItemRepository;
use SolidInvoice\CoreBundle\Doctrine\Type\BigIntegerType;
use SolidInvoice\CoreBundle\Traits\Entity\CompanyAware;
use SolidInvoice\CoreBundle\Traits\Entity\TimeStampable;
use SolidInvoice\TaxBundle\Entity\Tax;
use Stringable;
use Symfony\Bridge\Doctrine\IdGenerator\UlidGenerator;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Something the company sells, kept to fill an invoice or quote line from.
 *
 * The price is in the minor unit of `currency`, the company's currency when the item was
 * created. It is copied onto a line only when the document is in that same currency.
 */
#[ORM\Table(name: CatalogItem::TABLE_NAME)]
#[ORM\Entity(repositoryClass: CatalogItemRepository::class)]
#[ApiFilter(SearchFilter::class, properties: ['name' => 'partial'])]
#[ApiResource(
    shortName: 'CatalogItem',
    operations: [
        new GetCollection(),
        new Post(),
        new Get(),
        new Patch(),
        new Delete(),
    ],
    normalizationContext: [
        'groups' => ['catalog_item_api:read'],
        AbstractObjectNormalizer::SKIP_NULL_VALUES => false,
    ],
    denormalizationContext: [
        'groups' => ['catalog_item_api:write'],
        AbstractObjectNormalizer::SKIP_NULL_VALUES => false,
    ],
)]
class CatalogItem implements Stringable
{
    final public const string TABLE_NAME = 'catalog_items';

    use TimeStampable;
    use CompanyAware;

    #[ORM\Column(name: 'id', type: UlidType::NAME)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UlidGenerator::class)]
    #[Groups(['catalog_item_api:read'])]
    private ?Ulid $id = null;

    #[ORM\Column(name: 'name', type: Types::STRING, length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['catalog_item_api:read', 'catalog_item_api:write'])]
    private string $name = '';

    #[ORM\Column(name: 'description', type: Types::TEXT, nullable: true)]
    #[Groups(['catalog_item_api:read', 'catalog_item_api:write'])]
    private ?string $description = null;

    /**
     * Zero leaves the price to be typed on each line, for an item whose price changes.
     */
    #[ORM\Column(name: 'price_amount', type: BigIntegerType::NAME)]
    #[Assert\NotNull]
    #[Groups(['catalog_item_api:read', 'catalog_item_api:write'])]
    #[ApiProperty(openapiContext: ['type' => 'number'], jsonSchemaContext: ['type' => 'number'])]
    private BigNumber $price;

    #[ORM\Column(name: 'currency_code', type: Types::STRING, length: 3)]
    #[Assert\NotBlank]
    #[Assert\Currency]
    #[Groups(['catalog_item_api:read', 'catalog_item_api:write'])]
    private string $currencyCode = '';

    #[ORM\ManyToOne(targetEntity: Tax::class)]
    #[ORM\JoinColumn(name: 'tax_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['catalog_item_api:read', 'catalog_item_api:write'])]
    #[ApiProperty(readableLink: false, writableLink: false)]
    private ?Tax $tax = null;

    public function __construct()
    {
        $this->price = BigDecimal::zero();
    }

    public function getId(): ?Ulid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getPrice(): BigNumber
    {
        return $this->price;
    }

    public function setPrice(BigNumber | int | string $price): self
    {
        $this->price = BigNumber::of($price);

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

    public function getCurrency(): Currency
    {
        return new Currency($this->currencyCode);
    }

    /**
     * The price with its currency, for display.
     */
    public function getPriceMoney(): Money
    {
        return new Money((string) $this->price->toBigDecimal()->toScale(0, RoundingMode::HalfEven), $this->getCurrency());
    }

    public function getTax(): ?Tax
    {
        return $this->tax;
    }

    public function setTax(?Tax $tax): self
    {
        $this->tax = $tax;

        return $this;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
