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

namespace SolidInvoice\ExpenseBundle\Enum;

/**
 * How expense totals are broken down.
 */
enum ExpenseGrouping: string
{
    case Month = 'month';
    case Category = 'category';
    case Vendor = 'vendor';
}
