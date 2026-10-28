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

namespace SolidInvoice\ExpenseBundle\Manager;

use Doctrine\Persistence\ManagerRegistry;
use SolidInvoice\ExpenseBundle\Entity\Expense;
use SolidInvoice\ExpenseBundle\Entity\ExpenseAttachment;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use function file_get_contents;

/**
 * Saves an expense from the web form, together with the receipts uploaded with it.
 */
final readonly class ExpenseManager
{
    public function __construct(
        private ManagerRegistry $doctrine,
    ) {
    }

    /**
     * @param iterable<UploadedFile> $receipts Already validated by the form
     */
    public function save(Expense $expense, iterable $receipts = []): void
    {
        foreach ($receipts as $receipt) {
            $attachment = new ExpenseAttachment();
            $attachment->setFileName($receipt->getClientOriginalName());
            $attachment->setContent((string) file_get_contents($receipt->getPathname()));
            $expense->addAttachment($attachment);
        }

        $em = $this->doctrine->getManager();
        $em->persist($expense);
        $em->flush();
    }
}
