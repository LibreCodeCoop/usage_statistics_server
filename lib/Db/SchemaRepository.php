<?php

declare(strict_types=1);

namespace OCA\UsageStatisticsServer\Db;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

final readonly class SchemaRepository {
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

        $decoded = json_decode((string)$definition, true, 512, JSON_THROW_ON_ERROR);
        return is_array($decoded) ? $decoded : null;
    }

    public function store(string $application, int $schemaVersion, array $definition): void {
        $this->db->setValues(
            'usage_stats_schemas',
            [
                'application' => $application,
                'schema_version' => $schemaVersion,
            ],
            [
                'definition' => json_encode($definition, JSON_THROW_ON_ERROR),
            ],
            [
                'definition' => json_encode($definition, JSON_THROW_ON_ERROR),
                'created_at' => new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            ],
        );
    }
}
