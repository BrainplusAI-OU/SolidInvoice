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

use SolidInvoice\InvoiceBundle\Entity\WithholdingCertificate;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Serves the scanned certificate, with the same guards as an expense receipt.
 */
final class WithholdingCertificateFile
{
    public function __invoke(WithholdingCertificate $certificate): Response
    {
        if (! $certificate->hasFile()) {
            throw new NotFoundHttpException();
        }

        $fileName = (string) $certificate->getFileName();

        return new Response($certificate->getContent(), Response::HTTP_OK, [
            'Content-Type' => (string) $certificate->getMimeType(),
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_INLINE,
                $fileName,
                // The header's plain filename must be ASCII; the full name travels in filename*.
                (string) preg_replace('/[^\x20-\x7E]/', '_', $fileName),
            ),
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
