<?php

declare(strict_types=1);

namespace OCA\UsageStatisticsServer\BackgroundJob;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IAppConfig;
use OCP\IDBConnection;

final class CleanupOldData extends TimedJob {
    private const APP_ID = 'usage_statistics_server';
    private const DEFAULT_RETENTION_DAYS = 1095;
    private const BATCH_SIZE = 500;

    public function __construct(
        ITimeFactory $time,
        private readonly IDBConnection $db,
        private readonly IAppConfig $appConfig,
    ) {
        parent::__construct($time);
        $this->setInterval(60 * 60 * 24);
        $this->setTimeSensitivity(self::TIME_INSENSITIVE);
    }

    #[\Override]
    protected function run($argument): void {
        $retentionDays = max(1, $this->appConfig->getValueInt(
            self::APP_ID,
            'retention_days',
            self::DEFAULT_RETENTION_DAYS,
        ));
        $cutoff = (new \DateTimeImmutable('@' . ($this->time->getTime() - ($retentionDays * 86400))))
            ->setTimezone(new \DateTimeZone('UTC'));

        $this->deleteStaleInstallations($cutoff);

        while (true) {
            $reportIds = $this->findOldReportIds($cutoff);
            if ($reportIds === []) {
                break;
            }

            $this->db->beginTransaction();
            try {
                $metrics = $this->db->getQueryBuilder();
                $metrics->delete('usage_stats_metrics')
                    ->where($metrics->expr()->in(
                        'report_id',
                        $metrics->createNamedParameter($reportIds, IQueryBuilder::PARAM_INT_ARRAY),
                    ))
                    ->executeStatement();

                $reports = $this->db->getQueryBuilder();
                $reports->delete('usage_stats_reports')
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
    }

    private function deleteStaleInstallations(\DateTimeImmutable $cutoff): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete('usage_stats_installations')
            ->where($qb->expr()->lt(
                'last_seen_at',
                $qb->createNamedParameter($cutoff, IQueryBuilder::PARAM_DATETIME_IMMUTABLE),
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
                $qb->createNamedParameter($cutoff, IQueryBuilder::PARAM_DATETIME_IMMUTABLE),
            ))
            ->orderBy('id', 'ASC')
            ->setMaxResults(self::BATCH_SIZE)
            ->executeQuery();

        $ids = array_map('intval', $result->fetchFirstColumn());
        $result->closeCursor();
        return $ids;
    }
}
