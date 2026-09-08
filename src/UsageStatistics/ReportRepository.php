<?php

declare(strict_types=1);

namespace App\UsageStatistics;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final readonly class ReportRepository
{
    public function __construct(private Connection $connection)
    {
    }

    /** @return array{id: int, created: bool} */
    public function store(Report $report, array $rawPayload): array
    {
        return $this->connection->transactional(function (Connection $connection) use ($report, $rawPayload): array {
            try {
                $connection->insert('reports', [
                    'application' => $report->application,
                    'installation_id' => $report->installationId,
                    'protocol_version' => $report->protocolVersion,
                    'schema_version' => $report->schemaVersion,
                    'period_start' => $report->periodStart->format(DATE_ATOM),
                    'period_end' => $report->periodEnd->format(DATE_ATOM),
                    'received_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(DATE_ATOM),
                    'payload' => json_encode($rawPayload, JSON_THROW_ON_ERROR),
                ]);
                $reportId = (int) $connection->lastInsertId();
            } catch (UniqueConstraintViolationException) {
                $reportId = (int) $connection->fetchOne(
                    'SELECT id FROM reports WHERE application = ? AND installation_id = ? AND period_start = ? AND period_end = ? AND schema_version = ?',
                    [
                        $report->application,
                        $report->installationId,
                        $report->periodStart->format(DATE_ATOM),
                        $report->periodEnd->format(DATE_ATOM),
                        $report->schemaVersion,
                    ],
                );
                return ['id' => $reportId, 'created' => false];
            }

            foreach ($report->metrics as $metric) {
                $connection->insert('metrics', [
                    'report_id' => $reportId,
                    'category' => $metric->category,
                    'metric_key' => $metric->key,
                    'value_type' => $metric->type,
                    'value' => $this->serializeValue($metric),
                ]);
            }

            return ['id' => $reportId, 'created' => true];
        });
    }

    private function serializeValue(Metric $metric): string
    {
        return match ($metric->type) {
            'boolean' => $metric->value ? 'true' : 'false',
            default => (string) $metric->value,
        };
    }
}
