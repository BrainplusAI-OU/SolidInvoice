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

namespace SolidInvoice\ExpenseBundle\Menu;

use Knp\Menu\ItemInterface;
use SolidInvoice\CoreBundle\Enum\Menu\MenuPriority;
use SolidWorx\Platform\PlatformBundle\Attributes\Menu\MenuBuilder;

final readonly class ExpenseMenu
{
    #[MenuBuilder(name: 'sidebar', priority: MenuPriority::PRIORITY_EXPENSE->value)]
    public function sidebar(ItemInterface $menu): void
    {
        $section = $menu->addChild(
            'expense.menu.main',
            [
                'extras' => ['icon' => 'receipt-2'],
            ],
        );

        $section->addChild(
            'expense.menu.main',
            [
                'route' => '_expenses_index',
                'extras' => ['icon' => 'receipt-2'],
            ],
        );

        $section->addChild(
            'expense.menu.vendors',
            [
                'route' => '_expense_vendors',
                'extras' => ['icon' => 'building-store'],
            ],
        );

        $section->addChild(
            'expense.menu.categories',
            [
                'route' => '_expense_categories',
                'extras' => ['icon' => 'tag'],
            ],
        );
    }
}
