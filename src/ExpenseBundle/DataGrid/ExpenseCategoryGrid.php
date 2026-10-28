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

namespace SolidInvoice\ExpenseBundle\DataGrid;

use Override;
use SolidInvoice\DataGridBundle\Attributes\AsDataGrid;
use SolidInvoice\DataGridBundle\Grid;
use SolidInvoice\DataGridBundle\GridBuilder\Action\EditAction;
use SolidInvoice\DataGridBundle\GridBuilder\Batch\BatchAction;
use SolidInvoice\DataGridBundle\GridBuilder\Column\Column;
use SolidInvoice\DataGridBundle\GridBuilder\Column\DateTimeColumn;
use SolidInvoice\DataGridBundle\GridBuilder\Column\StringColumn;
use SolidInvoice\ExpenseBundle\Entity\ExpenseCategory;
use SolidInvoice\ExpenseBundle\Repository\ExpenseCategoryRepository;

#[AsDataGrid(name: 'expense_category_grid', title: 'Expense Categories')]
final class ExpenseCategoryGrid extends Grid
{
    public function entityFQCN(): string
    {
        return ExpenseCategory::class;
    }

    /**
     * @return Column[]
     */
    #[Override]
    public function columns(): array
    {
        return [
            StringColumn::new('name')
                ->label('label.name'),
            StringColumn::new('description')
                ->label('label.description'),
            DateTimeColumn::new('created')
                ->label('label.created')
                ->format('d F Y'),
        ];
    }

    #[Override]
    public function batchActions(): iterable
    {
        return [
            BatchAction::new('action.delete')
                ->icon('trash')
                ->color('danger')
                ->confirmMessage('expense.category.grid.delete_confirm')
                ->action(static function (ExpenseCategoryRepository $repository, array $selectedItems): void {
                    $repository->delete($selectedItems);
                }),
        ];
    }

    /**
     * @return EditAction[]
     */
    #[Override]
    public function actions(): array
    {
        return [
            EditAction::new('_expense_categories_edit', ['id' => 'id']),
        ];
    }

    public function getCreateRoute(): string
    {
        return '_expense_categories_add';
    }
}
