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
use SolidInvoice\InvoiceBundle\Exception\WithholdingCertificateException;
use SolidInvoice\InvoiceBundle\Form\Type\WithholdingCertificateType;
use SolidInvoice\InvoiceBundle\Service\WithholdingCertificateRecorder;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use function assert;
use function file_get_contents;

/**
 * @see \SolidInvoice\InvoiceBundle\Tests\Action\RecordWithholdingCertificateTest
 */
final readonly class RecordWithholdingCertificate
{
    public function __construct(
        private FormFactoryInterface $formFactory,
        private RouterInterface $router,
        private WithholdingCertificateRecorder $recorder,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @return array{form: FormView, invoice: Invoice}|Response
     */
    #[Template('@SolidInvoiceInvoice/WithholdingCertificate/record.html.twig')]
    public function __invoke(Request $request, Invoice $invoice): array | Response
    {
        $session = $request->getSession();
        assert($session instanceof Session);

        if (! WithholdingCertificateRecorder::canBeCertified($invoice)) {
            $session->getFlashBag()->add('warning', 'withholding_certificate.error.not_certifiable');

            return new RedirectResponse($this->router->generate('_invoices_view', ['id' => $invoice->getId()]));
        }

        $uncertified = WithholdingCertificateRecorder::uncertifiedWithholding($invoice);

        $form = $this->formFactory->create(
            WithholdingCertificateType::class,
            ['number' => '', 'amount' => $uncertified, 'date' => CarbonImmutable::today()->toDateTimeImmutable(), 'file' => null],
            ['currency' => $invoice->getCurrency(), 'uncertified' => $uncertified],
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{number: string, date: DateTimeImmutable, amount: BigNumber, file: UploadedFile|null} $data */
            $data = $form->getData();
            $file = $data['file'] instanceof UploadedFile
                ? ['name' => $data['file']->getClientOriginalName(), 'content' => (string) file_get_contents($data['file']->getPathname())]
                : null;

            try {
                $certificate = $this->recorder->record($invoice, $data['number'], $data['date'], $data['amount']->toBigInteger(), $file);

                $session->getFlashBag()->add('success', $this->translator->trans('withholding_certificate.flash.recorded', ['%number%' => $certificate->getNumber()]));

                return new RedirectResponse($this->router->generate('_invoices_view', ['id' => $invoice->getId()]));
            } catch (WithholdingCertificateException $e) {
                $form->addError(new FormError($this->translator->trans($e->getMessage())));
            }
        }

        return ['form' => $form->createView(), 'invoice' => $invoice];
    }
}
