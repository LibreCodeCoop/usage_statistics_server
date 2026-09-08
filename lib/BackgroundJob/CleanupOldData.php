<?php

declare(strict_types=1);

namespace OCA\UsageStatisticsServer\BackgroundJob;

use OCA\UsageStatisticsServer\Service\RetentionService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IAppConfig;

final class CleanupOldData extends TimedJob {
    private const APP_ID = 'usage_statistics_server';
    private const DEFAULT_RETENTION_DAYS = 1095;

    public function __construct(
        ITimeFactory $time,
        private readonly IAppConfig $appConfig,
        private readonly RetentionService $retention,
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

        $this->retention->cleanupBefore($cutoff);
    }
}
