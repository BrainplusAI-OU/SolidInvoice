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

enum EventStatus: string
{
    /** Waiting for someone: a document not yet validated, an e-mail not yet read. */
    case Pending = 'pending';

    /** Done: the expense is recorded, the e-mail handed on. */
    case Processed = 'processed';

    /** Turned down on purpose: a document cancelled, a stranger refused. */
    case Rejected = 'rejected';

    /** Something broke on the way. */
    case Failed = 'failed';

    /** Nothing to follow: a message, a command. */
    case Info = 'info';

    public function getLabel(): string
    {
        return 'assistant.event.status.' . $this->value;
    }

    /**
     * The badge variant of the data grid's status column.
     */
    public function getVariant(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Processed => 'success',
            self::Rejected => 'secondary',
            self::Failed => 'danger',
            self::Info => 'info',
        };
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

    /**
     * @return array<string, string> value => badge variant
     */
    public static function variants(): array
    {
        $variants = [];

        foreach (self::cases() as $case) {
            $variants[$case->value] = $case->getVariant();
        }

        return $variants;
    }
}
