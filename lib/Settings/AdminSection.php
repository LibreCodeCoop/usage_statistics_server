<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\UsageStatisticsServer\Settings;

use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;
use Override;

final readonly class AdminSection implements IIconSection {
    public const ID = 'usage-statistics-server';

    public function __construct(
        private IURLGenerator $urlGenerator,
        private IL10N $l10n,
    ) {
    }

    #[Override]
    public function getIcon(): string {
        return $this->urlGenerator->imagePath('core', 'actions/settings-dark.svg');
    }

    #[Override]
    public function getID(): string {
        return self::ID;
    }

    #[Override]
    public function getName(): string {
        return $this->l10n->t('Usage Statistics');
    }

    #[Override]
    public function getPriority(): int {
        return 80;
    }
}
