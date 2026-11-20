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
use DateTimeImmutable;
use Money\Currency;
use SolidInvoice\InvoiceBundle\Entity\WithholdingCertificate;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use function assert;

/**
 * @extends AbstractType<array{number: string, date: DateTimeImmutable, amount: BigNumber, file: UploadedFile|null}>
 */
final class WithholdingCertificateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $uncertified = $options['uncertified'];
        assert($uncertified instanceof BigInteger);

        $builder->add('number', TextType::class, [
            'label' => 'withholding_certificate.form.number',
            'help' => 'withholding_certificate.form.number_help',
            'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 64)],
        ]);
        $builder->add('date', DateType::class, [
            'widget' => 'single_text',
            'input' => 'datetime_immutable',
            'label' => 'withholding_certificate.form.date',
            'constraints' => [new Assert\NotNull()],
        ]);
        $builder->add('amount', MoneyType::class, [
            'currency' => $options['currency'],
            'label' => 'withholding_certificate.form.amount',
            'help' => 'withholding_certificate.form.amount_help',
            'constraints' => [
                new Assert\NotBlank(),
                new Assert\Callback(static function (?BigNumber $value, ExecutionContextInterface $context) use ($uncertified): void {
                    if ($value instanceof BigNumber && (! $value->isPositive() || $value->isGreaterThan($uncertified))) {
                        $context->buildViolation('withholding_certificate.amount_out_of_range')->addViolation();
                    }
                }),
            ],
        ]);
        $builder->add('file', FileType::class, [
            'required' => false,
            'label' => 'withholding_certificate.form.file',
            'help' => 'withholding_certificate.form.file_help',
            'attr' => ['accept' => 'application/pdf,image/*'],
            'constraints' => [
                new Assert\File(
                    maxSize: WithholdingCertificate::MAX_SIZE,
                    mimeTypes: WithholdingCertificate::ALLOWED_MIME_TYPES,
                    mimeTypesMessage: 'withholding_certificate.invalid_file_type',
                ),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired(['currency', 'uncertified']);
        $resolver->setAllowedTypes('currency', [Currency::class]);
        $resolver->setAllowedTypes('uncertified', [BigInteger::class]);
    }
}
