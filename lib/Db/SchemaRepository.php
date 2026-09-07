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

        $qb = $this->db->getQueryBuilder();
        $qb->insert('usage_stats_schemas')->values([
            'application' => $qb->createNamedParameter($application),
            'schema_version' => $qb->createNamedParameter($schemaVersion, IQueryBuilder::PARAM_INT),
            'definition' => $qb->createNamedParameter(json_encode($definition, JSON_THROW_ON_ERROR)),
            'created_at' => $qb->createNamedParameter(
                new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
                IQueryBuilder::PARAM_DATETIME_IMMUTABLE,
            ),
        ])->executeStatement();

        return true;
    }
}
