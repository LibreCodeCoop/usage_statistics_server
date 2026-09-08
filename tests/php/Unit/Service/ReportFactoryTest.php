<?php

declare(strict_types=1);

namespace OCA\UsageStatisticsServer\Tests\Unit\Service;

use OCA\UsageStatisticsServer\Service\InvalidReport;
use OCA\UsageStatisticsServer\Service\ReportFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReportFactoryTest extends TestCase {
    public function testBuildsValidReportAndNormalizesPeriodToUtc(): void {
        $report = (new ReportFactory())->fromPayload($this->validPayload());

        self::assertSame('libresign', $report->application);
        self::assertSame('UTC', $report->periodStart->getTimezone()->getName());
        self::assertSame(72, $report->metrics[0]->value);
    }

    public function testRejectsDuplicateMetric(): void {
        $payload = $this->validPayload();
        $payload['metrics'][] = $payload['metrics'][0];

        $this->expectException(InvalidReport::class);
        (new ReportFactory())->fromPayload($payload);
    }

    #[DataProvider('invalidTimestampProvider')]
    public function testRejectsNonRfc3339OrInvalidTimestamp(string $timestamp): void {
        $payload = $this->validPayload();
        $payload['period']['start'] = $timestamp;

        $this->expectException(InvalidReport::class);
        (new ReportFactory())->fromPayload($payload);
    }

    /** @return iterable<string,array{string}> */
    public static function invalidTimestampProvider(): iterable {
        yield 'date only' => ['2026-08-01'];
        yield 'space separator' => ['2026-08-01 00:00:00Z'];
        yield 'missing timezone' => ['2026-08-01T00:00:00'];
        yield 'invalid calendar date' => ['2026-02-30T00:00:00Z'];
        yield 'invalid hour' => ['2026-08-01T25:00:00Z'];
        yield 'invalid timezone' => ['2026-08-01T00:00:00+25:00'];
    }

    #[DataProvider('unknownFieldProvider')]
    public function testRejectsUnknownFields(string $scope): void {
        $payload = $this->validPayload();

        match ($scope) {
            'report' => $payload['unexpected'] = true,
            'period' => $payload['period']['unexpected'] = true,
            'metric' => $payload['metrics'][0]['unexpected'] = true,
        };

        $this->expectException(InvalidReport::class);
        (new ReportFactory())->fromPayload($payload);
    }

    /** @return iterable<string,array{string}> */
    public static function unknownFieldProvider(): iterable {
        yield 'report' => ['report'];
        yield 'period' => ['period'];
        yield 'metric' => ['metric'];
    }

    /** @return array<string,mixed> */
    private function validPayload(): array {
        return [
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
        ];
    }
}
