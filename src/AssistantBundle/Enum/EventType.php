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

/**
 * What an assistant reports.
 *
 * A document is followed from the moment it arrives to the moment it is recorded (or
 * cancelled): the bot sends the same event key again as it moves, and its status changes.
 */
enum EventType: string
{
    /** A receipt or invoice to record: a photo, a PDF, an e-mail attachment. */
    case Document = 'document';

    /** Something a member wrote to the bot, or the bot wrote back. */
    case Message = 'message';

    /** An e-mail that reached the assistant's mailbox. */
    case Email = 'email';

    /** Someone who is not on the list of members tried to use the bot. */
    case Access = 'access';

    /** A message the bot sent to every member. */
    case Announcement = 'announcement';

    /** Something went wrong outside a document. */
    case Error = 'error';

    public function getLabel(): string
    {
        return 'assistant.event.type.' . $this->value;
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Document => 'file-invoice',
            self::Message => 'message-circle',
            self::Email => 'mail',
            self::Access => 'lock',
            self::Announcement => 'speakerphone',
            self::Error => 'alert-triangle',
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
}
