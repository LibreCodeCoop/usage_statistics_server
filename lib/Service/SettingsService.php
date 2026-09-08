<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\UsageStatisticsServer\Service;

use OCP\IAppConfig;

final readonly class SettingsService {
    public const APP_ID = 'usage_statistics_server';
    public const DEFAULT_RETENTION_DAYS = 1095;
    public const MIN_RETENTION_DAYS = 45;
    public const MAX_RETENTION_DAYS = 3650;

    public function __construct(private IAppConfig $appConfig) {
    }

    public function getRetentionDays(): int {
        $value = $this->appConfig->getValueInt(
            self::APP_ID,
            'retention_days',
            self::DEFAULT_RETENTION_DAYS,
        );

        return min(self::MAX_RETENTION_DAYS, max(self::MIN_RETENTION_DAYS, $value));
    }

    public function setRetentionDays(int $days): int {
        if ($days < self::MIN_RETENTION_DAYS || $days > self::MAX_RETENTION_DAYS) {
            throw new \InvalidArgumentException(sprintf(
                'retentionDays must be between %d and %d.',
                self::MIN_RETENTION_DAYS,
                self::MAX_RETENTION_DAYS,
            ));
        }

        $this->appConfig->setValueInt(self::APP_ID, 'retention_days', $days);
        return $days;
    }
}
