<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\UsageStatisticsServer\Command;

use OCA\UsageStatisticsServer\Service\SettingsService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Override;

final class RetentionSet extends Command {
    public function __construct(private readonly SettingsService $settings) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void {
        $this
            ->setName('usage-statistics-server:retention:set')
            ->setDescription('Set the report retention period in days')
            ->addArgument('days', InputArgument::REQUIRED, 'Retention period in days');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $days = filter_var($input->getArgument('days'), FILTER_VALIDATE_INT);
        if ($days === false) {
            $output->writeln('<error>Retention period must be an integer.</error>');
            return self::INVALID;
        }

        try {
            $days = $this->settings->setRetentionDays($days);
        } catch (\InvalidArgumentException $e) {
            $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
            return self::INVALID;
        }

        $output->writeln(sprintf('Retention period set to %d days', $days));
        return self::SUCCESS;
    }
}
