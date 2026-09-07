<?php

declare(strict_types=1);

namespace OCA\UsageStatisticsServer\Db;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

final readonly class SchemaRepository {
    private const DB_DATETIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private IDBConnection $db) {
    }

    /** @return array<string,mixed>|null */
    public function find(string $application, int $schemaVersion): ?array {
        $qb = $this->db->getQueryBuilder();
        $definition = $qb->select('definition')
            ->from('usage_stats_schemas')
            ->where($qb->expr()->eq('application', $qb->createNamedParameter($application)))
            ->andWhere($qb->expr()->eq('schema_version', $qb->createNamedParameter($schemaVersion, IQueryBuilder::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();

        if ($definition === false) {
            return null;
        }

        return $this->decodeDefinition($definition);
    }

    /** @return array<string,mixed>|null */
    public function findMetric(string $application, string $category, string $key): ?array {
        foreach ($this->findAll($application) as $definition) {
            foreach ($definition['metrics'] ?? [] as $metric) {
                if (!is_array($metric)) {
                    continue;
                }
                if (($metric['category'] ?? null) === $category && ($metric['key'] ?? null) === $key) {
                    return $metric;
                }
            }
        }

        return null;
    }

    /**
     * @return bool true when created, false when the same definition already exists
     * @throws \LogicException when the version already exists with a different definition
     */
    public function store(string $application, int $schemaVersion, array $definition): bool {
        $existing = $this->find($application, $schemaVersion);
        if ($existing !== null) {
            if ($existing === $definition) {
                return false;
            }
            throw new \LogicException('Schema version already exists with a different definition.');
        }

        $this->assertMetricCompatibility($application, $definition);

        $qb = $this->db->getQueryBuilder();
        $qb->insert('usage_stats_schemas')->values([
            'application' => $qb->createNamedParameter($application),
            'schema_version' => $qb->createNamedParameter($schemaVersion, IQueryBuilder::PARAM_INT),
            'definition' => $qb->createNamedParameter(json_encode($definition, JSON_THROW_ON_ERROR)),
            'created_at' => $qb->createNamedParameter($this->formatDateTime(
                new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            )),
        ])->executeStatement();

        return true;
    }

    /** @return list<array<string,mixed>> */
    private function findAll(string $application): array {
        $qb = $this->db->getQueryBuilder();
        $result = $qb->select('definition')
            ->from('usage_stats_schemas')
            ->where($qb->expr()->eq('application', $qb->createNamedParameter($application)))
            ->orderBy('schema_version', 'ASC')
            ->executeQuery();

        $definitions = [];
        foreach ($result->iterateAssociative() as $row) {
            $decoded = $this->decodeDefinition($row['definition'] ?? null);
            if ($decoded !== null) {
                $definitions[] = $decoded;
            }
        }

        return $definitions;
    }

    /** @param array<string,mixed> $definition */
    private function assertMetricCompatibility(string $application, array $definition): void {
        $existingMetrics = [];
        foreach ($this->findAll($application) as $existingDefinition) {
            foreach ($existingDefinition['metrics'] ?? [] as $metric) {
                if (!is_array($metric)) {
                    continue;
                }
                $identity = ($metric['category'] ?? '') . ':' . ($metric['key'] ?? '');
                $existingMetrics[$identity] = $metric;
            }
        }

        foreach ($definition['metrics'] ?? [] as $metric) {
            if (!is_array($metric)) {
                continue;
            }
            $identity = ($metric['category'] ?? '') . ':' . ($metric['key'] ?? '');
            $existing = $existingMetrics[$identity] ?? null;
            if (!is_array($existing)) {
                continue;
            }

            foreach (['type', 'kind', 'aggregation'] as $field) {
                if (($existing[$field] ?? null) !== ($metric[$field] ?? null)) {
                    throw new \LogicException("Metric {$identity} changes {$field}; use a new metric key for incompatible semantics.");
                }
            }
        }
    }

    /** @return array<string,mixed>|null */
    private function decodeDefinition(mixed $definition): ?array {
        $decoded = json_decode((string)$definition, true, 512, JSON_THROW_ON_ERROR);
        return is_array($decoded) ? $decoded : null;
    }

    private function formatDateTime(\DateTimeImmutable $dateTime): string {
        return $dateTime
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format(self::DB_DATETIME_FORMAT);
    }
}
