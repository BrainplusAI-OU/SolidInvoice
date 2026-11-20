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

use Mpdf\MpdfException;
use SolidInvoice\CoreBundle\Pdf\Generator;
use SolidInvoice\CoreBundle\Response\PdfResponse;
use SolidInvoice\InvoiceBundle\Entity\CreditNote;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * The credit note as a PDF, the document sent to the client.
 *
 * @see \SolidInvoice\InvoiceBundle\Tests\Action\IssueCreditNoteTest
 */
final readonly class ViewCreditNote
{
    public function __construct(
        private Generator $pdfGenerator,
        private Environment $twig,
    ) {
    }

    /**
     * @throws LoaderError
     * @throws MpdfException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function __invoke(CreditNote $creditNote): Response
    {
        $html = $this->twig->render('@SolidInvoiceInvoice/CreditNote/pdf.html.twig', ['creditNote' => $creditNote, 'invoice' => $creditNote->getInvoice()]);

        if (! $this->pdfGenerator->canPrintPdf()) {
            return new Response($html);
        }

        return new PdfResponse($this->pdfGenerator->generate($html), sprintf('credit_note_%s.pdf', $creditNote->getNumber()));
    }
}
