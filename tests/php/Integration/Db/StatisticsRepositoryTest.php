<?php

declare(strict_types=1);

namespace OCA\UsageStatisticsServer\Tests\Integration\Db;

use OCA\UsageStatisticsServer\Db\ReportRepository;
use OCA\UsageStatisticsServer\Db\StatisticsRepository;
use OCA\UsageStatisticsServer\Service\ReportFactory;
use OCP\IDBConnection;
use OCP\Server;
use PHPUnit\Framework\Attributes\Group;
use Test\TestCase;

#[Group('DB')]
final class StatisticsRepositoryTest extends TestCase {
    private IDBConnection $db;
    private ReportRepository $reports;
    private StatisticsRepository $statistics;
    private ReportFactory $factory;

    protected function setUp(): void {
        parent::setUp();

        $this->db = Server::get(IDBConnection::class);
        $this->reports = new ReportRepository($this->db);
        $this->statistics = new StatisticsRepository($this->db);
        $this->factory = new ReportFactory();

        $this->truncateTables();
    }

    protected function tearDown(): void {
        $this->truncateTables();
        parent::tearDown();
    }

    public function testCurrentDistributionUsesLatestReportWhileHistoryKeepsAllReports(): void {
        $this->store('installation-a', '2026-06-01T00:00:00Z', '2026-07-01T00:00:00Z', '1.0.0');
        $this->store('installation-a', '2026-07-01T00:00:00Z', '2026-08-01T00:00:00Z', '1.1.0');
        $this->store('installation-b', '2026-07-01T00:00:00Z', '2026-08-01T00:00:00Z', '1.0.0');

        self::assertSame(2, $this->statistics->countActiveInstallations(
            'libresign',
            new \DateTimeImmutable('-1 day', new \DateTimeZone('UTC')),
        ));

        self::assertSame([
            ['value' => '1.0.0', 'count' => 1],
            ['value' => '1.1.0', 'count' => 1],
        ], $this->statistics->currentDistribution('libresign', 'server', 'version'));

        $history = $this->statistics->metricHistory(
            'libresign',
            'server',
            'version',
            new \DateTimeImmutable('2026-06-01T00:00:00Z'),
            new \DateTimeImmutable('2026-08-02T00:00:00Z'),
        );

        self::assertCount(3, $history);
        self::assertSame(['1.0.0', '1.1.0', '1.0.0'], array_column($history, 'value'));
    }

    private function store(string $installationId, string $start, string $end, string $version): void {
        $payload = [
            'protocolVersion' => 1,
            'application' => 'libresign',
            'installationId' => $installationId,
            'schemaVersion' => 1,
            'period' => [
                'start' => $start,
                'end' => $end,
            ],
            'metrics' => [[
                'category' => 'server',
                'key' => 'version',
                'type' => 'string',
                'value' => $version,
            ]],
        ];

        $this->reports->store($this->factory->fromPayload($payload), $payload);
    }

    private function truncateTables(): void {
        $this->db->getQueryBuilder()->delete('usage_stats_metrics')->executeStatement();
        $this->db->getQueryBuilder()->delete('usage_stats_installations')->executeStatement();
        $this->db->getQueryBuilder()->delete('usage_stats_reports')->executeStatement();
    }
}
