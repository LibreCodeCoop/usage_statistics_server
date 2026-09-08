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
            ->select(
                'm.metric_type',
                'm.value_integer',
                'm.value_number',
                'm.value_boolean',
                'm.value_string',
                $qb->func()->count('', 'value_count'),
            )
            ->from('usage_stats_installations', 'i')
            ->innerJoin('i', 'usage_stats_metrics', 'm', $qb->expr()->eq('m.report_id', 'i.last_report_id'))
            ->where($qb->expr()->eq('i.application', $qb->createNamedParameter($application)))
            ->andWhere($qb->expr()->eq('m.category', $qb->createNamedParameter($category)))
            ->andWhere($qb->expr()->eq('m.metric_key', $qb->createNamedParameter($key)))
            ->groupBy('m.metric_type', 'm.value_integer', 'm.value_number', 'm.value_boolean', 'm.value_string')
            ->orderBy('value_count', 'DESC')
            ->executeQuery();

        $distribution = [];
        foreach ($result->iterateAssociative() as $row) {
            $distribution[] = [
                'value' => $this->readTypedValue($row),
                'count' => (int)$row['value_count'],
            ];
        }

        usort($distribution, static function (array $left, array $right): int {
            $countComparison = $right['count'] <=> $left['count'];
            if ($countComparison !== 0) {
                return $countComparison;
            }

            return (string)$left['value'] <=> (string)$right['value'];
        });

        return $distribution;
    }

    /** @return array{count:int,average:float|null,min:float|null,max:float|null,total:float|null} */
    public function currentNumericalEvaluation(string $application, string $category, string $key): array {
        $integer = $this->currentNumericalEvaluationForType($application, $category, $key, 'integer', 'value_integer');
        $number = $this->currentNumericalEvaluationForType($application, $category, $key, 'number', 'value_number');

        return $this->mergeNumericalEvaluations($integer, $number);
    }

    /** @return list<array{periodStart:string,periodEnd:string,count:int,average:float|null,min:float|null,max:float|null,total:float|null}> */
    public function numericalHistory(
        string $application,
        string $category,
        string $key,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): array {
        $history = [];

        foreach ([['integer', 'value_integer'], ['number', 'value_number']] as [$type, $column]) {
            foreach ($this->numericalHistoryForType($application, $category, $key, $from, $to, $type, $column) as $row) {
                $identity = $row['periodStart'] . '|' . $row['periodEnd'];
                $history[$identity] = isset($history[$identity])
                    ? $this->mergeHistoricalRows($history[$identity], $row)
                    : $row;
            }
        }

        $rows = array_values($history);
        usort($rows, static fn (array $left, array $right): int => $left['periodEnd'] <=> $right['periodEnd']);

        return $rows;
    }

    /** @return array{count:int,total:float|null,min:float|null,max:float|null} */
    private function currentNumericalEvaluationForType(
        string $application,
        string $category,
        string $key,
        string $type,
        string $column,
    ): array {
        $qb = $this->db->getQueryBuilder();
        $row = $qb
            ->select(
                $qb->func()->count('m.' . $column),
                $qb->func()->sum('m.' . $column),
                $qb->func()->min('m.' . $column),
                $qb->func()->max('m.' . $column),
            )
            ->from('usage_stats_installations', 'i')
            ->innerJoin('i', 'usage_stats_metrics', 'm', $qb->expr()->eq('m.report_id', 'i.last_report_id'))
            ->where($qb->expr()->eq('i.application', $qb->createNamedParameter($application)))
            ->andWhere($qb->expr()->eq('m.category', $qb->createNamedParameter($category)))
            ->andWhere($qb->expr()->eq('m.metric_key', $qb->createNamedParameter($key)))
            ->andWhere($qb->expr()->eq('m.metric_type', $qb->createNamedParameter($type)))
            ->executeQuery()
            ->fetchNumeric();

        if ($row === false) {
            return ['count' => 0, 'total' => null, 'min' => null, 'max' => null];
        }

        return [
            'count' => (int)$row[0],
            'total' => $row[1] === null ? null : (float)$row[1],
            'min' => $row[2] === null ? null : (float)$row[2],
            'max' => $row[3] === null ? null : (float)$row[3],
        ];
    }

    /** @return list<array{periodStart:string,periodEnd:string,count:int,average:float|null,min:float|null,max:float|null,total:float|null}> */
    private function numericalHistoryForType(
        string $application,
        string $category,
        string $key,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
        string $type,
        string $column,
    ): array {
        $qb = $this->db->getQueryBuilder();
        $result = $qb
            ->select(
                'r.period_start',
                'r.period_end',
                $qb->func()->count('m.' . $column),
                $qb->func()->sum('m.' . $column),
                $qb->func()->min('m.' . $column),
                $qb->func()->max('m.' . $column),
            )
            ->from('usage_stats_reports', 'r')
            ->innerJoin('r', 'usage_stats_metrics', 'm', $qb->expr()->eq('m.report_id', 'r.id'))
            ->where($qb->expr()->eq('r.application', $qb->createNamedParameter($application)))
            ->andWhere($qb->expr()->eq('m.category', $qb->createNamedParameter($category)))
            ->andWhere($qb->expr()->eq('m.metric_key', $qb->createNamedParameter($key)))
            ->andWhere($qb->expr()->eq('m.metric_type', $qb->createNamedParameter($type)))
            ->andWhere($qb->expr()->gte('r.period_end', $qb->createNamedParameter($this->formatDateTime($from))))
            ->andWhere($qb->expr()->lte('r.period_end', $qb->createNamedParameter($this->formatDateTime($to))))
            ->groupBy('r.period_start', 'r.period_end')
            ->orderBy('r.period_end', 'ASC')
            ->executeQuery();

        $history = [];
        foreach ($result->iterateNumeric() as $row) {
            $count = (int)$row[2];
            $total = $row[3] === null ? null : (float)$row[3];
            $history[] = [
                'periodStart' => (string)$row[0],
                'periodEnd' => (string)$row[1],
                'count' => $count,
                'average' => $count === 0 || $total === null ? null : round($total / $count, 2),
                'min' => $row[4] === null ? null : (float)$row[4],
                'max' => $row[5] === null ? null : (float)$row[5],
                'total' => $total,
            ];
        }

        return $history;
    }

    /** @param array{count:int,total:float|null,min:float|null,max:float|null} $left
     *  @param array{count:int,total:float|null,min:float|null,max:float|null} $right
     *  @return array{count:int,average:float|null,min:float|null,max:float|null,total:float|null}
     */
    private function mergeNumericalEvaluations(array $left, array $right): array {
        $count = $left['count'] + $right['count'];
        $total = ($left['total'] ?? 0.0) + ($right['total'] ?? 0.0);
        $hasValues = $count > 0;

        $mins = array_values(array_filter([$left['min'], $right['min']], static fn ($value): bool => $value !== null));
        $maxs = array_values(array_filter([$left['max'], $right['max']], static fn ($value): bool => $value !== null));

        return [
            'count' => $count,
            'average' => $hasValues ? round($total / $count, 2) : null,
            'min' => $mins === [] ? null : min($mins),
            'max' => $maxs === [] ? null : max($maxs),
            'total' => $hasValues ? $total : null,
        ];
    }

    /** @param array{periodStart:string,periodEnd:string,count:int,average:float|null,min:float|null,max:float|null,total:float|null} $left
     *  @param array{periodStart:string,periodEnd:string,count:int,average:float|null,min:float|null,max:float|null,total:float|null} $right
     *  @return array{periodStart:string,periodEnd:string,count:int,average:float|null,min:float|null,max:float|null,total:float|null}
     */
    private function mergeHistoricalRows(array $left, array $right): array {
        $merged = $this->mergeNumericalEvaluations($left, $right);

        return [
            'periodStart' => $left['periodStart'],
            'periodEnd' => $left['periodEnd'],
            ...$merged,
        ];
    }

    /** @param array<string,mixed> $row */
    private function readTypedValue(array $row): mixed {
        return match ($row['metric_type']) {
            'integer' => (int)$row['value_integer'],
            'number' => (float)$row['value_number'],
            'boolean' => (bool)$row['value_boolean'],
            'string' => (string)$row['value_string'],
            default => null,
        };
    }

    private function formatDateTime(\DateTimeImmutable $dateTime): string {
        return $dateTime
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format(self::DB_DATETIME_FORMAT);
    }
}
