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

namespace SolidInvoice\InvoiceBundle\Tests\Listener;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SolidInvoice\InvoiceBundle\Entity\Invoice;
use SolidInvoice\InvoiceBundle\Enum\InvoiceStatus;
use SolidInvoice\InvoiceBundle\Listener\IssuedInvoiceGuardListener;
use SolidInvoice\InvoiceBundle\Model\Graph;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Transition;

final class IssuedInvoiceGuardListenerTest extends TestCase
{
    /**
     * @return iterable<string, array{InvoiceStatus, ?string, bool}>
     */
    public static function invoiceProvider(): iterable
    {
        yield 'draft' => [InvoiceStatus::Draft, null, false];
        yield 'pending' => [InvoiceStatus::Pending, 'THB', true];
        yield 'overdue' => [InvoiceStatus::Overdue, 'THB', true];
        yield 'cancelled after issue' => [InvoiceStatus::Cancelled, 'THB', true];
        yield 'cancelled as a draft' => [InvoiceStatus::Cancelled, null, false];
    }

    #[DataProvider('invoiceProvider')]
    public function testOnlyAnIssuedInvoiceIsKeptFromGoingBackToDraft(InvoiceStatus $status, ?string $currency, bool $blocked): void
    {
        $invoice = new Invoice();
        $invoice->setStatus($status);
        $invoice->setCurrencyCode($currency);

        $event = new GuardEvent($invoice, new Marking([$status->value => 1]), new Transition(Graph::TRANSITION_EDIT, $status->value, InvoiceStatus::Draft->value));

        new IssuedInvoiceGuardListener()($event);

        self::assertSame($blocked, $event->isBlocked());
        self::assertSame($blocked, $invoice->isIssued());
    }
}
