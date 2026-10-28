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

namespace SolidInvoice\ExpenseBundle\Test\Factory;

use Brick\Math\BigInteger;
use DateTimeImmutable;
use SolidInvoice\CoreBundle\Test\Factory\CompanyFactory;
use SolidInvoice\ExpenseBundle\Entity\Expense;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Expense>
 */
final class ExpenseFactory extends PersistentObjectFactory
{
    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'date' => DateTimeImmutable::createFromMutable(self::faker()->dateTimeThisYear()),
            'description' => self::faker()->sentence(4),
            'currencyCode' => 'USD',
            'amount' => BigInteger::of(self::faker()->numberBetween(100, 100000)),
            'company' => CompanyFactory::random(),
        ];
    }

    public static function class(): string
    {
        return Expense::class;
    }
}
