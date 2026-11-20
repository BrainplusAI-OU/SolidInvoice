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

namespace SolidInvoice\InvoiceBundle\Action;

use Brick\Math\BigNumber;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use SolidInvoice\InvoiceBundle\Entity\Invoice;
use SolidInvoice\InvoiceBundle\Exception\CreditNoteException;
use SolidInvoice\InvoiceBundle\Form\Type\CreditNoteType;
use SolidInvoice\InvoiceBundle\Service\CreditNoteIssuer;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use function assert;

/**
 * @see \SolidInvoice\InvoiceBundle\Tests\Action\IssueCreditNoteTest
 */
final readonly class IssueCreditNote
{
    public function __construct(
        private FormFactoryInterface $formFactory,
        private RouterInterface $router,
        private CreditNoteIssuer $issuer,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @return array{form: FormView, invoice: Invoice}|Response
     */
    #[Template('@SolidInvoiceInvoice/CreditNote/issue.html.twig')]
    public function __invoke(Request $request, Invoice $invoice): array | Response
    {
        $session = $request->getSession();
        assert($session instanceof Session);

        if (! CreditNoteIssuer::canBeCredited($invoice)) {
            $session->getFlashBag()->add('warning', 'credit_note.error.not_creditable');

            return new RedirectResponse($this->router->generate('_invoices_view', ['id' => $invoice->getId()]));
        }

        $creditable = CreditNoteIssuer::creditableAmount($invoice);

        $form = $this->formFactory->create(
            CreditNoteType::class,
            ['amount' => $creditable, 'reason' => '', 'date' => CarbonImmutable::today()->toDateTimeImmutable()],
            ['currency' => $invoice->getCurrency(), 'creditable' => $creditable],
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{amount: BigNumber, reason: string, date: DateTimeImmutable} $data */
            $data = $form->getData();

            try {
                $creditNote = $this->issuer->issue($invoice, $data['amount']->toBigInteger(), $data['reason'], $data['date']);

                $session->getFlashBag()->add('success', $this->translator->trans('credit_note.flash.issued', ['%number%' => $creditNote->getNumber()]));

                return new RedirectResponse($this->router->generate('_invoices_view', ['id' => $invoice->getId()]));
            } catch (CreditNoteException $e) {
                $form->addError(new FormError($this->translator->trans($e->getMessage())));
            }
        }

        return ['form' => $form->createView(), 'invoice' => $invoice];
    }
}
