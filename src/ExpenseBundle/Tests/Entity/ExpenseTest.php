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

namespace SolidInvoice\ExpenseBundle\Tests\Entity;

use Brick\Math\BigInteger;
use PHPUnit\Framework\TestCase;
use SolidInvoice\ExpenseBundle\Entity\Expense;

final class ExpenseTest extends TestCase
{
    public function testNetAmountIsTheAmountLessItsTax(): void
    {
        $expense = new Expense();
        $expense->setAmount(BigInteger::of(10700));
        $expense->setTaxAmount(BigInteger::of(700));

        self::assertSame('10000', (string) $expense->getNetAmount()->toBigInteger());
    }

    public function testTotalIsInTheExpenseCurrency(): void
    {
        $expense = new Expense();
        $expense->setCurrencyCode('thb');
        $expense->setAmount(BigInteger::of(150050));

        $total = $expense->getTotal();

        self::assertNotNull($total);
        self::assertSame('150050', $total->getAmount());
        self::assertSame('THB', $total->getCurrency()->getCode());
    }

    public function testTotalIsNullWithoutACurrency(): void
    {
        self::assertNull(new Expense()->getTotal());
    }

    public function testAMissingTaxAmountIsZero(): void
    {
        $expense = new Expense();
        $expense->setTaxAmount(null);

        self::assertTrue($expense->getTaxAmount()->isZero());
    }
}
