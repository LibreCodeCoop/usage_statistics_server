<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\UsageStatisticsServer\Tests\Integration\Service;

use OCA\UsageStatisticsServer\Db\ReportRepository;
use OCA\UsageStatisticsServer\Db\SchemaRepository;
use OCA\UsageStatisticsServer\Service\InvalidReport;
use OCA\UsageStatisticsServer\Service\ReportFactory;
use OCA\UsageStatisticsServer\Service\ReportSubmissionService;
use OCA\UsageStatisticsServer\Service\SchemaValidator;
use OCP\IDBConnection;
use OCP\Server;
use PHPUnit\Framework\Attributes\Group;
use Test\TestCase;

#[Group('DB')]
final class ReportSubmissionServiceTest extends TestCase {
    private IDBConnection $db;
    private SchemaRepository $schemas;
    private ReportSubmissionService $submission;

    protected function setUp(): void {
        parent::setUp();
        $this->db = Server::get(IDBConnection::class);
        $this->schemas = new SchemaRepository($this->db);
        $this->submission = new ReportSubmissionService(
            new ReportFactory(),
            new ReportRepository($this->db),
            $this->schemas,
            new SchemaValidator(),
        );
        $this->clearTables();
    }

    protected function tearDown(): void {
        $this->clearTables();
        parent::tearDown();
    }

    public function testSubmitsReportAgainstRegisteredSchema(): void {
        $definition = [
            'application' => 'integration_app',
            'schemaVersion' => 1,
            'metrics' => [[
                'category' => 'usage',
                'key' => 'requests_completed',
                'type' => 'integer',
                'kind' => 'period',
                'aggregation' => 'numerical',
                'description' => 'Completed requests',
                'required' => true,
            ]],
        ];
        $this->schemas->store('integration_app', 1, $definition);

        $this->submission->submit($this->payload());

        self::assertSame(1, $this->countRows('usage_stats_reports'));
        self::assertSame(1, $this->countRows('usage_stats_metrics'));
    }

    public function testRejectsReportWhenSchemaIsNotRegistered(): void {
        $this->expectException(InvalidReport::class);
        $this->expectExceptionMessage('Application schema is not registered.');

        $this->submission->submit($this->payload());
    }

    /**
     * @return array{
     *     protocolVersion:int,
     *     application:string,
     *     installationId:string,
     *     schemaVersion:int,
     *     period:array{start:string,end:string},
     *     metrics:list<array{category:string,key:string,type:string,value:int}>
     * }
     */
    private function payload(): array {
        return [
            'protocolVersion' => 1,
            'application' => 'integration_app',
            'installationId' => 'integration-installation',
            'schemaVersion' => 1,
            'period' => [
                'start' => '2026-08-01T00:00:00Z',
                'end' => '2026-09-01T00:00:00Z',
            ],
            'metrics' => [[
                'category' => 'usage',
                'key' => 'requests_completed',
                'type' => 'integer',
                'value' => 12,
            ]],
        ];
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
}
