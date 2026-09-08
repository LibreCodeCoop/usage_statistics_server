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
        $existingId = $this->findExistingId($report);
        if ($existingId !== null) {
            return ['id' => $existingId, 'created' => false];
        }

        try {
            return $this->connection->transactional(function (Connection $connection) use ($report, $rawPayload): array {
                $connection->insert('reports', [
                    'application' => $report->application,
                    'installation_id' => $report->installationId,
                    'protocol_version' => $report->protocolVersion,
                    'schema_version' => $report->schemaVersion,
                    'period_start' => $this->formatDate($report->periodStart),
                    'period_end' => $this->formatDate($report->periodEnd),
                    'received_at' => $this->formatDate(new \DateTimeImmutable('now', new \DateTimeZone('UTC'))),
                    'payload' => json_encode($rawPayload, JSON_THROW_ON_ERROR),
                ]);
                $reportId = (int) $connection->lastInsertId();

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
        } catch (UniqueConstraintViolationException) {
            $existingId = $this->findExistingId($report);
            if ($existingId === null) {
                throw new \RuntimeException('Logical report conflict could not be resolved.');
            }

            return ['id' => $existingId, 'created' => false];
        }
    }

    private function findExistingId(Report $report): ?int
    {
        $value = $this->connection->fetchOne(
            'SELECT id FROM reports WHERE application = ? AND installation_id = ? AND period_start = ? AND period_end = ? AND schema_version = ?',
            [
                $report->application,
                $report->installationId,
                $this->formatDate($report->periodStart),
                $this->formatDate($report->periodEnd),
                $report->schemaVersion,
            ],
        );

        return $value === false ? null : (int) $value;
    }

    private function formatDate(\DateTimeImmutable $date): string
    {
        return $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\\TH:i:s\\Z');
    }

    private function serializeValue(Metric $metric): string
    {
        return match ($metric->type) {
            'boolean' => $metric->value ? 'true' : 'false',
            default => (string) $metric->value,
        };
    }
}
