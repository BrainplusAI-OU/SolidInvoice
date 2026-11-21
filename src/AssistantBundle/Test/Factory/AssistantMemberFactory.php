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

namespace SolidInvoice\AssistantBundle\Test\Factory;

use SolidInvoice\AssistantBundle\Entity\AssistantMember;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<AssistantMember>
 */
final class AssistantMemberFactory extends PersistentObjectFactory
{
    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'name' => self::faker()->firstName(),
            'externalId' => (string) self::faker()->numberBetween(100_000_000, 999_999_999),
            'assistant' => AssistantFactory::new(),
        ];
    }

    public static function class(): string
    {
        return AssistantMember::class;
    }
}
