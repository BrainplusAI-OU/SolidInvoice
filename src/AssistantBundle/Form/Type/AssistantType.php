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

use SolidInvoice\AssistantBundle\Entity\Assistant;
use SolidInvoice\AssistantBundle\Enum\EventChannel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Assistant>
 */
final class AssistantType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', TextType::class, [
            'label' => 'assistant.form.name',
            'help' => 'assistant.form.name_help',
        ]);
        $builder->add('channel', EnumType::class, [
            'class' => EventChannel::class,
            'label' => 'assistant.form.channel',
            'choice_label' => static fn (EventChannel $channel): string => $channel->getLabel(),
            'choice_filter' => static fn (?EventChannel $channel): bool => $channel !== EventChannel::System,
        ]);
        $builder->add('inboxEmail', EmailType::class, [
            'label' => 'assistant.form.inbox_email',
            'help' => 'assistant.form.inbox_email_help',
            'required' => false,
        ]);
        $builder->add('description', TextareaType::class, [
            'label' => 'assistant.form.description',
            'required' => false,
            'attr' => ['rows' => 3],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Assistant::class]);
    }
}
