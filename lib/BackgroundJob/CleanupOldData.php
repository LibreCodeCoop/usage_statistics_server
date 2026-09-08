<?php

declare(strict_types=1);

namespace OCA\UsageStatisticsServer\BackgroundJob;

use OCA\UsageStatisticsServer\Service\RetentionService;
use OCA\UsageStatisticsServer\Service\SettingsService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

final class CleanupOldData extends TimedJob {
    public function __construct(
        ITimeFactory $time,
        private readonly SettingsService $settings,
        private readonly RetentionService $retention,
    ) {
        parent::__construct($time);
        $this->setInterval(60 * 60 * 24);
        $this->setTimeSensitivity(self::TIME_INSENSITIVE);
    }

    #[\Override]
    protected function run($argument): void {
        $retentionDays = $this->settings->getRetentionDays();
        $cutoff = (new \DateTimeImmutable('@' . ($this->time->getTime() - ($retentionDays * 86400))))
            ->setTimezone(new \DateTimeZone('UTC'));

        $this->retention->cleanupBefore($cutoff);
    }
}
