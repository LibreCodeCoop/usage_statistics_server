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
final class OutOfOrderReportTest extends TestCase {
    private IDBConnection $db;

    protected function setUp(): void {
        parent::setUp();
        $this->db = Server::get(IDBConnection::class);
        $this->truncateTables();
    }

    protected function tearDown(): void {
        $this->truncateTables();
        parent::tearDown();
    }

    public function testDelayedHistoricalReportDoesNotReplaceCurrentState(): void {
        $factory = new ReportFactory();
        $reports = new ReportRepository($this->db);
        $statistics = new StatisticsRepository($this->db);

        $reports->store($factory->fromPayload($this->payload(
            '2026-08-01T00:00:00Z',
            '2026-09-01T00:00:00Z',
            '2.0.0',
            20,
        )));
        $reports->store($factory->fromPayload($this->payload(
            '2026-07-01T00:00:00Z',
            '2026-08-01T00:00:00Z',
            '1.0.0',
            10,
        )));

        self::assertSame([
            ['value' => '2.0.0', 'count' => 1],
        ], $statistics->currentDistribution('libresign', 'server', 'version'));

        self::assertSame([
            'count' => 1,
            'average' => 20.0,
            'min' => 20.0,
            'max' => 20.0,
            'total' => 20.0,
        ], $statistics->currentNumericalEvaluation('libresign', 'usage', 'requests_completed'));

        $installationQb = $this->db->getQueryBuilder();
        $installation = $installationQb->select('last_period_start', 'last_period_end')
            ->from('usage_stats_installations')
            ->where($installationQb->expr()->eq('application', $installationQb->createNamedParameter('libresign')))
            ->andWhere($installationQb->expr()->eq('installation_id', $installationQb->createNamedParameter('installation-delayed')))
            ->executeQuery()
            ->fetchAssociative();

        self::assertIsArray($installation);
        self::assertSame('2026-08-01 00:00:00', (string)$installation['last_period_start']);
        self::assertSame('2026-09-01 00:00:00', (string)$installation['last_period_end']);

        $history = $statistics->numericalHistory(
            'libresign',
            'usage',
            'requests_completed',
            new \DateTimeImmutable('2026-07-01T00:00:00Z'),
            new \DateTimeImmutable('2026-09-02T00:00:00Z'),
        );
        self::assertCount(2, $history);
    }

    /** @return array<string,mixed> */
    private function payload(string $start, string $end, string $version, int $completed): array {
        return [
            'protocolVersion' => 1,
            'application' => 'libresign',
            'installationId' => 'installation-delayed',
            'schemaVersion' => 1,
            'period' => ['start' => $start, 'end' => $end],
            'metrics' => [
                ['category' => 'server', 'key' => 'version', 'type' => 'string', 'value' => $version],
                ['category' => 'usage', 'key' => 'requests_completed', 'type' => 'integer', 'value' => $completed],
            ],
        ];
    }

    private function truncateTables(): void {
        $this->db->getQueryBuilder()->delete('usage_stats_metrics')->executeStatement();
        $this->db->getQueryBuilder()->delete('usage_stats_installations')->executeStatement();
        $this->db->getQueryBuilder()->delete('usage_stats_reports')->executeStatement();
    }
}
