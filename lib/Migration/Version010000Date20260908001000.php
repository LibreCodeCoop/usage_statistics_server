<?php

declare(strict_types=1);

namespace OCA\UsageStatisticsServer\Migration;

use Closure;
use Doctrine\DBAL\Types\Types;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

final class Version010000Date20260908001000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('usage_stats_installations')) {
            $installations = $schema->createTable('usage_stats_installations');
            $installations->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'unsigned' => true]);
            $installations->addColumn('application', Types::STRING, ['length' => 128]);
            $installations->addColumn('installation_id', Types::STRING, ['length' => 128]);
            $installations->addColumn('last_seen_at', Types::DATETIME_IMMUTABLE);
            $installations->addColumn('last_report_id', Types::BIGINT, ['unsigned' => true]);
            $installations->setPrimaryKey(['id']);
            $installations->addUniqueIndex(['application', 'installation_id'], 'usage_stats_installation_identity');
            $installations->addIndex(['application', 'last_seen_at'], 'usage_stats_active_installations');
        }

        if (!$schema->hasTable('usage_stats_reports')) {
            $reports = $schema->createTable('usage_stats_reports');
            $reports->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'unsigned' => true]);
            $reports->addColumn('protocol_version', Types::INTEGER, ['unsigned' => true]);
            $reports->addColumn('application', Types::STRING, ['length' => 128]);
            $reports->addColumn('installation_id', Types::STRING, ['length' => 128]);
            $reports->addColumn('schema_version', Types::INTEGER, ['unsigned' => true]);
            $reports->addColumn('period_start', Types::DATETIME_IMMUTABLE);
            $reports->addColumn('period_end', Types::DATETIME_IMMUTABLE);
            $reports->addColumn('received_at', Types::DATETIME_IMMUTABLE);
            $reports->addColumn('raw_payload', Types::TEXT);
            $reports->setPrimaryKey(['id']);
            $reports->addUniqueIndex(['application', 'installation_id', 'schema_version', 'period_start', 'period_end'], 'usage_stats_report_identity');
            $reports->addIndex(['application', 'received_at'], 'usage_stats_recent_reports');
        }

        if (!$schema->hasTable('usage_stats_metrics')) {
            $metrics = $schema->createTable('usage_stats_metrics');
            $metrics->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'unsigned' => true]);
            $metrics->addColumn('report_id', Types::BIGINT, ['unsigned' => true]);
            $metrics->addColumn('category', Types::STRING, ['length' => 128]);
            $metrics->addColumn('metric_key', Types::STRING, ['length' => 512]);
            $metrics->addColumn('metric_type', Types::STRING, ['length' => 16]);
            $metrics->addColumn('metric_value', Types::TEXT);
            $metrics->addColumn('numeric_value', Types::FLOAT, ['notnull' => false]);
            $metrics->setPrimaryKey(['id']);
            $metrics->addUniqueIndex(['report_id', 'category', 'metric_key'], 'usage_stats_metric_identity');
            $metrics->addIndex(['category', 'metric_key'], 'usage_stats_metric_lookup');
        }

        return $schema;
    }
}
