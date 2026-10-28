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

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;
use SolidInvoice\CoreBundle\Doctrine\Type\BigIntegerType;
use Symfony\Bridge\Doctrine\Types\UlidType;

final class Version30200_9 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the expenses, expense_vendors, expense_categories and expense_attachments tables';
    }

    public function isTransactional(): bool
    {
        // MySQL and MariaDB commit implicitly on DDL. AbstractMySQLPlatform, because
        // MariaDBPlatform is a sibling of MySQLPlatform rather than a subclass.
        return ! $this->platform instanceof AbstractMySQLPlatform && ! $this->platform instanceof OraclePlatform;
    }

    public function up(Schema $schema): void
    {
        if (! $schema->hasTable('expense_vendors')) {
            $vendors = $schema->createTable('expense_vendors');
            $this->addCommonColumns($vendors);
            $vendors->addColumn('name', Types::STRING, ['length' => 125]);
            $vendors->addColumn('email', Types::STRING, ['length' => 255, 'notnull' => false]);
            $vendors->addColumn('phone', Types::STRING, ['length' => 64, 'notnull' => false]);
            $vendors->addColumn('website', Types::STRING, ['length' => 255, 'notnull' => false]);
            $vendors->addColumn('tax_number', Types::STRING, ['length' => 64, 'notnull' => false]);
            $vendors->addColumn('address', Types::TEXT, ['notnull' => false]);
            $vendors->addColumn('notes', Types::TEXT, ['notnull' => false]);
        }

        if (! $schema->hasTable('expense_categories')) {
            $categories = $schema->createTable('expense_categories');
            $this->addCommonColumns($categories);
            $categories->addColumn('name', Types::STRING, ['length' => 125]);
            $categories->addColumn('description', Types::TEXT, ['notnull' => false]);
        }

        if (! $schema->hasTable('expenses')) {
            $expenses = $schema->createTable('expenses');
            $this->addCommonColumns($expenses);
            $expenses->addColumn('expense_date', Types::DATE_IMMUTABLE);
            $expenses->addColumn('description', Types::STRING, ['length' => 255]);
            $expenses->addColumn('vendor_id', UlidType::NAME, ['notnull' => false]);
            $expenses->addColumn('category_id', UlidType::NAME, ['notnull' => false]);
            $expenses->addColumn('currency_code', Types::STRING, ['length' => 3]);
            $expenses->addColumn('amount', BigIntegerType::NAME);
            $expenses->addColumn('tax_amount', BigIntegerType::NAME, ['default' => 0]);
            $expenses->addColumn('payment_method', Types::STRING, ['length' => 64, 'notnull' => false]);
            $expenses->addColumn('reference', Types::STRING, ['length' => 125, 'notnull' => false]);
            $expenses->addColumn('notes', Types::TEXT, ['notnull' => false]);
            $expenses->addColumn('client_id', UlidType::NAME, ['notnull' => false]);
            $expenses->addColumn('billable', Types::BOOLEAN, ['default' => false]);
            $expenses->addColumn('invoice_id', UlidType::NAME, ['notnull' => false]);
            // company_id leads: every read is scoped to one company, then ranges over dates.
            $expenses->addIndex(['company_id', 'expense_date'], 'idx_expenses_company_date');
            $expenses->addForeignKeyConstraint('expense_vendors', ['vendor_id'], ['id'], ['onDelete' => 'SET NULL']);
            $expenses->addForeignKeyConstraint('expense_categories', ['category_id'], ['id'], ['onDelete' => 'SET NULL']);
            $expenses->addForeignKeyConstraint('clients', ['client_id'], ['id'], ['onDelete' => 'SET NULL']);
            $expenses->addForeignKeyConstraint('invoices', ['invoice_id'], ['id'], ['onDelete' => 'SET NULL']);
        }

        if (! $schema->hasTable('expense_attachments')) {
            $attachments = $schema->createTable('expense_attachments');
            $this->addCommonColumns($attachments);
            $attachments->addColumn('expense_id', UlidType::NAME);
            $attachments->addColumn('file_name', Types::STRING, ['length' => 255]);
            $attachments->addColumn('mime_type', Types::STRING, ['length' => 127]);
            $attachments->addColumn('file_size', Types::INTEGER);
            $attachments->addColumn('content', Types::BLOB);
            $attachments->addForeignKeyConstraint('expenses', ['expense_id'], ['id'], ['onDelete' => 'CASCADE']);
        }
    }

    public function down(Schema $schema): void
    {
        foreach (['expense_attachments', 'expenses', 'expense_categories', 'expense_vendors'] as $table) {
            if ($schema->hasTable($table)) {
                $schema->dropTable($table);
            }
        }
    }

    private function addCommonColumns(Table $table): void
    {
        $table->addColumn('id', UlidType::NAME);
        $table->addColumn('company_id', UlidType::NAME);
        $table->addColumn('created', Types::DATETIME_IMMUTABLE);
        $table->addColumn('updated', Types::DATETIME_IMMUTABLE);
        $table->setPrimaryKey(['id']);
        $table->addForeignKeyConstraint('companies', ['company_id'], ['id'], ['onDelete' => 'CASCADE']);
    }
}
