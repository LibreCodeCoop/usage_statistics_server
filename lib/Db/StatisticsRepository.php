<?php

declare(strict_types=1);

namespace OCA\UsageStatisticsServer\Db;

use OCP\IDBConnection;

final readonly class StatisticsRepository {
    private const DB_DATETIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private IDBConnection $db) {
    }

    public function countActiveInstallations(string $application, \DateTimeImmutable $since): int {
        $qb = $this->db->getQueryBuilder();
        $result = $qb->select('COUNT(*) AS active_count')
            ->from('usage_stats_installations')
            ->where($qb->expr()->eq('application', $qb->createNamedParameter($application)))
            ->andWhere($qb->expr()->gte('last_seen_at', $qb->createNamedParameter($this->formatDateTime($since))))
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

    /** @return array{count:int,average:float|null,min:float|null,max:float|null,total:float|null} */
    public function currentNumericalEvaluation(string $application, string $category, string $key): array {
        $qb = $this->db->getQueryBuilder();
        $row = $qb
            ->select('COUNT(m.numeric_value) AS value_count')
            ->addSelect($qb->createFunction('AVG(m.numeric_value) AS average_value'))
            ->addSelect($qb->createFunction('MIN(m.numeric_value) AS min_value'))
            ->addSelect($qb->createFunction('MAX(m.numeric_value) AS max_value'))
            ->addSelect($qb->createFunction('SUM(m.numeric_value) AS total_value'))
            ->from('usage_stats_installations', 'i')
            ->innerJoin('i', 'usage_stats_metrics', 'm', $qb->expr()->eq('m.report_id', 'i.last_report_id'))
            ->where($qb->expr()->eq('i.application', $qb->createNamedParameter($application)))
            ->andWhere($qb->expr()->eq('m.category', $qb->createNamedParameter($category)))
            ->andWhere($qb->expr()->eq('m.metric_key', $qb->createNamedParameter($key)))
            ->andWhere($qb->expr()->isNotNull('m.numeric_value'))
            ->executeQuery()
            ->fetch();

        if ($row === false) {
            return ['count' => 0, 'average' => null, 'min' => null, 'max' => null, 'total' => null];
        }

        return [
            'count' => (int)$row['value_count'],
            'average' => $row['average_value'] === null ? null : round((float)$row['average_value'], 2),
            'min' => $row['min_value'] === null ? null : (float)$row['min_value'],
            'max' => $row['max_value'] === null ? null : (float)$row['max_value'],
            'total' => $row['total_value'] === null ? null : (float)$row['total_value'],
        ];
    }

    /** @return list<array{periodStart:string,periodEnd:string,count:int,average:float|null,min:float|null,max:float|null,total:float|null}> */
    public function numericalHistory(
        string $application,
        string $category,
        string $key,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): array {
        $qb = $this->db->getQueryBuilder();
        $result = $qb
            ->select('r.period_start', 'r.period_end', 'COUNT(m.numeric_value) AS value_count')
            ->addSelect($qb->createFunction('AVG(m.numeric_value) AS average_value'))
            ->addSelect($qb->createFunction('MIN(m.numeric_value) AS min_value'))
            ->addSelect($qb->createFunction('MAX(m.numeric_value) AS max_value'))
            ->addSelect($qb->createFunction('SUM(m.numeric_value) AS total_value'))
            ->from('usage_stats_reports', 'r')
            ->innerJoin('r', 'usage_stats_metrics', 'm', $qb->expr()->eq('m.report_id', 'r.id'))
            ->where($qb->expr()->eq('r.application', $qb->createNamedParameter($application)))
            ->andWhere($qb->expr()->eq('m.category', $qb->createNamedParameter($category)))
            ->andWhere($qb->expr()->eq('m.metric_key', $qb->createNamedParameter($key)))
            ->andWhere($qb->expr()->isNotNull('m.numeric_value'))
            ->andWhere($qb->expr()->gte('r.period_end', $qb->createNamedParameter($this->formatDateTime($from))))
            ->andWhere($qb->expr()->lte('r.period_end', $qb->createNamedParameter($this->formatDateTime($to))))
            ->groupBy('r.period_start', 'r.period_end')
            ->orderBy('r.period_end', 'ASC')
            ->executeQuery();

        $history = [];
        while ($row = $result->fetch()) {
            $history[] = [
                'periodStart' => (string)$row['period_start'],
                'periodEnd' => (string)$row['period_end'],
                'count' => (int)$row['value_count'],
                'average' => $row['average_value'] === null ? null : round((float)$row['average_value'], 2),
                'min' => $row['min_value'] === null ? null : (float)$row['min_value'],
                'max' => $row['max_value'] === null ? null : (float)$row['max_value'],
                'total' => $row['total_value'] === null ? null : (float)$row['total_value'],
            ];
        }
        $result->closeCursor();

        return $history;
    }

    private function formatDateTime(\DateTimeImmutable $dateTime): string {
        return $dateTime
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format(self::DB_DATETIME_FORMAT);
    }
}
