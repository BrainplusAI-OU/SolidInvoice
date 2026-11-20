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
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;
use SolidInvoice\CoreBundle\Doctrine\Type\BigIntegerType;
use Symfony\Bridge\Doctrine\Types\UlidType;

final class Version30200_10 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the credit_notes table';
    }

    public function isTransactional(): bool
    {
        // MySQL and MariaDB commit implicitly on DDL. AbstractMySQLPlatform, because
        // MariaDBPlatform is a sibling of MySQLPlatform rather than a subclass.
        return ! $this->platform instanceof AbstractMySQLPlatform && ! $this->platform instanceof OraclePlatform;
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('credit_notes')) {
            return;
        }

        $table = $schema->createTable('credit_notes');
        $table->addColumn('id', UlidType::NAME);
        $table->addColumn('company_id', UlidType::NAME);
        $table->addColumn('invoice_id', UlidType::NAME);
        $table->addColumn('number', Types::STRING, ['length' => 64]);
        $table->addColumn('credit_date', Types::DATE_IMMUTABLE);
        $table->addColumn('reason', Types::TEXT);
        $table->addColumn('currency_code', Types::STRING, ['length' => 3]);
        $table->addColumn('original_amount', BigIntegerType::NAME);
        $table->addColumn('amount', BigIntegerType::NAME);
        $table->addColumn('tax', BigIntegerType::NAME);
        $table->addColumn('withholding', BigIntegerType::NAME, ['default' => 0]);
        $table->addColumn('created', Types::DATETIME_IMMUTABLE);
        $table->addColumn('updated', Types::DATETIME_IMMUTABLE);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['company_id', 'number'], 'uniq_credit_notes_company_number');
        $table->addIndex(['invoice_id']);
        $table->addForeignKeyConstraint('companies', ['company_id'], ['id'], ['onDelete' => 'CASCADE']);
        $table->addForeignKeyConstraint('invoices', ['invoice_id'], ['id'], ['onDelete' => 'CASCADE']);
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('credit_notes')) {
            $schema->dropTable('credit_notes');
        }
    }
}
