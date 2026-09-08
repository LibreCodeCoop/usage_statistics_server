<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\UsageStatisticsServer\Service;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

final readonly class RetentionService {
    private const BATCH_SIZE = 500;
    private const DB_DATETIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private IDBConnection $db) {
    }

    /** @return array{installations:int,reports:int,metrics:int} */
    public function cleanupBefore(\DateTimeImmutable $cutoff): array {
        $installations = $this->deleteStaleInstallations($cutoff);
        $reportsDeleted = 0;
        $metricsDeleted = 0;

        while (true) {
            $reportIds = $this->findOldReportIds($cutoff);
            if ($reportIds === []) {
                break;
            }

            $this->db->beginTransaction();
            try {
                $metrics = $this->db->getQueryBuilder();
                $metricsDeleted += $metrics->delete('usage_stats_metrics')
                    ->where($metrics->expr()->in(
                        'report_id',
                        $metrics->createNamedParameter($reportIds, IQueryBuilder::PARAM_INT_ARRAY),
                    ))
                    ->executeStatement();

                $reports = $this->db->getQueryBuilder();
                $reportsDeleted += $reports->delete('usage_stats_reports')
                    ->where($reports->expr()->in(
                        'id',
                        $reports->createNamedParameter($reportIds, IQueryBuilder::PARAM_INT_ARRAY),
                    ))
                    ->executeStatement();

                $this->db->commit();
            } catch (\Throwable $e) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                throw $e;
            }
        }

        return [
            'installations' => $installations,
            'reports' => $reportsDeleted,
            'metrics' => $metricsDeleted,
        ];
    }

    private function deleteStaleInstallations(\DateTimeImmutable $cutoff): int {
        $qb = $this->db->getQueryBuilder();
        return $qb->delete('usage_stats_installations')
            ->where($qb->expr()->lt(
                'last_seen_at',
                $qb->createNamedParameter($this->formatDateTime($cutoff)),
            ))
            ->executeStatement();
    }

    /** @return list<int> */
    private function findOldReportIds(\DateTimeImmutable $cutoff): array {
        $qb = $this->db->getQueryBuilder();
        $result = $qb->select('id')
            ->from('usage_stats_reports')
            ->where($qb->expr()->lt(
                'received_at',
                $qb->createNamedParameter($this->formatDateTime($cutoff)),
            ))
            ->orderBy('id', 'ASC')
            ->setMaxResults(self::BATCH_SIZE)
            ->executeQuery();

        return array_map('intval', $result->fetchFirstColumn());
    }

    private function formatDateTime(\DateTimeImmutable $dateTime): string {
        return $dateTime
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format(self::DB_DATETIME_FORMAT);
    }
}
