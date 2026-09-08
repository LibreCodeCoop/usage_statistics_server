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
        $result = $qb->select($qb->func()->count())
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
        $result = $qb
            ->select('m.metric_value', $qb->func()->count('', 'value_count'))
            ->from('usage_stats_installations', 'i')
            ->innerJoin('i', 'usage_stats_metrics', 'm', $qb->expr()->eq('m.report_id', 'i.last_report_id'))
            ->where($qb->expr()->eq('i.application', $qb->createNamedParameter($application)))
            ->andWhere($qb->expr()->eq('m.category', $qb->createNamedParameter($category)))
            ->andWhere($qb->expr()->eq('m.metric_key', $qb->createNamedParameter($key)))
            ->groupBy('m.metric_value')
            ->orderBy('value_count', 'DESC')
            ->executeQuery();

        $distribution = [];
        foreach ($result->iterateAssociative() as $row) {
            $distribution[] = [
                'value' => json_decode((string)$row['metric_value'], true, 512, JSON_THROW_ON_ERROR),
                'count' => (int)$row['value_count'],
            ];
        }

        return $distribution;
    }

    /** @return array{count:int,average:float|null,min:float|null,max:float|null,total:float|null} */
    public function currentNumericalEvaluation(string $application, string $category, string $key): array {
        $qb = $this->db->getQueryBuilder();
        $row = $qb
            ->select(
                $qb->func()->count('m.numeric_value'),
                $qb->func()->sum('m.numeric_value'),
                $qb->func()->min('m.numeric_value'),
                $qb->func()->max('m.numeric_value'),
            )
            ->from('usage_stats_installations', 'i')
            ->innerJoin('i', 'usage_stats_metrics', 'm', $qb->expr()->eq('m.report_id', 'i.last_report_id'))
            ->where($qb->expr()->eq('i.application', $qb->createNamedParameter($application)))
            ->andWhere($qb->expr()->eq('m.category', $qb->createNamedParameter($category)))
            ->andWhere($qb->expr()->eq('m.metric_key', $qb->createNamedParameter($key)))
            ->andWhere($qb->expr()->isNotNull('m.numeric_value'))
            ->executeQuery()
            ->fetchNumeric();

        if ($row === false) {
            return ['count' => 0, 'average' => null, 'min' => null, 'max' => null, 'total' => null];
        }

        [$countValue, $totalValue, $minValue, $maxValue] = $row;
        $count = (int)$countValue;
        $total = $totalValue === null ? null : (float)$totalValue;

        return [
            'count' => $count,
            'average' => $count === 0 || $total === null ? null : round($total / $count, 2),
            'min' => $minValue === null ? null : (float)$minValue,
            'max' => $maxValue === null ? null : (float)$maxValue,
            'total' => $total,
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
            ->select(
                'r.period_start',
                'r.period_end',
                $qb->func()->count('m.numeric_value'),
                $qb->func()->sum('m.numeric_value'),
                $qb->func()->min('m.numeric_value'),
                $qb->func()->max('m.numeric_value'),
            )
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
        foreach ($result->iterateNumeric() as $row) {
            [$periodStart, $periodEnd, $countValue, $totalValue, $minValue, $maxValue] = $row;
            $count = (int)$countValue;
            $total = $totalValue === null ? null : (float)$totalValue;

            $history[] = [
                'periodStart' => (string)$periodStart,
                'periodEnd' => (string)$periodEnd,
                'count' => $count,
                'average' => $count === 0 || $total === null ? null : round($total / $count, 2),
                'min' => $minValue === null ? null : (float)$minValue,
                'max' => $maxValue === null ? null : (float)$maxValue,
                'total' => $total,
            ];
        }

        return $history;
    }

    private function formatDateTime(\DateTimeImmutable $dateTime): string {
        return $dateTime
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format(self::DB_DATETIME_FORMAT);
    }
}
