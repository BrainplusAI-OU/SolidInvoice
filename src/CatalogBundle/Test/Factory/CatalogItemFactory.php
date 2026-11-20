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

namespace SolidInvoice\CatalogBundle\Test\Factory;

use SolidInvoice\CatalogBundle\Entity\CatalogItem;
use SolidInvoice\CoreBundle\Test\Factory\CompanyFactory;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<CatalogItem>
 */
final class CatalogItemFactory extends PersistentObjectFactory
{
    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'name' => self::faker()->words(3, true),
            'price' => self::faker()->numberBetween(100, 1_000_000),
            'currencyCode' => 'USD',
            'company' => CompanyFactory::random(),
        ];
    }

    public static function class(): string
    {
        return CatalogItem::class;
    }
}
