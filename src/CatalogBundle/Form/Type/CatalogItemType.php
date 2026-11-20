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

namespace SolidInvoice\CatalogBundle\Form\Type;

use SolidInvoice\CatalogBundle\Entity\CatalogItem;
use SolidInvoice\TaxBundle\Entity\Tax;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * @extends AbstractType<CatalogItem>
 */
final class CatalogItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $item = $options['data'] ?? null;

        $builder->add('name', TextType::class, ['label' => 'catalog.item.form.name']);
        $builder->add('description', TextareaType::class, [
            'required' => false,
            'label' => 'catalog.item.form.description',
            'help' => 'catalog.item.form.description_help',
            'attr' => ['rows' => 3],
        ]);
        $priceOptions = [
            'label' => 'catalog.item.form.price',
            'help' => 'catalog.item.form.price_help',
            'required' => false,
        ];

        // Without an item, the company's currency, which a new item takes.
        if ($item instanceof CatalogItem && $item->getCurrencyCode() !== '') {
            $priceOptions['currency'] = $item->getCurrency();
        }

        $builder->add('price', MoneyType::class, $priceOptions);
        $builder->add('tax', EntityType::class, [
            'class' => Tax::class,
            'required' => false,
            'label' => 'catalog.item.form.tax',
            'placeholder' => 'catalog.item.form.no_tax',
            // EntityType turns choice translation off, which would print the TranslatableMessage label raw.
            'choice_translation_domain' => 'messages',
            'choice_label' => static fn (Tax $tax): TranslatableMessage => new TranslatableMessage('tax.choice_label', [
                'name' => $tax->getName() ?? '',
                'rate' => $tax->getType() === Tax::TYPE_FLAT_RATE ? (string) ($tax->getRate() ?? 0) : ($tax->getRate() ?? 0) . '%',
                'compound' => $tax->isCompound() ? 'yes' : 'no',
                'category' => $tax->getCategory()->value,
            ]),
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => CatalogItem::class]);
    }
}
