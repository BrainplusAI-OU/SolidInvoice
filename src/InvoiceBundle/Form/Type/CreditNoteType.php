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

namespace SolidInvoice\InvoiceBundle\Form\Type;

use Brick\Math\BigInteger;
use Brick\Math\BigNumber;
use Money\Currency;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * @extends AbstractType<array{amount: BigNumber, reason: string, date: \DateTimeImmutable}>
 */
final class CreditNoteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $creditable = $options['creditable'];
        assert($creditable instanceof BigInteger);

        $builder->add('amount', MoneyType::class, [
            'currency' => $options['currency'],
            'label' => 'credit_note.form.amount',
            'help' => 'credit_note.form.amount_help',
            'constraints' => [
                new Assert\NotBlank(),
                new Assert\Callback(static function (?BigNumber $value, ExecutionContextInterface $context) use ($creditable): void {
                    if ($value instanceof BigNumber && (! $value->isPositive() || $value->isGreaterThan($creditable))) {
                        $context->buildViolation('credit_note.amount_out_of_range')->addViolation();
                    }
                }),
            ],
        ]);
        $builder->add('reason', TextareaType::class, [
            'label' => 'credit_note.form.reason',
            'help' => 'credit_note.form.reason_help',
            'constraints' => [new Assert\NotBlank()],
        ]);
        $builder->add('date', DateType::class, [
            'widget' => 'single_text',
            'input' => 'datetime_immutable',
            'label' => 'credit_note.form.date',
            'constraints' => [new Assert\NotNull()],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired(['currency', 'creditable']);
        $resolver->setAllowedTypes('currency', [Currency::class]);
        $resolver->setAllowedTypes('creditable', [BigInteger::class]);
    }
}
