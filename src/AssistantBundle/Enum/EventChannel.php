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

enum EventChannel: string
{
    case Telegram = 'telegram';
    case Line = 'line';
    case Whatsapp = 'whatsapp';
    case Email = 'email';
    case System = 'system';

    public function getLabel(): string
    {
        return 'assistant.event.channel.' . $this->value;
    }

    /**
     * @return array<string, string> value => label, for a grid filter
     */
    public static function choices(): array
    {
        $choices = [];

        foreach (self::cases() as $case) {
            $choices[$case->value] = $case->getLabel();
        }

        return $choices;
    }
}
