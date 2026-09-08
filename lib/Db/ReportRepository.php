<?php

declare(strict_types=1);

namespace OCA\UsageStatisticsServer\Db;

use OCA\UsageStatisticsServer\Service\Report;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

final readonly class ReportRepository {
    public function __construct(private IDBConnection $db) {
    }

    /** @return array{id:int,created:bool} */
    public function store(Report $report, array $rawPayload): array {
        $existing = $this->findId($report);
        if ($existing !== null) {
            return ['id' => $existing, 'created' => false];
        }

        $this->db->beginTransaction();
        try {
            $qb = $this->db->getQueryBuilder();
            $qb->insert('usage_stats_reports')->values([
                'protocol_version' => $qb->createNamedParameter($report->protocolVersion, IQueryBuilder::PARAM_INT),
                'application' => $qb->createNamedParameter($report->application),
                'installation_id' => $qb->createNamedParameter($report->installationId),
                'schema_version' => $qb->createNamedParameter($report->schemaVersion, IQueryBuilder::PARAM_INT),
                'period_start' => $qb->createNamedParameter($report->periodStart, IQueryBuilder::PARAM_DATETIME_IMMUTABLE),
                'period_end' => $qb->createNamedParameter($report->periodEnd, IQueryBuilder::PARAM_DATETIME_IMMUTABLE),
                'received_at' => $qb->createNamedParameter(new \DateTimeImmutable('now', new \DateTimeZone('UTC')), IQueryBuilder::PARAM_DATETIME_IMMUTABLE),
                'raw_payload' => $qb->createNamedParameter(json_encode($rawPayload, JSON_THROW_ON_ERROR)),
            ])->executeStatement();
            $reportId = $qb->getLastInsertId();

            foreach ($report->metrics as $metric) {
                $metricQb = $this->db->getQueryBuilder();
                $metricQb->insert('usage_stats_metrics')->values([
                    'report_id' => $metricQb->createNamedParameter($reportId, IQueryBuilder::PARAM_INT),
                    'category' => $metricQb->createNamedParameter($metric->category),
                    'metric_key' => $metricQb->createNamedParameter($metric->key),
                    'metric_type' => $metricQb->createNamedParameter($metric->type),
                    'metric_value' => $metricQb->createNamedParameter(json_encode($metric->value, JSON_THROW_ON_ERROR)),
                ])->executeStatement();
            }

            $this->db->commit();
            return ['id' => $reportId, 'created' => true];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if (in_array($e->getReason(), [Exception::REASON_CONSTRAINT_VIOLATION, Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION], true)) {
                $id = $this->findId($report);
                if ($id !== null) {
                    return ['id' => $id, 'created' => false];
                }
            }
            throw $e;
        }
    }

    private function findId(Report $report): ?int {
        $qb = $this->db->getQueryBuilder();
        $result = $qb->select('id')->from('usage_stats_reports')
            ->where($qb->expr()->eq('application', $qb->createNamedParameter($report->application)))
            ->andWhere($qb->expr()->eq('installation_id', $qb->createNamedParameter($report->installationId)))
            ->andWhere($qb->expr()->eq('schema_version', $qb->createNamedParameter($report->schemaVersion, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('period_start', $qb->createNamedParameter($report->periodStart, IQueryBuilder::PARAM_DATETIME_IMMUTABLE)))
            ->andWhere($qb->expr()->eq('period_end', $qb->createNamedParameter($report->periodEnd, IQueryBuilder::PARAM_DATETIME_IMMUTABLE)))
            ->executeQuery()->fetchOne();
        return $result === false ? null : (int)$result;
    }
}
