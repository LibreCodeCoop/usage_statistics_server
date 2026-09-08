<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

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

            return $left['value'] <=> $right['value'];
        });

        return $distribution;
    }

    /** @return array{count:int,average:float|null,min:float|null,max:float|null,total:float|null} */
    public function currentNumericalEvaluation(string $application, string $category, string $key, string $type): array {
        $column = $this->numericalColumn($type);
        $qb = $this->db->getQueryBuilder();
        $row = $qb
            ->select(
                $qb->func()->count($column),
                $qb->func()->sum($column),
                $qb->func()->min($column),
                $qb->func()->max($column),
            )
            ->from('usage_stats_installations', 'i')
            ->innerJoin('i', 'usage_stats_metrics', 'm', $qb->expr()->eq('m.report_id', 'i.last_report_id'))
            ->where($qb->expr()->eq('i.application', $qb->createNamedParameter($application)))
            ->andWhere($qb->expr()->eq('m.category', $qb->createNamedParameter($category)))
            ->andWhere($qb->expr()->eq('m.metric_key', $qb->createNamedParameter($key)))
            ->andWhere($qb->expr()->eq('m.metric_type', $qb->createNamedParameter($type)))
            ->executeQuery()
            ->fetchNumeric();

        if ($row === false || (int)$row[0] === 0) {
            return ['count' => 0, 'average' => null, 'min' => null, 'max' => null, 'total' => null];
        }

        $count = (int)$row[0];
        $total = (float)$row[1];

        return [
            'count' => $count,
            'average' => round($total / $count, 2),
            'min' => (float)$row[2],
            'max' => (float)$row[3],
            'total' => $total,
        ];
    }

    /** @return list<array{periodStart:string,periodEnd:string,count:int,average:float,min:float,max:float,total:float}> */
    public function numericalHistory(
        string $application,
        string $category,
        string $key,
        string $type,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): array {
        $column = $this->numericalColumn($type);
        $qb = $this->db->getQueryBuilder();
        $result = $qb
            ->select(
                'r.period_start',
                'r.period_end',
                $qb->func()->count($column),
                $qb->func()->sum($column),
                $qb->func()->min($column),
                $qb->func()->max($column),
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
            ->executeQuery();

        $history = [];
        foreach ($result->iterateNumeric() as $row) {
            $count = (int)$row[2];
            $total = (float)$row[3];
            $history[] = [
                'periodStart' => (string)$row[0],
                'periodEnd' => (string)$row[1],
                'count' => $count,
                'average' => round($total / $count, 2),
                'min' => (float)$row[4],
                'max' => (float)$row[5],
                'total' => $total,
            ];
        }

        usort($history, static fn (array $left, array $right): int => $left['periodEnd'] <=> $right['periodEnd']);
        return $history;
    }

    private function numericalColumn(string $type): string {
        return match ($type) {
            'integer' => 'value_integer',
            'number' => 'value_number',
            default => throw new \InvalidArgumentException('Numerical metrics must use integer or number type.'),
        };
    }

    /** @param array<string,mixed> $row */
    private function readTypedValue(array $row): mixed {
        /** @var 'integer'|'number'|'boolean'|'string' $type */
        $type = $row['metric_type'];

        return match ($type) {
            'integer' => (int)$row['value_integer'],
            'number' => (float)$row['value_number'],
            'boolean' => $this->readBoolean($row['value_boolean']),
            'string' => (string)$row['value_string'],
        };
    }

    private function readBoolean(mixed $value): bool {
        if (is_bool($value)) {
            return $value;
        }

        return in_array($value, [1, '1', 't', 'true'], true);
    }

    private function formatDateTime(\DateTimeImmutable $dateTime): string {
        return $dateTime
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format(self::DB_DATETIME_FORMAT);
    }
}
