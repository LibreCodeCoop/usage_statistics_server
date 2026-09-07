<?php

declare(strict_types=1);

namespace OCA\UsageStatisticsServer\Tests\Unit\Service;

use OCA\UsageStatisticsServer\Service\InvalidReport;
use OCA\UsageStatisticsServer\Service\ReportFactory;
use PHPUnit\Framework\TestCase;

final class ReportFactoryTest extends TestCase {
    public function testBuildsValidReportAndNormalizesPeriodToUtc(): void {
        $report = (new ReportFactory())->fromPayload([
            'protocolVersion' => 1,
            'application' => 'libresign',
            'installationId' => str_repeat('a', 64),
            'schemaVersion' => 1,
            'period' => [
                'start' => '2026-08-01T00:00:00-03:00',
                'end' => '2026-09-01T00:00:00-03:00',
            ],
            'metrics' => [[
                'category' => 'usage',
                'key' => 'requests_completed',
                'type' => 'integer',
                'value' => 72,
            ]],
        ]);

        self::assertSame('libresign', $report->application);
        self::assertSame('UTC', $report->periodStart->getTimezone()->getName());
        self::assertSame(72, $report->metrics[0]->value);
    }

    public function testRejectsDuplicateMetric(): void {
        $this->expectException(InvalidReport::class);
        (new ReportFactory())->fromPayload([
            'protocolVersion' => 1,
            'application' => 'libresign',
            'installationId' => str_repeat('a', 64),
            'schemaVersion' => 1,
            'period' => ['start' => '2026-08-01T00:00:00Z', 'end' => '2026-09-01T00:00:00Z'],
            'metrics' => [
                ['category' => 'usage', 'key' => 'requests', 'type' => 'integer', 'value' => 1],
                ['category' => 'usage', 'key' => 'requests', 'type' => 'integer', 'value' => 2],
            ],
        ]);
    }
}
