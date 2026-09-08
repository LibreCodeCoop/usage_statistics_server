<?php

declare(strict_types=1);

namespace OCA\UsageStatisticsServer\Tests\Integration\Db;

use OCA\UsageStatisticsServer\Db\ReportRepository;
use OCA\UsageStatisticsServer\Db\StatisticsRepository;
use OCA\UsageStatisticsServer\Service\ConflictingReport;
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

    public function testCurrentStateAndHistoricalNumericalAggregations(): void {
        $this->store('installation-a', '2026-06-01T00:00:00Z', '2026-07-01T00:00:00Z', '1.0.0', 10);
        $this->store('installation-a', '2026-07-01T00:00:00Z', '2026-08-01T00:00:00Z', '1.1.0', 20);
        $this->store('installation-b', '2026-07-01T00:00:00Z', '2026-08-01T00:00:00Z', '1.0.0', 40);

        self::assertSame(2, $this->statistics->countActiveInstallations(
            'libresign',
            new \DateTimeImmutable('-1 day', new \DateTimeZone('UTC')),
        ));

        self::assertSame([
            ['value' => '1.0.0', 'count' => 1],
            ['value' => '1.1.0', 'count' => 1],
        ], $this->statistics->currentDistribution('libresign', 'server', 'version'));

        self::assertSame([
            ['value' => true, 'count' => 2],
        ], $this->statistics->currentDistribution('libresign', 'features', 'enabled'));

        self::assertSame([
            'count' => 2,
            'average' => 30.0,
            'min' => 20.0,
            'max' => 40.0,
            'total' => 60.0,
        ], $this->statistics->currentNumericalEvaluation('libresign', 'usage', 'requests_completed'));

        self::assertSame([
            'count' => 2,
            'average' => 1.5,
            'min' => 1.5,
            'max' => 1.5,
            'total' => 3.0,
        ], $this->statistics->currentNumericalEvaluation('libresign', 'usage', 'average_size'));

        $history = $this->statistics->numericalHistory(
            'libresign',
            'usage',
            'requests_completed',
            new \DateTimeImmutable('2026-06-01T00:00:00Z'),
            new \DateTimeImmutable('2026-08-02T00:00:00Z'),
        );

        self::assertCount(2, $history);
        self::assertSame(1, $history[0]['count']);
        self::assertSame(10.0, $history[0]['total']);
        self::assertSame(2, $history[1]['count']);
        self::assertSame(30.0, $history[1]['average']);
        self::assertSame(60.0, $history[1]['total']);
    }

    public function testMetricValuesAreStoredInTheirTypedColumn(): void {
        $this->store('installation-a', '2026-07-01T00:00:00Z', '2026-08-01T00:00:00Z', '1.1.0', 20);

        $qb = $this->db->getQueryBuilder();
        $rows = $qb->select('metric_type', 'value_integer', 'value_number', 'value_boolean', 'value_string')
            ->from('usage_stats_metrics')
            ->orderBy('metric_type', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();

        self::assertCount(4, $rows);

        $integer = $this->findMetricRow($rows, 'integer');
        self::assertSame(20, (int)$integer['value_integer']);
        self::assertNull($integer['value_number']);
        self::assertNull($integer['value_boolean']);
        self::assertNull($integer['value_string']);

        $number = $this->findMetricRow($rows, 'number');
        self::assertSame(1.5, (float)$number['value_number']);
        self::assertNull($number['value_integer']);
        self::assertNull($number['value_boolean']);
        self::assertNull($number['value_string']);

        $boolean = $this->findMetricRow($rows, 'boolean');
        self::assertTrue($this->toBoolean($boolean['value_boolean']));
        self::assertNull($boolean['value_integer']);
        self::assertNull($boolean['value_number']);
        self::assertNull($boolean['value_string']);

        $string = $this->findMetricRow($rows, 'string');
        self::assertSame('1.1.0', $string['value_string']);
        self::assertNull($string['value_integer']);
        self::assertNull($string['value_number']);
        self::assertNull($string['value_boolean']);
    }

    public function testSameLogicalPeriodWithDifferentSchemaIsRejected(): void {
        $payload = $this->payload('installation-a', '2026-07-01T00:00:00Z', '2026-08-01T00:00:00Z', '1.1.0', 20, 1);
        $this->reports->store($this->factory->fromPayload($payload));

        $conflicting = $this->payload('installation-a', '2026-07-01T00:00:00Z', '2026-08-01T00:00:00Z', '1.1.0', 20, 2);

        $this->expectException(ConflictingReport::class);
        $this->reports->store($this->factory->fromPayload($conflicting));
    }

    public function testSameLogicalReportIsIdempotent(): void {
        $payload = $this->payload('installation-a', '2026-07-01T00:00:00Z', '2026-08-01T00:00:00Z', '1.1.0', 20, 1);
        $report = $this->factory->fromPayload($payload);

        self::assertTrue($this->reports->store($report)['created']);
        self::assertFalse($this->reports->store($report)['created']);

        $qb = $this->db->getQueryBuilder();
        self::assertSame(1, (int)$qb->select($qb->func()->count())->from('usage_stats_reports')->executeQuery()->fetchOne());
    }

    private function store(
        string $installationId,
        string $start,
        string $end,
        string $version,
        int $requestsCompleted,
    ): void {
        $payload = $this->payload($installationId, $start, $end, $version, $requestsCompleted, 1);
        $this->reports->store($this->factory->fromPayload($payload));
    }

    /** @return array<string,mixed> */
    private function payload(
        string $installationId,
        string $start,
        string $end,
        string $version,
        int $requestsCompleted,
        int $schemaVersion,
    ): array {
        return [
            'protocolVersion' => 1,
            'application' => 'libresign',
            'installationId' => $installationId,
            'schemaVersion' => $schemaVersion,
            'period' => [
                'start' => $start,
                'end' => $end,
            ],
            'metrics' => [
                [
                    'category' => 'server',
                    'key' => 'version',
                    'type' => 'string',
                    'value' => $version,
                ],
                [
                    'category' => 'usage',
                    'key' => 'requests_completed',
                    'type' => 'integer',
                    'value' => $requestsCompleted,
                ],
                [
                    'category' => 'usage',
                    'key' => 'average_size',
                    'type' => 'number',
                    'value' => 1.5,
                ],
                [
                    'category' => 'features',
                    'key' => 'enabled',
                    'type' => 'boolean',
                    'value' => true,
                ],
            ],
        ];
    }

    /** @param list<array<string,mixed>> $rows
     *  @return array<string,mixed>
     */
    private function findMetricRow(array $rows, string $type): array {
        return array_values(array_filter($rows, static fn (array $row): bool => $row['metric_type'] === $type))[0];
    }

    private function toBoolean(mixed $value): bool {
        if (is_bool($value)) {
            return $value;
        }

        return in_array($value, [1, '1', 't', 'true'], true);
    }

    private function truncateTables(): void {
        $this->db->getQueryBuilder()->delete('usage_stats_metrics')->executeStatement();
        $this->db->getQueryBuilder()->delete('usage_stats_installations')->executeStatement();
        $this->db->getQueryBuilder()->delete('usage_stats_reports')->executeStatement();
    }
}
