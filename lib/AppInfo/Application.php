<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\UsageStatisticsServer\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use Override;

final class Application extends App implements IBootstrap {
    public const APP_ID = 'usage_statistics_server';

    public function __construct() {
        parent::__construct(self::APP_ID);
    }

    #[Override]
    public function register(IRegistrationContext $context): void {
    }

    #[Override]
    public function boot(IBootContext $context): void {
    }
}
