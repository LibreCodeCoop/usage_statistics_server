<?php

declare(strict_types=1);

namespace OCA\UsageStatisticsServer\Tests\Integration\Service;

use OCA\UsageStatisticsServer\Db\ReportRepository;
use OCA\UsageStatisticsServer\Db\SchemaRepository;
use OCA\UsageStatisticsServer\Service\ReportFactory;
use OCA\UsageStatisticsServer\Service\RetentionService;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Server;
use PHPUnit\Framework\Attributes\Group;
use Test\TestCase;

#[Group('DB')]
final class RetentionServiceTest extends TestCase {
    private const DB_DATETIME_FORMAT = 'Y-m-d H:i:s';

    private IDBConnection $db;
    private ReportRepository $reports;
    private SchemaRepository $schemas;
    private RetentionService $retention;
    private ReportFactory $factory;

    protected function setUp(): void {
        parent::setUp();
        $this->db = Server::get(IDBConnection::class);
        $this->reports = new ReportRepository($this->db);
        $this->schemas = new SchemaRepository($this->db);
        $this->retention = new RetentionService($this->db);
        $this->factory = new ReportFactory();
        $this->clearTables();
    }

    protected function tearDown(): void {
        $this->clearTables();
        parent::tearDown();
    }

    public function testCleanupRemovesOldReportsAndKeepsSchemasAndRecentData(): void {
        $this->schemas->store('libresign', 1, [
            'application' => 'libresign',
            'schemaVersion' => 1,
            'metrics' => [],
        ]);

        $old = $this->store('old-installation', '2025-01-01T00:00:00Z', '2025-02-01T00:00:00Z');
        $recent = $this->store('recent-installation', '2026-08-01T00:00:00Z', '2026-09-01T00:00:00Z');

        $this->setReportReceivedAt($old, '2025-02-02T00:00:00Z');
        $this->setInstallationLastSeen('old-installation', '2025-02-02T00:00:00Z');
        $this->setReportReceivedAt($recent, '2026-09-02T00:00:00Z');
        $this->setInstallationLastSeen('recent-installation', '2026-09-02T00:00:00Z');

        $deleted = $this->retention->cleanupBefore(new \DateTimeImmutable('2026-01-01T00:00:00Z'));

        self::assertSame(1, $deleted['installations']);
        self::assertSame(1, $deleted['reports']);
        self::assertSame(1, $deleted['metrics']);
        self::assertSame(1, $this->countRows('usage_stats_schemas'));
        self::assertSame(1, $this->countRows('usage_stats_installations'));
        self::assertSame(1, $this->countRows('usage_stats_reports'));
        self::assertSame(1, $this->countRows('usage_stats_metrics'));
    }

    private function store(string $installationId, string $start, string $end): int {
        $payload = [
            'protocolVersion' => 1,
            'application' => 'libresign',
            'installationId' => $installationId,
            'schemaVersion' => 1,
            'period' => ['start' => $start, 'end' => $end],
            'metrics' => [[
                'category' => 'usage',
                'key' => 'requests_completed',
                'type' => 'integer',
                'value' => 1,
            ]],
        ];

        return $this->reports->store($this->factory->fromPayload($payload))['id'];
    }

    private function setReportReceivedAt(int $reportId, string $date): void {
        $qb = $this->db->getQueryBuilder();
        $qb->update('usage_stats_reports')
            ->set('received_at', $qb->createNamedParameter($this->formatDateTime($date)))
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($reportId, IQueryBuilder::PARAM_INT)))
            ->executeStatement();
    }

    private function setInstallationLastSeen(string $installationId, string $date): void {
        $qb = $this->db->getQueryBuilder();
        $qb->update('usage_stats_installations')
            ->set('last_seen_at', $qb->createNamedParameter($this->formatDateTime($date)))
            ->where($qb->expr()->eq('installation_id', $qb->createNamedParameter($installationId)))
            ->executeStatement();
    }

    private function countRows(string $table): int {
        $qb = $this->db->getQueryBuilder();
        return (int)$qb
            ->select($qb->func()->count())
            ->from($table)
            ->executeQuery()
            ->fetchOne();
    }

    private function clearTables(): void {
        $this->db->getQueryBuilder()->delete('usage_stats_metrics')->executeStatement();
        $this->db->getQueryBuilder()->delete('usage_stats_installations')->executeStatement();
        $this->db->getQueryBuilder()->delete('usage_stats_reports')->executeStatement();
        $this->db->getQueryBuilder()->delete('usage_stats_schemas')->executeStatement();
    }

    private function formatDateTime(string $date): string {
        return (new \DateTimeImmutable($date))
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format(self::DB_DATETIME_FORMAT);
    }
}
