<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260908000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create historical usage statistics report and metric tables';
    }

    public function up(Schema $schema): void
    {
        $reports = $schema->createTable('reports');
        $reports->addColumn('id', 'integer', ['autoincrement' => true]);
        $reports->addColumn('application', 'string', ['length' => 128]);
        $reports->addColumn('installation_id', 'string', ['length' => 128]);
        $reports->addColumn('protocol_version', 'integer');
        $reports->addColumn('schema_version', 'integer');
        $reports->addColumn('period_start', 'string', ['length' => 40]);
        $reports->addColumn('period_end', 'string', ['length' => 40]);
        $reports->addColumn('received_at', 'string', ['length' => 40]);
        $reports->addColumn('payload', 'text');
        $reports->setPrimaryKey(['id']);
        $reports->addUniqueIndex(
            ['application', 'installation_id', 'period_start', 'period_end', 'schema_version'],
            'uniq_logical_report'
        );
        $reports->addIndex(['application', 'received_at'], 'idx_application_received');
        $reports->addIndex(['application', 'installation_id', 'received_at'], 'idx_installation_received');

        $metrics = $schema->createTable('metrics');
        $metrics->addColumn('id', 'integer', ['autoincrement' => true]);
        $metrics->addColumn('report_id', 'integer');
        $metrics->addColumn('category', 'string', ['length' => 128]);
        $metrics->addColumn('metric_key', 'string', ['length' => 256]);
        $metrics->addColumn('value_type', 'string', ['length' => 16]);
        $metrics->addColumn('value', 'text');
        $metrics->setPrimaryKey(['id']);
        $metrics->addUniqueIndex(['report_id', 'category', 'metric_key'], 'uniq_report_metric');
        $metrics->addIndex(['category', 'metric_key'], 'idx_metric_identity');
        $metrics->addForeignKeyConstraint($reports, ['report_id'], ['id'], ['onDelete' => 'CASCADE']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('metrics');
        $schema->dropTable('reports');
    }
}
