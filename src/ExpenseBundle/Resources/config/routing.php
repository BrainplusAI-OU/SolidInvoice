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

use SolidInvoice\ExpenseBundle\Action\Add;
use SolidInvoice\ExpenseBundle\Action\Attachment;
use SolidInvoice\ExpenseBundle\Action\CategoryAdd;
use SolidInvoice\ExpenseBundle\Action\CategoryEdit;
use SolidInvoice\ExpenseBundle\Action\CategoryIndex;
use SolidInvoice\ExpenseBundle\Action\DeleteAttachment;
use SolidInvoice\ExpenseBundle\Action\Edit;
use SolidInvoice\ExpenseBundle\Action\Index;
use SolidInvoice\ExpenseBundle\Action\VendorAdd;
use SolidInvoice\ExpenseBundle\Action\VendorEdit;
use SolidInvoice\ExpenseBundle\Action\VendorIndex;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routingConfigurator): void {
    $routingConfigurator
        ->add('_expenses_index', '/')
        ->controller(Index::class);

    $routingConfigurator
        ->add('_expenses_add', '/add')
        ->controller(Add::class);

    $routingConfigurator
        ->add('_expenses_edit', '/edit/{id}')
        ->controller(Edit::class);

    $routingConfigurator
        ->add('_expenses_attachment', '/attachments/{id}')
        ->controller(Attachment::class)
        ->methods(['GET']);

    $routingConfigurator
        ->add('_expenses_attachment_delete', '/attachments/{id}/delete')
        ->controller(DeleteAttachment::class)
        ->methods(['POST']);

    $routingConfigurator
        ->add('_expense_vendors', '/vendors')
        ->controller(VendorIndex::class);

    $routingConfigurator
        ->add('_expense_vendors_add', '/vendors/add')
        ->controller(VendorAdd::class);

    $routingConfigurator
        ->add('_expense_vendors_edit', '/vendors/edit/{id}')
        ->controller(VendorEdit::class);

    $routingConfigurator
        ->add('_expense_categories', '/categories')
        ->controller(CategoryIndex::class);

    $routingConfigurator
        ->add('_expense_categories_add', '/categories/add')
        ->controller(CategoryAdd::class);

    $routingConfigurator
        ->add('_expense_categories_edit', '/categories/edit/{id}')
        ->controller(CategoryEdit::class);
};
