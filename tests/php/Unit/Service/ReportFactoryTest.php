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

        self::assertSame(1, $report->protocolVersion);
        self::assertSame('libresign', $report->application);
        self::assertSame(str_repeat('a', 64), $report->installationId);
        self::assertSame(1, $report->schemaVersion);
        self::assertSame('2026-08-01T03:00:00+00:00', $report->periodStart->format(DATE_ATOM));
        self::assertSame('2026-09-01T03:00:00+00:00', $report->periodEnd->format(DATE_ATOM));
        self::assertSame('UTC', $report->periodStart->getTimezone()->getName());
        self::assertSame('usage', $report->metrics[0]->category);
        self::assertSame('requests_completed', $report->metrics[0]->key);
        self::assertSame('integer', $report->metrics[0]->type);
        self::assertSame(72, $report->metrics[0]->value);
    }

    public function testAcceptsRfc3339BoundaryValues(): void {
        $payload = $this->validPayload();
        $payload['period'] = [
            'start' => '2026-08-01T23:59:59.123456+23:59',
            'end' => '2026-08-02T23:59:59.123456+23:59',
        ];

        $report = (new ReportFactory())->fromPayload($payload);

        self::assertSame('2026-08-01T00:00:59+00:00', $report->periodStart->format(DATE_ATOM));
        self::assertSame('2026-08-02T00:00:59+00:00', $report->periodEnd->format(DATE_ATOM));
    }

    public function testRejectsDuplicateMetric(): void {
        $payload = $this->validPayload();
        $payload['metrics'][] = $payload['metrics'][0];

        $this->expectException(InvalidReport::class);
        (new ReportFactory())->fromPayload($payload);
    }

    public function testAllowsSameKeyInDifferentCategories(): void {
        $payload = $this->validPayload();
        $payload['metrics'][] = [
            'category' => 'other',
            'key' => 'requests_completed',
            'type' => 'integer',
            'value' => 1,
        ];

        $report = (new ReportFactory())->fromPayload($payload);
        self::assertCount(2, $report->metrics);
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
        yield 'invalid month zero' => ['2026-00-01T00:00:00Z'];
        yield 'invalid month thirteen' => ['2026-13-01T00:00:00Z'];
        yield 'invalid day zero' => ['2026-01-00T00:00:00Z'];
        yield 'invalid hour' => ['2026-08-01T24:00:00Z'];
        yield 'invalid minute' => ['2026-08-01T23:60:00Z'];
        yield 'invalid second' => ['2026-08-01T23:59:60Z'];
        yield 'invalid timezone hour' => ['2026-08-01T00:00:00+24:00'];
        yield 'invalid timezone minute' => ['2026-08-01T00:00:00+23:60'];
        yield 'too much precision' => ['2026-08-01T00:00:00.1234567Z'];
    }

    #[DataProvider('invalidPeriodContainerProvider')]
    public function testRejectsInvalidPeriodContainer(mixed $period): void {
        $payload = $this->validPayload();
        $payload['period'] = $period;

        $this->expectException(InvalidReport::class);
        (new ReportFactory())->fromPayload($payload);
    }

    /** @return iterable<string,array{mixed}> */
    public static function invalidPeriodContainerProvider(): iterable {
        yield 'null' => [null];
        yield 'string' => ['period'];
        yield 'integer' => [1];
        yield 'list' => [['2026-08-01T00:00:00Z', '2026-08-02T00:00:00Z']];
    }

    #[DataProvider('invalidPeriodProvider')]
    public function testRejectsInvalidPeriod(string $start, string $end): void {
        $payload = $this->validPayload();
        $payload['period'] = ['start' => $start, 'end' => $end];

        $this->expectException(InvalidReport::class);
        (new ReportFactory())->fromPayload($payload);
    }

    /** @return iterable<string,array{string,string}> */
    public static function invalidPeriodProvider(): iterable {
        yield 'same instant' => ['2026-08-01T00:00:00Z', '2026-08-01T00:00:00Z'];
        yield 'end before start' => ['2026-08-02T00:00:00Z', '2026-08-01T00:00:00Z'];
        yield 'more than 31 days' => ['2026-08-01T00:00:00Z', '2026-09-01T00:00:01Z'];
    }

    public function testAcceptsMaximumPeriodLength(): void {
        $payload = $this->validPayload();
        $payload['period'] = [
            'start' => '2026-08-01T00:00:00Z',
            'end' => '2026-09-01T00:00:00Z',
        ];

        self::assertInstanceOf(\DateTimeImmutable::class, (new ReportFactory())->fromPayload($payload)->periodEnd);
    }

    #[DataProvider('invalidScalarProvider')]
    public function testRejectsInvalidTopLevelScalar(string $field, mixed $value): void {
        $payload = $this->validPayload();
        $payload[$field] = $value;

        $this->expectException(InvalidReport::class);
        (new ReportFactory())->fromPayload($payload);
    }

    /** @return iterable<string,array{string,mixed}> */
    public static function invalidScalarProvider(): iterable {
        yield 'protocol version' => ['protocolVersion', 2];
        yield 'schema version zero' => ['schemaVersion', 0];
        yield 'schema version string' => ['schemaVersion', '1'];
        yield 'application null' => ['application', null];
        yield 'application integer' => ['application', 1];
        yield 'application empty' => ['application', ''];
        yield 'application invalid chars' => ['application', 'libre sign'];
        yield 'application too long' => ['application', str_repeat('a', 129)];
        yield 'installation id null' => ['installationId', null];
        yield 'installation id too long' => ['installationId', str_repeat('a', 129)];
    }

    #[DataProvider('invalidMetricsContainerProvider')]
    public function testRejectsInvalidMetricsContainer(mixed $metrics): void {
        $payload = $this->validPayload();
        $payload['metrics'] = $metrics;

        $this->expectException(InvalidReport::class);
        (new ReportFactory())->fromPayload($payload);
    }

    /** @return iterable<string,array{mixed}> */
    public static function invalidMetricsContainerProvider(): iterable {
        yield 'null' => [null];
        yield 'object-like array' => [['metric' => []]];
        yield 'empty list' => [[]];
        yield 'too many' => [array_fill(0, 257, [
            'category' => 'usage',
            'key' => 'same',
            'type' => 'integer',
            'value' => 1,
        ])];
    }

    public function testAcceptsMaximumNumberOfMetrics(): void {
        $payload = $this->validPayload();
        $payload['metrics'] = [];
        for ($i = 0; $i < 256; ++$i) {
            $payload['metrics'][] = [
                'category' => 'usage',
                'key' => 'metric_' . $i,
                'type' => 'integer',
                'value' => $i,
            ];
        }

        self::assertCount(256, (new ReportFactory())->fromPayload($payload)->metrics);
    }

    #[DataProvider('invalidMetricProvider')]
    public function testRejectsInvalidMetric(mixed $metric): void {
        $payload = $this->validPayload();
        $payload['metrics'] = [$metric];

        $this->expectException(InvalidReport::class);
        (new ReportFactory())->fromPayload($payload);
    }

    /** @return iterable<string,array{mixed}> */
    public static function invalidMetricProvider(): iterable {
        yield 'scalar metric' => ['metric'];
        yield 'list metric' => [['usage', 'x', 'integer', 1]];
        yield 'unknown type' => [[
            'category' => 'usage', 'key' => 'x', 'type' => 'unknown', 'value' => 1,
        ]];
        yield 'integer with float' => [[
            'category' => 'usage', 'key' => 'x', 'type' => 'integer', 'value' => 1.5,
        ]];
        yield 'number with string' => [[
            'category' => 'usage', 'key' => 'x', 'type' => 'number', 'value' => '1',
        ]];
        yield 'number with infinity' => [[
            'category' => 'usage', 'key' => 'x', 'type' => 'number', 'value' => INF,
        ]];
        yield 'boolean with integer' => [[
            'category' => 'usage', 'key' => 'x', 'type' => 'boolean', 'value' => 1,
        ]];
        yield 'string too long' => [[
            'category' => 'usage', 'key' => 'x', 'type' => 'string', 'value' => str_repeat('x', 1025),
        ]];
        yield 'category too long' => [[
            'category' => str_repeat('a', 129), 'key' => 'x', 'type' => 'integer', 'value' => 1,
        ]];
        yield 'key too long' => [[
            'category' => 'usage', 'key' => str_repeat('a', 513), 'type' => 'integer', 'value' => 1,
        ]];
    }

    public function testAcceptsBoundaryMetricValues(): void {
        $payload = $this->validPayload();
        $payload['metrics'] = [
            ['category' => str_repeat('a', 128), 'key' => str_repeat('b', 512), 'type' => 'string', 'value' => str_repeat('x', 1024)],
            ['category' => 'usage', 'key' => 'number_int', 'type' => 'number', 'value' => 1],
            ['category' => 'usage', 'key' => 'number_float', 'type' => 'number', 'value' => 1.5],
            ['category' => 'usage', 'key' => 'flag', 'type' => 'boolean', 'value' => false],
        ];

        self::assertCount(4, (new ReportFactory())->fromPayload($payload)->metrics);
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
