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

namespace SolidInvoice\CatalogBundle\DataGrid;

use Override;
use SolidInvoice\CatalogBundle\Entity\CatalogItem;
use SolidInvoice\CatalogBundle\Repository\CatalogItemRepository;
use SolidInvoice\DataGridBundle\Attributes\AsDataGrid;
use SolidInvoice\DataGridBundle\Grid;
use SolidInvoice\DataGridBundle\GridBuilder\Action\EditAction;
use SolidInvoice\DataGridBundle\GridBuilder\Batch\BatchAction;
use SolidInvoice\DataGridBundle\GridBuilder\Column\Column;
use SolidInvoice\DataGridBundle\GridBuilder\Column\MoneyColumn;
use SolidInvoice\DataGridBundle\GridBuilder\Column\StringColumn;

#[AsDataGrid(name: 'catalog_item_grid', title: 'Items')]
final class CatalogItemGrid extends Grid
{
    public function entityFQCN(): string
    {
        return CatalogItem::class;
    }

    /**
     * @return Column[]
     */
    #[Override]
    public function columns(): array
    {
        return [
            StringColumn::new('name')
                ->label('catalog.item.form.name'),
            StringColumn::new('description')
                ->label('catalog.item.form.description')
                ->sortable(false),
            MoneyColumn::new('priceMoney')
                ->label('catalog.item.form.price')
                ->searchable(false)
                ->sortableField('price'),
            StringColumn::new('tax')
                ->label('catalog.item.form.tax')
                ->searchable(false)
                ->sortable(false),
        ];
    }

    #[Override]
    public function batchActions(): iterable
    {
        return [
            BatchAction::new('action.delete')
                ->icon('trash')
                ->color('danger')
                ->confirmMessage('catalog.item.grid.delete_confirm')
                ->action(static function (CatalogItemRepository $repository, array $selectedItems): void {
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
            EditAction::new('_catalog_items_edit', ['id' => 'id']),
        ];
    }

    public function getCreateRoute(): string
    {
        return '_catalog_items_add';
    }
}
