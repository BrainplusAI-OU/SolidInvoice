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
use Symfony\Bridge\Doctrine\Types\UlidType;

final class Version30200_13 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the user_grid_preferences table (hidden columns, sort and page size per user and grid)';
    }

    public function isTransactional(): bool
    {
        // MySQL and MariaDB commit implicitly on DDL. AbstractMySQLPlatform, because
        // MariaDBPlatform is a sibling of MySQLPlatform rather than a subclass.
        return ! $this->platform instanceof AbstractMySQLPlatform && ! $this->platform instanceof OraclePlatform;
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('user_grid_preferences')) {
            return;
        }

        $table = $schema->createTable('user_grid_preferences');
        $table->addColumn('id', UlidType::NAME);
        $table->addColumn('user_id', UlidType::NAME);
        $table->addColumn('grid_name', Types::STRING, ['length' => 125]);
        $table->addColumn('hidden_columns', Types::JSON);
        $table->addColumn('sort', Types::STRING, ['length' => 255, 'notnull' => false]);
        $table->addColumn('per_page', Types::INTEGER, ['notnull' => false]);
        $table->addColumn('created', Types::DATETIME_IMMUTABLE);
        $table->addColumn('updated', Types::DATETIME_IMMUTABLE);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['user_id', 'grid_name']);
        $table->addForeignKeyConstraint('users', ['user_id'], ['id'], ['onDelete' => 'CASCADE']);
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('user_grid_preferences')) {
            $schema->dropTable('user_grid_preferences');
        }
    }
}
