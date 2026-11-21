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

namespace SolidInvoice\AssistantBundle\Enum;

enum EventDirection: string
{
    /** From a person (or a mailbox) to the bot. */
    case In = 'in';

    /** From the bot to a person. */
    case Out = 'out';

    public function getLabel(): string
    {
        return 'assistant.event.direction.' . $this->value;
    }
}
