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

namespace SolidInvoice\AssistantBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Oversight of the bots that work for a company: what they handled, who they talked to,
 * who may use them.
 *
 * A bot (an n8n workflow, a Telegram or LINE bot, a mailbox reader) lives outside the
 * application. It reports what it does to the API, and reads from it the list of people
 * allowed to use it.
 */
final class SolidInvoiceAssistantBundle extends Bundle
{
    public const NAMESPACE = __NAMESPACE__;
}
