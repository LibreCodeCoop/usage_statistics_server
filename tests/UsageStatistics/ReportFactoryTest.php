<?php

declare(strict_types=1);

namespace App\Tests\UsageStatistics;

use App\UsageStatistics\InvalidReport;
use App\UsageStatistics\ReportFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReportFactoryTest extends TestCase
{
    public function testBuildsValidReportAndNormalizesPeriodToUtc(): void
    {
        $payload = self::payload();
        $payload['period']['start'] = '2026-07-31T21:00:00-03:00';

        $report = (new ReportFactory())->fromPayload($payload);

        self::assertSame('libresign', $report->application);
        self::assertSame('installation-1', $report->installationId);
        self::assertSame('2026-08-01T00:00:00+00:00', $report->periodStart->format(DATE_ATOM));
        self::assertCount(2, $report->metrics);
    }

    #[DataProvider('invalidPayloads')]
    public function testRejectsInvalidReports(array $payload): void
    {
        $this->expectException(InvalidReport::class);
        (new ReportFactory())->fromPayload($payload);
    }

    public static function invalidPayloads(): iterable
    {
        $base = self::payload();

        $unsupportedProtocol = $base;
        $unsupportedProtocol['protocolVersion'] = 2;
        yield 'unsupported protocol' => [$unsupportedProtocol];

        $invalidPeriod = $base;
        $invalidPeriod['period']['end'] = $invalidPeriod['period']['start'];
        yield 'empty period' => [$invalidPeriod];

        $wrongType = $base;
        $wrongType['metrics'][1]['value'] = '72';
        yield 'wrong metric type' => [$wrongType];

        $duplicateMetric = $base;
        $duplicateMetric['metrics'][] = $duplicateMetric['metrics'][0];
        yield 'duplicate metric' => [$duplicateMetric];
    }

    private static function payload(): array
    {
        return [
            'protocolVersion' => 1,
            'application' => 'libresign',
            'installationId' => 'installation-1',
            'schemaVersion' => 1,
            'period' => [
                'start' => '2026-08-01T00:00:00+00:00',
                'end' => '2026-09-01T00:00:00+00:00',
            ],
            'metrics' => [
                ['category' => 'environment', 'key' => 'version', 'type' => 'string', 'value' => '12.0.0'],
                ['category' => 'usage', 'key' => 'requests_completed', 'type' => 'integer', 'value' => 72],
            ],
        ];
    }
}
