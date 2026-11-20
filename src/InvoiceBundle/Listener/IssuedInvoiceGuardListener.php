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

namespace SolidInvoice\InvoiceBundle\Listener;

use SolidInvoice\InvoiceBundle\Entity\Invoice;
use SolidInvoice\InvoiceBundle\Model\Graph;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Event\GuardEvent;

/**
 * Keeps an issued invoice from going back to draft, through `edit` or through `reopen` after a
 * cancellation. Web, REST and MCP all ask the workflow, so this one guard covers them; a mistake
 * on an issued invoice is corrected by a credit note.
 *
 * @see \SolidInvoice\InvoiceBundle\Tests\Listener\IssuedInvoiceGuardListenerTest
 */
final class IssuedInvoiceGuardListener
{
    public const string BLOCKED_MESSAGE = 'An issued invoice cannot go back to draft. Issue a credit note to correct it.';

    /**
     * @param GuardEvent<Invoice> $event
     */
    #[AsEventListener('workflow.invoice.guard.' . Graph::TRANSITION_EDIT)]
    #[AsEventListener('workflow.invoice.guard.' . Graph::TRANSITION_REOPEN)]
    public function __invoke(GuardEvent $event): void
    {
        $invoice = $event->getSubject();

        if ($invoice instanceof Invoice && $invoice->isIssued()) {
            $event->setBlocked(true, self::BLOCKED_MESSAGE);
        }
    }
}
