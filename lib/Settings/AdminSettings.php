<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\UsageStatisticsServer\Settings;

use OCA\UsageStatisticsServer\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;
use OCP\Util;
use Override;

final class AdminSettings implements ISettings {
    /**
     * @return TemplateResponse<200, array{}>
     */
    #[Override]
    public function getForm(): TemplateResponse {
        Util::addStyle(Application::APP_ID, Application::APP_ID . '-settings');
        Util::addScript(Application::APP_ID, Application::APP_ID . '-settings');

        return new TemplateResponse(Application::APP_ID, 'settings/admin', [], '');
    }

    #[Override]
    public function getSection(): string {
        return AdminSection::ID;
    }

    #[Override]
    public function getPriority(): int {
        return 50;
    }
}
