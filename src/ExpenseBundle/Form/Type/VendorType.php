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

namespace SolidInvoice\ExpenseBundle\Form\Type;

use SolidInvoice\ExpenseBundle\Entity\Vendor;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Vendor>
 */
final class VendorType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', null, ['label' => 'label.name']);
        $builder->add('email', EmailType::class, ['required' => false, 'label' => 'expense.vendor.form.email']);
        $builder->add('phone', null, ['required' => false, 'label' => 'expense.vendor.form.phone']);
        $builder->add('website', UrlType::class, ['required' => false, 'default_protocol' => 'https', 'label' => 'expense.vendor.form.website']);
        $builder->add('taxNumber', null, ['required' => false, 'label' => 'expense.vendor.form.tax_number']);
        $builder->add('address', TextareaType::class, ['required' => false, 'label' => 'expense.vendor.form.address']);
        $builder->add('notes', TextareaType::class, ['required' => false, 'label' => 'expense.vendor.form.notes']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Vendor::class]);
    }
}
