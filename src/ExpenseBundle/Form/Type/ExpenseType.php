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

use Doctrine\ORM\EntityRepository;
use Money\Currency;
use SolidInvoice\ClientBundle\Entity\Client;
use SolidInvoice\ExpenseBundle\Entity\Expense;
use SolidInvoice\ExpenseBundle\Entity\ExpenseAttachment;
use SolidInvoice\ExpenseBundle\Entity\ExpenseCategory;
use SolidInvoice\ExpenseBundle\Entity\Vendor;
use SolidInvoice\InvoiceBundle\Entity\Invoice;
use SolidInvoice\MoneyBundle\Form\Type\CurrencyType;
use SolidInvoice\SettingsBundle\SystemConfig;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\File;
use Throwable;
use function is_array;
use function is_string;

/**
 * @extends AbstractType<Expense>
 */
final class ExpenseType extends AbstractType
{
    public function __construct(
        private readonly SystemConfig $config,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('date', DateType::class, ['widget' => 'single_text', 'input' => 'datetime_immutable', 'label' => 'expense.form.date']);
        $builder->add('description', null, ['label' => 'label.description']);
        $builder->add('vendor', EntityType::class, [
            'class' => Vendor::class,
            'required' => false,
            'placeholder' => 'expense.form.vendor_placeholder',
            'label' => 'expense.form.vendor',
            'query_builder' => static fn (EntityRepository $repository) => $repository->createQueryBuilder('v')->orderBy('v.name', 'ASC'),
        ]);
        $builder->add('category', EntityType::class, [
            'class' => ExpenseCategory::class,
            'required' => false,
            'placeholder' => 'expense.form.category_placeholder',
            'label' => 'expense.form.category',
            'query_builder' => static fn (EntityRepository $repository) => $repository->createQueryBuilder('c')->orderBy('c.name', 'ASC'),
        ]);
        $builder->add('currencyCode', CurrencyType::class, ['label' => 'expense.form.currency']);
        $this->addAmounts($builder, $options['currency']);
        $builder->add('paymentMethod', null, ['required' => false, 'label' => 'expense.form.payment_method']);
        $builder->add('reference', null, ['required' => false, 'label' => 'expense.form.reference', 'help' => 'expense.form.reference_help']);
        $builder->add('client', EntityType::class, [
            'class' => Client::class,
            'required' => false,
            'placeholder' => 'expense.form.client_placeholder',
            'label' => 'label.client',
            'query_builder' => static fn (EntityRepository $repository) => $repository->createQueryBuilder('c')->orderBy('c.name', 'ASC'),
        ]);
        $builder->add('billable', CheckboxType::class, ['required' => false, 'label' => 'expense.form.billable', 'help' => 'expense.form.billable_help']);
        $builder->add('invoice', EntityType::class, [
            'class' => Invoice::class,
            'required' => false,
            'placeholder' => 'expense.form.invoice_placeholder',
            'label' => 'label.invoice',
            'choice_label' => static fn (Invoice $invoice): string => $invoice->getInvoiceId(),
            'query_builder' => static fn (EntityRepository $repository) => $repository->createQueryBuilder('i')->orderBy('i.invoiceDate', 'DESC'),
        ]);
        $builder->add('notes', TextareaType::class, ['required' => false, 'label' => 'expense.form.notes']);
        $builder->add('receipts', FileType::class, [
            'mapped' => false,
            'multiple' => true,
            'required' => false,
            'label' => 'expense.form.receipts',
            'help' => 'expense.form.receipts_help',
            'attr' => ['accept' => 'application/pdf,image/*'],
            'constraints' => [
                new All(constraints: [
                    new File(
                        maxSize: ExpenseAttachment::MAX_SIZE,
                        mimeTypes: ExpenseAttachment::ALLOWED_MIME_TYPES,
                        mimeTypesMessage: 'expense.attachment.invalid_type',
                    ),
                ]),
            ],
        ]);

        // The amounts are scaled by the currency's own number of decimals, so they are rebuilt
        // for the currency that was submitted rather than the one the form was opened with.
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            $data = $event->getData();

            if (! is_array($data) || ! is_string($data['currencyCode'] ?? null) || $data['currencyCode'] === '') {
                return;
            }

            try {
                $currency = new Currency($data['currencyCode']);
            } catch (Throwable) {
                // An invalid code is reported by the currency field itself.
                return;
            }

            $this->addAmounts($event->getForm(), $currency);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Expense::class,
            'currency' => function (Options $options): Currency {
                $expense = $options['data'] ?? null;

                return $expense instanceof Expense && $expense->getCurrency() instanceof Currency
                    ? $expense->getCurrency()
                    : $this->config->getCurrency();
            },
        ]);

        $resolver->setAllowedTypes('currency', [Currency::class]);
    }

    /**
     * @param FormBuilderInterface<mixed>|FormInterface<mixed> $form
     */
    private function addAmounts(FormBuilderInterface | FormInterface $form, Currency $currency): void
    {
        $form->add('amount', MoneyType::class, ['currency' => $currency, 'label' => 'expense.form.amount', 'help' => 'expense.form.amount_help']);
        $form->add('taxAmount', MoneyType::class, ['currency' => $currency, 'required' => false, 'label' => 'expense.form.tax_amount', 'help' => 'expense.form.tax_amount_help']);
    }
}
