<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\UsageStatisticsServer\Command;

use OCA\UsageStatisticsServer\Service\SettingsService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Override;

final class RetentionGet extends Command {
    public function __construct(private readonly SettingsService $settings) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void {
        $this
            ->setName('usage-statistics-server:retention:get')
            ->setDescription('Show the report retention period in days');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $output->writeln(sprintf('Retention period: %d days', $this->settings->getRetentionDays()));
        return self::SUCCESS;
    }
}
