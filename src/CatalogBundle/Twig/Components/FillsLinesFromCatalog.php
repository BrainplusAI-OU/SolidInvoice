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

namespace SolidInvoice\CatalogBundle\Twig\Components;

use Money\Currency;
use SolidInvoice\CatalogBundle\Entity\CatalogItem;
use SolidInvoice\CatalogBundle\Repository\CatalogItemRepository;
use SolidInvoice\TaxBundle\Entity\Tax;
use SolidInvoice\TaxBundle\Repository\TaxRepository;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Uid\Ulid;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;
use function array_key_last;
use function array_values;
use function is_array;
use function trim;

/**
 * "Add from the catalog" for the line editor of the invoice, recurring invoice and quote
 * forms: picking an item writes a line with its name, description, price and tax.
 *
 * The line goes through the submitted values, like a typed one, so the form validates and
 * totals it the same way. The price is copied only onto a document in the item's currency;
 * otherwise it is left to type.
 */
trait FillsLinesFromCatalog
{
    private CatalogItemRepository $catalogItems;

    private FormFactoryInterface $catalogFormFactory;

    private TaxRepository $catalogTaxes;

    #[Required]
    public function setCatalog(
        CatalogItemRepository $catalogItems,
        FormFactoryInterface $catalogFormFactory,
        TaxRepository $catalogTaxes
    ): void {
        $this->catalogItems = $catalogItems;
        $this->catalogFormFactory = $catalogFormFactory;
        $this->catalogTaxes = $catalogTaxes;
    }

    /**
     * @return list<CatalogItem>
     */
    #[ExposeInTemplate]
    public function getCatalogItems(): array
    {
        return $this->catalogItems->findAllByName();
    }

    #[LiveAction]
    public function addCatalogItem(#[LiveArg] string $item): void
    {
        if (! Ulid::isValid($item, Ulid::FORMAT_BASE_32)) {
            return;
        }

        $catalogItem = $this->catalogItems->find(Ulid::fromString($item));

        if (! $catalogItem instanceof CatalogItem) {
            return;
        }

        $line = [
            'name' => $catalogItem->getName(),
            'description' => (string) $catalogItem->getDescription(),
            'price' => '',
            'qty' => '1',
        ];

        $currency = $this->getForm()->getConfig()->getOption('currency');

        if ($currency instanceof Currency && $currency->equals($catalogItem->getCurrency()) && ! $catalogItem->getPrice()->isZero()) {
            $line['price'] = $this->priceViewValue($catalogItem, $currency);
        }

        $tax = $catalogItem->getTax();

        // A line has its taxes field only once tax rates exist; see ItemType.
        if ($tax instanceof Tax && $this->catalogTaxes->taxRatesConfigured()) {
            $line['taxes'] = [['tax' => (string) $tax->getId(), 'sequence' => '0']];
        }

        $lines = $this->formValues['lines'] ?? [];
        $lines = is_array($lines) ? array_values($lines) : [];

        // The blank line a new document starts with is taken rather than left above.
        $last = array_key_last($lines);

        if ($last !== null && self::isBlankLine($lines[$last])) {
            $lines[$last] = $line;
        } else {
            $lines[] = $line;
        }

        $this->formValues['lines'] = $lines;
    }

    /**
     * The price as the line's money field shows it, in the request's locale.
     */
    private function priceViewValue(CatalogItem $item, Currency $currency): string
    {
        return (string) $this->catalogFormFactory
            ->create(MoneyType::class, $item->getPrice(), ['currency' => $currency, 'data_class' => null])
            ->createView()
            ->vars['value'];
    }

    private static function isBlankLine(mixed $line): bool
    {
        if (! is_array($line)) {
            return true;
        }

        foreach (['name', 'description', 'price'] as $field) {
            $value = $line[$field] ?? '';

            if (is_array($value) || trim((string) $value) !== '' && (string) $value !== '0' && (string) $value !== '0.00') {
                return false;
            }
        }

        return true;
    }
}
