<?php

declare(strict_types=1);

namespace OCA\UsageStatisticsServer\Db;

use OCA\UsageStatisticsServer\Service\Report;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

final readonly class ReportRepository {
    private const DB_DATETIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private IDBConnection $db) {
    }

    /** @return array{id:int,created:bool} */
    public function store(Report $report, array $rawPayload): array {
        $existing = $this->findId($report);
        if ($existing !== null) {
            return ['id' => $existing, 'created' => false];
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
                'raw_payload' => $qb->createNamedParameter(json_encode($rawPayload, JSON_THROW_ON_ERROR)),
            ])->executeStatement();
            $reportId = $qb->getLastInsertId();

            foreach ($report->metrics as $metric) {
                $metricQb = $this->db->getQueryBuilder();
                $numericValue = in_array($metric->type, ['integer', 'number'], true)
                    ? (float)$metric->value
                    : null;

                $metricQb->insert('usage_stats_metrics')->values([
                    'report_id' => $metricQb->createNamedParameter($reportId, IQueryBuilder::PARAM_INT),
                    'category' => $metricQb->createNamedParameter($metric->category),
                    'metric_key' => $metricQb->createNamedParameter($metric->key),
                    'metric_type' => $metricQb->createNamedParameter($metric->type),
                    'metric_value' => $metricQb->createNamedParameter(json_encode($metric->value, JSON_THROW_ON_ERROR)),
                    'numeric_value' => $metricQb->createNamedParameter($numericValue),
                ])->executeStatement();
            }

            $this->db->setValues(
                'usage_stats_installations',
                [
                    'application' => $report->application,
                    'installation_id' => $report->installationId,
                ],
                [
                    'last_seen_at' => $this->formatDateTime($receivedAt),
                    'last_report_id' => $reportId,
                ],
            );

            $this->db->commit();
            return ['id' => $reportId, 'created' => true];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            if ($e instanceof Exception && in_array($e->getReason(), [Exception::REASON_CONSTRAINT_VIOLATION, Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION], true)) {
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
            ->andWhere($qb->expr()->eq('period_start', $qb->createNamedParameter($this->formatDateTime($report->periodStart))))
            ->andWhere($qb->expr()->eq('period_end', $qb->createNamedParameter($this->formatDateTime($report->periodEnd))))
            ->executeQuery()
            ->fetchOne();

        return $result === false ? null : (int)$result;
    }

    private function formatDateTime(\DateTimeImmutable $dateTime): string {
        return $dateTime
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format(self::DB_DATETIME_FORMAT);
    }
}
