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
use SolidInvoice\ExpenseBundle\Entity\Vendor;
use SolidInvoice\ExpenseBundle\Repository\VendorRepository;

#[AsDataGrid(name: 'vendor_grid', title: 'Vendors')]
final class VendorGrid extends Grid
{
    public function entityFQCN(): string
    {
        return Vendor::class;
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
            StringColumn::new('email')
                ->label('expense.vendor.form.email'),
            StringColumn::new('taxNumber')
                ->label('expense.vendor.form.tax_number'),
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
                ->confirmMessage('expense.vendor.grid.delete_confirm')
                ->action(static function (VendorRepository $repository, array $selectedItems): void {
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
            EditAction::new('_expense_vendors_edit', ['id' => 'id']),
        ];
    }

    public function getCreateRoute(): string
    {
        return '_expense_vendors_add';
    }
}
