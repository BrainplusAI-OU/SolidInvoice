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

namespace SolidInvoice\AssistantBundle\Form\Type;

use SolidInvoice\AssistantBundle\Entity\AssistantMember;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<AssistantMember>
 */
final class AssistantMemberType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', TextType::class, ['label' => 'assistant.member.form.name']);
        $builder->add('externalId', TextType::class, [
            'label' => 'assistant.member.form.external_id',
            'help' => 'assistant.member.form.external_id_help',
        ]);
        $builder->add('payer', TextType::class, [
            'label' => 'assistant.member.form.payer',
            'help' => 'assistant.member.form.payer_help',
            'required' => false,
        ]);
        $builder->add('language', TextType::class, [
            'label' => 'assistant.member.form.language',
            'help' => 'assistant.member.form.language_help',
            'required' => false,
        ]);
        $builder->add('vehiclePlates', TextType::class, [
            'label' => 'assistant.member.form.vehicle_plates',
            'help' => 'assistant.member.form.vehicle_plates_help',
            'required' => false,
        ]);
        $builder->add('active', CheckboxType::class, [
            'label' => 'assistant.member.form.active',
            'help' => 'assistant.member.form.active_help',
            'required' => false,
        ]);
        $builder->add('note', TextareaType::class, [
            'label' => 'assistant.member.form.note',
            'required' => false,
            'attr' => ['rows' => 2],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => AssistantMember::class]);
    }
}
