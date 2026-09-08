<?php

declare(strict_types=1);

namespace OCA\UsageStatisticsServer\Db;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

final readonly class StatisticsRepository {
    public function __construct(private IDBConnection $db) {
    }

    public function countActiveInstallations(string $application, \DateTimeImmutable $since): int {
        $qb = $this->db->getQueryBuilder();
        $result = $qb->select('COUNT(*) AS active_count')
            ->from('usage_stats_installations')
            ->where($qb->expr()->eq('application', $qb->createNamedParameter($application)))
            ->andWhere($qb->expr()->gte('last_seen_at', $qb->createNamedParameter($since, IQueryBuilder::PARAM_DATETIME_IMMUTABLE)))
            ->executeQuery()
            ->fetchOne();

        return (int)$result;
    }

    /** @return list<array{value:mixed,count:int}> */
    public function currentDistribution(string $application, string $category, string $key): array {
        $qb = $this->db->getQueryBuilder();
        $result = $qb->select('m.metric_value', 'COUNT(*) AS value_count')
            ->from('usage_stats_installations', 'i')
            ->innerJoin('i', 'usage_stats_metrics', 'm', $qb->expr()->eq('m.report_id', 'i.last_report_id'))
            ->where($qb->expr()->eq('i.application', $qb->createNamedParameter($application)))
            ->andWhere($qb->expr()->eq('m.category', $qb->createNamedParameter($category)))
            ->andWhere($qb->expr()->eq('m.metric_key', $qb->createNamedParameter($key)))
            ->groupBy('m.metric_value')
            ->orderBy('value_count', 'DESC')
            ->executeQuery();

        $distribution = [];
        while ($row = $result->fetch()) {
            $distribution[] = [
                'value' => json_decode((string)$row['metric_value'], true, 512, JSON_THROW_ON_ERROR),
                'count' => (int)$row['value_count'],
            ];
        }
        $result->closeCursor();

        return $distribution;
    }

    /** @return list<array{periodStart:string,periodEnd:string,value:mixed,type:string}> */
    public function metricHistory(
        string $application,
        string $category,
        string $key,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): array {
        $qb = $this->db->getQueryBuilder();
        $result = $qb->select('r.period_start', 'r.period_end', 'm.metric_value', 'm.metric_type')
            ->from('usage_stats_reports', 'r')
            ->innerJoin('r', 'usage_stats_metrics', 'm', $qb->expr()->eq('m.report_id', 'r.id'))
            ->where($qb->expr()->eq('r.application', $qb->createNamedParameter($application)))
            ->andWhere($qb->expr()->eq('m.category', $qb->createNamedParameter($category)))
            ->andWhere($qb->expr()->eq('m.metric_key', $qb->createNamedParameter($key)))
            ->andWhere($qb->expr()->gte('r.period_end', $qb->createNamedParameter($from, IQueryBuilder::PARAM_DATETIME_IMMUTABLE)))
            ->andWhere($qb->expr()->lte('r.period_end', $qb->createNamedParameter($to, IQueryBuilder::PARAM_DATETIME_IMMUTABLE)))
            ->orderBy('r.period_end', 'ASC')
            ->executeQuery();

        $history = [];
        while ($row = $result->fetch()) {
            $history[] = [
                'periodStart' => (string)$row['period_start'],
                'periodEnd' => (string)$row['period_end'],
                'value' => json_decode((string)$row['metric_value'], true, 512, JSON_THROW_ON_ERROR),
                'type' => (string)$row['metric_type'],
            ];
        }
        $result->closeCursor();

        return $history;
    }
}
