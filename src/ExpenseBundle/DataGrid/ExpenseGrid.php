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
use SolidInvoice\DataGridBundle\GridBuilder\Column\MoneyColumn;
use SolidInvoice\DataGridBundle\GridBuilder\Column\StringColumn;
use SolidInvoice\DataGridBundle\GridBuilder\Filter\DateRangeFilter;
use SolidInvoice\DataGridBundle\GridBuilder\Filter\EntityFilter;
use SolidInvoice\ExpenseBundle\Entity\Expense;
use SolidInvoice\ExpenseBundle\Entity\ExpenseCategory;
use SolidInvoice\ExpenseBundle\Entity\Vendor;
use SolidInvoice\ExpenseBundle\Repository\ExpenseRepository;
use Symfony\Component\Translation\TranslatableMessage;

#[AsDataGrid(name: 'expense_grid', title: 'Expenses')]
final class ExpenseGrid extends Grid
{
    public function entityFQCN(): string
    {
        return Expense::class;
    }

    /**
     * @return Column[]
     */
    #[Override]
    public function columns(): array
    {
        return [
            DateTimeColumn::new('date')
                ->label('expense.grid.date')
                ->format('d F Y')
                ->filter(new DateRangeFilter('date')),
            StringColumn::new('description')
                ->label('label.description'),
            StringColumn::new('vendor')
                ->label('expense.grid.vendor')
                ->searchable(false)
                ->filter(EntityFilter::new(Vendor::class, 'vendor', 'name')->multiple()),
            StringColumn::new('category')
                ->label('expense.grid.category')
                ->searchable(false)
                ->filter(EntityFilter::new(ExpenseCategory::class, 'category', 'name')->multiple()),
            StringColumn::new('client')
                ->label('label.client')
                ->searchable(false)
                ->linkToRoute('_clients_view', ['id' => 'client.id']),
            MoneyColumn::new('total')
                ->label('expense.grid.amount')
                ->searchable(false)
                ->sortableField('amount'),
        ];
    }

    #[Override]
    public function batchActions(): iterable
    {
        return [
            BatchAction::new('action.delete')
                ->icon('trash')
                ->color('danger')
                ->confirmMessage('expense.grid.delete_confirm')
                ->action(static function (ExpenseRepository $repository, array $selectedItems): void {
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
            EditAction::new('_expenses_edit', ['id' => 'id']),
        ];
    }

    public function getCreateRoute(): string
    {
        return '_expenses_add';
    }

    #[Override]
    public function getEmptyTitle(): TranslatableMessage
    {
        return new TranslatableMessage('datagrid.empty.expense.title');
    }

    #[Override]
    public function getEmptyDescription(): TranslatableMessage
    {
        return new TranslatableMessage('datagrid.empty.expense.description');
    }
}
