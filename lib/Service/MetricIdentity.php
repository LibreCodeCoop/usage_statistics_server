<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\UsageStatisticsServer\Service;

final class MetricIdentity {
    private const SEPARATOR = "\0";

    private function __construct() {
    }

    public static function fromParts(string $category, string $key): string {
        return $category . self::SEPARATOR . $key;
    }
}
