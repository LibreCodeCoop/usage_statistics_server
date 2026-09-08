<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\UsageStatisticsServer\Service;

final readonly class Report {
    /** @param list<Metric> $metrics */
    public function __construct(
        public int $protocolVersion,
        public string $application,
        public string $installationId,
        public int $schemaVersion,
        public \DateTimeImmutable $periodStart,
        public \DateTimeImmutable $periodEnd,
        public array $metrics,
    ) {
    }
}
