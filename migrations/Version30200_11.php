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

final class Version30200_11 extends AbstractMigration
{
    private const array COLUMNS = ['exchange_rate', 'exchange_rate_currency', 'exchange_rate_date', 'exchange_rate_source'];

    public function getDescription(): string
    {
        return 'Record on each invoice the exchange rate to the company currency, frozen when it is issued';
    }

    public function isTransactional(): bool
    {
        // MySQL and MariaDB commit implicitly on DDL. AbstractMySQLPlatform, because
        // MariaDBPlatform is a sibling of MySQLPlatform rather than a subclass.
        return ! $this->platform instanceof AbstractMySQLPlatform && ! $this->platform instanceof OraclePlatform;
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('invoices');

        if (! $table->hasColumn('exchange_rate')) {
            $table->addColumn('exchange_rate', Types::DECIMAL, ['precision' => 20, 'scale' => 8, 'notnull' => false]);
        }

        if (! $table->hasColumn('exchange_rate_currency')) {
            $table->addColumn('exchange_rate_currency', Types::STRING, ['length' => 3, 'notnull' => false]);
        }

        if (! $table->hasColumn('exchange_rate_date')) {
            $table->addColumn('exchange_rate_date', Types::DATE_IMMUTABLE, ['notnull' => false]);
        }

        if (! $table->hasColumn('exchange_rate_source')) {
            $table->addColumn('exchange_rate_source', Types::STRING, ['length' => 32, 'notnull' => false]);
        }
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('invoices');

        foreach (self::COLUMNS as $column) {
            if ($table->hasColumn($column)) {
                $table->dropColumn($column);
            }
        }
    }
}
