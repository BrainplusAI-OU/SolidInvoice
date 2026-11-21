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

final class Version30200_15 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the assistants, assistant_members and assistant_events tables';
    }

    public function isTransactional(): bool
    {
        // MySQL and MariaDB commit implicitly on DDL. AbstractMySQLPlatform, because
        // MariaDBPlatform is a sibling of MySQLPlatform rather than a subclass.
        return ! $this->platform instanceof AbstractMySQLPlatform && ! $this->platform instanceof OraclePlatform;
    }

    public function up(Schema $schema): void
    {
        if (! $schema->hasTable('assistants')) {
            $table = $schema->createTable('assistants');
            $table->addColumn('id', UlidType::NAME);
            $table->addColumn('company_id', UlidType::NAME);
            $table->addColumn('name', Types::STRING, ['length' => 60]);
            $table->addColumn('description', Types::TEXT, ['notnull' => false]);
            $table->addColumn('channel', Types::STRING, ['length' => 20]);
            $table->addColumn('inbox_email', Types::STRING, ['length' => 180, 'notnull' => false]);
            $table->addColumn('created', Types::DATETIME_IMMUTABLE);
            $table->addColumn('updated', Types::DATETIME_IMMUTABLE);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['company_id']);
            $table->addForeignKeyConstraint('companies', ['company_id'], ['id'], ['onDelete' => 'CASCADE']);
        }

        if (! $schema->hasTable('assistant_members')) {
            $table = $schema->createTable('assistant_members');
            $table->addColumn('id', UlidType::NAME);
            $table->addColumn('company_id', UlidType::NAME);
            $table->addColumn('assistant_id', UlidType::NAME);
            $table->addColumn('external_id', Types::STRING, ['length' => 64]);
            $table->addColumn('name', Types::STRING, ['length' => 120]);
            $table->addColumn('vehicle_plates', Types::STRING, ['length' => 255, 'notnull' => false]);
            $table->addColumn('language', Types::STRING, ['length' => 8, 'notnull' => false]);
            $table->addColumn('payer', Types::STRING, ['length' => 120, 'notnull' => false]);
            $table->addColumn('active', Types::BOOLEAN);
            $table->addColumn('last_seen_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
            $table->addColumn('note', Types::TEXT, ['notnull' => false]);
            $table->addColumn('created', Types::DATETIME_IMMUTABLE);
            $table->addColumn('updated', Types::DATETIME_IMMUTABLE);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['company_id']);
            $table->addIndex(['assistant_id']);
            $table->addUniqueIndex(['assistant_id', 'external_id'], 'assistant_member_external_id');
            $table->addForeignKeyConstraint('companies', ['company_id'], ['id'], ['onDelete' => 'CASCADE']);
            $table->addForeignKeyConstraint('assistants', ['assistant_id'], ['id'], ['onDelete' => 'CASCADE']);
        }

        if (! $schema->hasTable('assistant_events')) {
            $table = $schema->createTable('assistant_events');
            $table->addColumn('id', UlidType::NAME);
            $table->addColumn('company_id', UlidType::NAME);
            $table->addColumn('assistant_id', UlidType::NAME);
            $table->addColumn('member_id', UlidType::NAME, ['notnull' => false]);
            $table->addColumn('expense_id', UlidType::NAME, ['notnull' => false]);
            $table->addColumn('event_key', Types::STRING, ['length' => 191, 'notnull' => false]);
            $table->addColumn('type', Types::STRING, ['length' => 20]);
            $table->addColumn('status', Types::STRING, ['length' => 20]);
            $table->addColumn('channel', Types::STRING, ['length' => 20]);
            $table->addColumn('direction', Types::STRING, ['length' => 3, 'notnull' => false]);
            $table->addColumn('occurred_at', Types::DATETIME_IMMUTABLE);
            $table->addColumn('member_name', Types::STRING, ['length' => 120, 'notnull' => false]);
            $table->addColumn('member_external_id', Types::STRING, ['length' => 64, 'notnull' => false]);
            $table->addColumn('subject', Types::STRING, ['length' => 255]);
            $table->addColumn('body', Types::TEXT, ['notnull' => false]);
            $table->addColumn('details', Types::JSON);
            $table->addColumn('created', Types::DATETIME_IMMUTABLE);
            $table->addColumn('updated', Types::DATETIME_IMMUTABLE);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['company_id']);
            $table->addIndex(['member_id']);
            $table->addIndex(['expense_id']);
            $table->addIndex(['assistant_id', 'occurred_at'], 'assistant_event_occurred');
            $table->addIndex(['assistant_id', 'type', 'status'], 'assistant_event_type_status');
            $table->addUniqueIndex(['assistant_id', 'event_key'], 'assistant_event_key');
            $table->addForeignKeyConstraint('companies', ['company_id'], ['id'], ['onDelete' => 'CASCADE']);
            $table->addForeignKeyConstraint('assistants', ['assistant_id'], ['id'], ['onDelete' => 'CASCADE']);
            $table->addForeignKeyConstraint('assistant_members', ['member_id'], ['id'], ['onDelete' => 'SET NULL']);
            $table->addForeignKeyConstraint('expenses', ['expense_id'], ['id'], ['onDelete' => 'SET NULL']);
        }
    }

    public function down(Schema $schema): void
    {
        foreach (['assistant_events', 'assistant_members', 'assistants'] as $name) {
            if ($schema->hasTable($name)) {
                $schema->dropTable($name);
            }
        }
    }
}
