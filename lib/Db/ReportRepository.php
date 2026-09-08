<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\UsageStatisticsServer\Db;

use OCA\UsageStatisticsServer\Service\ConflictingReport;
use OCA\UsageStatisticsServer\Service\Metric;
use OCA\UsageStatisticsServer\Service\Report;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

final readonly class ReportRepository {
    private const DB_DATETIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private IDBConnection $db) {
    }

    /** @return array{id:int,created:bool} */
    public function store(Report $report): array {
        return $this->storeWithRetry($report, true);
    }

    /** @return array{id:int,created:bool} */
    private function storeWithRetry(Report $report, bool $allowRetry): array {
        $existing = $this->findByLogicalIdentity($report);
        if ($existing !== null) {
            $this->assertSameSchema($report, $existing['schemaVersion']);
            return ['id' => $existing['id'], 'created' => false];
        }

        $receivedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $this->db->beginTransaction();
        try {
            $qb = $this->db->getQueryBuilder();
            $qb->insert('usage_stats_reports')->values([
                'protocol_version' => $qb->createNamedParameter($report->protocolVersion, IQueryBuilder::PARAM_INT),
                'application' => $qb->createNamedParameter($report->application),
                'installation_id' => $qb->createNamedParameter($report->installationId),
                'schema_version' => $qb->createNamedParameter($report->schemaVersion, IQueryBuilder::PARAM_INT),
                'period_start' => $qb->createNamedParameter($this->formatDateTime($report->periodStart)),
                'period_end' => $qb->createNamedParameter($this->formatDateTime($report->periodEnd)),
                'received_at' => $qb->createNamedParameter($this->formatDateTime($receivedAt)),
            ])->executeStatement();
            $reportId = $qb->getLastInsertId();

            foreach ($report->metrics as $metric) {
                $this->insertMetric($reportId, $metric);
            }

            $this->updateInstallation($report, $reportId, $receivedAt);

            $this->db->commit();
            return ['id' => $reportId, 'created' => true];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            if ($e instanceof Exception && in_array($e->getReason(), [Exception::REASON_CONSTRAINT_VIOLATION, Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION], true)) {
                $existing = $this->findByLogicalIdentity($report);
                if ($existing !== null) {
                    $this->assertSameSchema($report, $existing['schemaVersion']);
                    return ['id' => $existing['id'], 'created' => false];
                }

                if ($allowRetry) {
                    return $this->storeWithRetry($report, false);
                }
            }

            throw $e;
        }
    }

    private function insertMetric(int $reportId, Metric $metric): void {
        $qb = $this->db->getQueryBuilder();
        $values = [
            'report_id' => $qb->createNamedParameter($reportId, IQueryBuilder::PARAM_INT),
            'category' => $qb->createNamedParameter($metric->category),
            'metric_key' => $qb->createNamedParameter($metric->key),
            'metric_type' => $qb->createNamedParameter($metric->type),
            'value_integer' => $qb->createNamedParameter(null),
            'value_number' => $qb->createNamedParameter(null),
            'value_boolean' => $qb->createNamedParameter(null),
            'value_string' => $qb->createNamedParameter(null),
        ];

        switch ($metric->type) {
            case 'integer':
                $values['value_integer'] = $qb->createNamedParameter((int)$metric->value, IQueryBuilder::PARAM_INT);
                break;
            case 'number':
                $values['value_number'] = $qb->createNamedParameter((float)$metric->value);
                break;
            case 'boolean':
                $values['value_boolean'] = $qb->createNamedParameter((bool)$metric->value, IQueryBuilder::PARAM_BOOL);
                break;
            case 'string':
                $values['value_string'] = $qb->createNamedParameter((string)$metric->value);
                break;
        }

        $qb->insert('usage_stats_metrics')->values($values)->executeStatement();
    }

    private function updateInstallation(Report $report, int $reportId, \DateTimeImmutable $receivedAt): void {
        $receivedAtValue = $this->formatDateTime($receivedAt);
        $periodStart = $this->formatDateTime($report->periodStart);
        $periodEnd = $this->formatDateTime($report->periodEnd);

        $seenQb = $this->db->getQueryBuilder();
        $seenQb->update('usage_stats_installations')
            ->set('last_seen_at', $seenQb->createNamedParameter($receivedAtValue))
            ->where($seenQb->expr()->eq('application', $seenQb->createNamedParameter($report->application)))
            ->andWhere($seenQb->expr()->eq('installation_id', $seenQb->createNamedParameter($report->installationId)))
            ->andWhere($seenQb->expr()->lt('last_seen_at', $seenQb->createNamedParameter($receivedAtValue)))
            ->executeStatement();

        $currentQb = $this->db->getQueryBuilder();
        $currentQb->update('usage_stats_installations')
            ->set('last_report_id', $currentQb->createNamedParameter($reportId, IQueryBuilder::PARAM_INT))
            ->set('last_period_start', $currentQb->createNamedParameter($periodStart))
            ->set('last_period_end', $currentQb->createNamedParameter($periodEnd))
            ->where($currentQb->expr()->eq('application', $currentQb->createNamedParameter($report->application)))
            ->andWhere($currentQb->expr()->eq('installation_id', $currentQb->createNamedParameter($report->installationId)))
            ->andWhere($currentQb->expr()->orX(
                $currentQb->expr()->lt('last_period_end', $currentQb->createNamedParameter($periodEnd)),
                $currentQb->expr()->andX(
                    $currentQb->expr()->eq('last_period_end', $currentQb->createNamedParameter($periodEnd)),
                    $currentQb->expr()->lt('last_period_start', $currentQb->createNamedParameter($periodStart)),
                ),
            ))
            ->executeStatement();

        if ($this->installationExists($report->application, $report->installationId)) {
            return;
        }

        $insertQb = $this->db->getQueryBuilder();
        $insertQb->insert('usage_stats_installations')->values([
            'application' => $insertQb->createNamedParameter($report->application),
            'installation_id' => $insertQb->createNamedParameter($report->installationId),
            'last_seen_at' => $insertQb->createNamedParameter($receivedAtValue),
            'last_report_id' => $insertQb->createNamedParameter($reportId, IQueryBuilder::PARAM_INT),
            'last_period_start' => $insertQb->createNamedParameter($periodStart),
            'last_period_end' => $insertQb->createNamedParameter($periodEnd),
        ])->executeStatement();
    }

    private function installationExists(string $application, string $installationId): bool {
        $qb = $this->db->getQueryBuilder();
        return $qb->select('id')
            ->from('usage_stats_installations')
            ->where($qb->expr()->eq('application', $qb->createNamedParameter($application)))
            ->andWhere($qb->expr()->eq('installation_id', $qb->createNamedParameter($installationId)))
            ->executeQuery()
            ->fetchOne() !== false;
    }

    /** @return array{id:int,schemaVersion:int}|null */
    private function findByLogicalIdentity(Report $report): ?array {
        $qb = $this->db->getQueryBuilder();
        $row = $qb->select('id', 'schema_version')->from('usage_stats_reports')
            ->where($qb->expr()->eq('application', $qb->createNamedParameter($report->application)))
            ->andWhere($qb->expr()->eq('installation_id', $qb->createNamedParameter($report->installationId)))
            ->andWhere($qb->expr()->eq('period_start', $qb->createNamedParameter($this->formatDateTime($report->periodStart))))
            ->andWhere($qb->expr()->eq('period_end', $qb->createNamedParameter($this->formatDateTime($report->periodEnd))))
            ->executeQuery()
            ->fetchAssociative();

        if ($row === false) {
            return null;
        }

        return [
            'id' => (int)$row['id'],
            'schemaVersion' => (int)$row['schema_version'],
        ];
    }

    private function assertSameSchema(Report $report, int $existingSchemaVersion): void {
        if ($existingSchemaVersion !== $report->schemaVersion) {
            throw new ConflictingReport('A report for this installation and period already exists with a different schema version.');
        }
    }

    private function formatDateTime(\DateTimeImmutable $dateTime): string {
        return $dateTime
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format(self::DB_DATETIME_FORMAT);
    }
}
