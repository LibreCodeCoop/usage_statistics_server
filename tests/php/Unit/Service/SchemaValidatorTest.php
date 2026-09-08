<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\UsageStatisticsServer\Tests\Unit\Service;

use OCA\UsageStatisticsServer\Service\InvalidReport;
use OCA\UsageStatisticsServer\Service\Metric;
use OCA\UsageStatisticsServer\Service\Report;
use OCA\UsageStatisticsServer\Service\SchemaValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SchemaValidatorTest extends TestCase {
    private SchemaValidator $validator;

    protected function setUp(): void {
        $this->validator = new SchemaValidator();
    }

    public function testValidDefinitionIsNormalized(): void {
        $definition = $this->validator->validateDefinition($this->definition());

        self::assertSame('libresign', $definition['application']);
        self::assertSame(1, $definition['schemaVersion']);
        self::assertCount(2, $definition['metrics']);
        self::assertSame([
            'category' => 'server',
            'key' => 'version',
            'type' => 'string',
            'kind' => 'snapshot',
            'aggregation' => 'distribution',
            'description' => 'LibreSign version',
            'required' => true,
        ], $definition['metrics'][0]);
    }

    public function testDefaultsDescriptionAndRequired(): void {
        $definition = $this->definition();
        unset($definition['metrics'][0]['description'], $definition['metrics'][0]['required']);

        $validated = $this->validator->validateDefinition($definition);
        self::assertSame('', $validated['metrics'][0]['description']);
        self::assertFalse($validated['metrics'][0]['required']);
    }

    #[DataProvider('invalidDefinitionScalarProvider')]
    public function testRejectsInvalidDefinitionScalar(string $field, mixed $value): void {
        $definition = $this->definition();
        $definition[$field] = $value;

        $this->expectException(InvalidReport::class);
        $this->validator->validateDefinition($definition);
    }

    /** @return iterable<string,array{string,mixed}> */
    public static function invalidDefinitionScalarProvider(): iterable {
        yield 'null application' => ['application', null];
        yield 'integer application' => ['application', 1];
        yield 'empty application' => ['application', ''];
        yield 'invalid application chars' => ['application', 'libre sign'];
        yield 'application too long' => ['application', str_repeat('a', 129)];
        yield 'schema version zero' => ['schemaVersion', 0];
        yield 'schema version string' => ['schemaVersion', '1'];
    }

    #[DataProvider('invalidMetricsContainerProvider')]
    public function testRejectsInvalidMetricsContainer(mixed $metrics): void {
        $definition = $this->definition();
        $definition['metrics'] = $metrics;

        $this->expectException(InvalidReport::class);
        $this->validator->validateDefinition($definition);
    }

    /** @return iterable<string,array{mixed}> */
    public static function invalidMetricsContainerProvider(): iterable {
        yield 'null' => [null];
        yield 'object-like array' => [['metric' => []]];
        yield 'empty list' => [[]];
        yield 'too many metrics' => [array_fill(0, 257, [
            'category' => 'usage',
            'key' => 'same',
            'type' => 'integer',
            'kind' => 'period',
            'aggregation' => 'numerical',
        ])];
    }

    public function testAcceptsMaximumNumberOfMetrics(): void {
        $definition = $this->definition();
        $definition['metrics'] = [];
        for ($i = 0; $i < 256; ++$i) {
            $definition['metrics'][] = [
                'category' => 'usage',
                'key' => 'metric_' . $i,
                'type' => 'integer',
                'kind' => 'period',
                'aggregation' => 'numerical',
            ];
        }

        self::assertCount(256, $this->validator->validateDefinition($definition)['metrics']);
    }

    #[DataProvider('invalidMetricDefinitionProvider')]
    public function testRejectsInvalidMetricDefinition(mixed $metric): void {
        $definition = $this->definition();
        $definition['metrics'] = [$metric];

        $this->expectException(InvalidReport::class);
        $this->validator->validateDefinition($definition);
    }

    /** @return iterable<string,array{mixed}> */
    public static function invalidMetricDefinitionProvider(): iterable {
        yield 'scalar metric' => ['metric'];
        yield 'list metric' => [['usage', 'x', 'integer']];
        yield 'category too long' => [[
            'category' => str_repeat('a', 129), 'key' => 'x', 'type' => 'integer', 'kind' => 'period', 'aggregation' => 'numerical',
        ]];
        yield 'key too long' => [[
            'category' => 'usage', 'key' => str_repeat('a', 513), 'type' => 'integer', 'kind' => 'period', 'aggregation' => 'numerical',
        ]];
        yield 'unknown type' => [[
            'category' => 'usage', 'key' => 'x', 'type' => 'unknown', 'kind' => 'period', 'aggregation' => 'none',
        ]];
        yield 'non-string type' => [[
            'category' => 'usage', 'key' => 'x', 'type' => 1, 'kind' => 'period', 'aggregation' => 'none',
        ]];
        yield 'unknown kind' => [[
            'category' => 'usage', 'key' => 'x', 'type' => 'integer', 'kind' => 'unknown', 'aggregation' => 'none',
        ]];
        yield 'non-string kind' => [[
            'category' => 'usage', 'key' => 'x', 'type' => 'integer', 'kind' => 1, 'aggregation' => 'none',
        ]];
        yield 'unknown aggregation' => [[
            'category' => 'usage', 'key' => 'x', 'type' => 'integer', 'kind' => 'period', 'aggregation' => 'unknown',
        ]];
        yield 'non-string aggregation' => [[
            'category' => 'usage', 'key' => 'x', 'type' => 'integer', 'kind' => 'period', 'aggregation' => 1,
        ]];
        yield 'numerical string' => [[
            'category' => 'usage', 'key' => 'x', 'type' => 'string', 'kind' => 'period', 'aggregation' => 'numerical',
        ]];
        yield 'description too long' => [[
            'category' => 'usage', 'key' => 'x', 'type' => 'integer', 'kind' => 'period', 'aggregation' => 'numerical', 'description' => str_repeat('a', 513),
        ]];
        yield 'description wrong type' => [[
            'category' => 'usage', 'key' => 'x', 'type' => 'integer', 'kind' => 'period', 'aggregation' => 'numerical', 'description' => 1,
        ]];
        yield 'required wrong type' => [[
            'category' => 'usage', 'key' => 'x', 'type' => 'integer', 'kind' => 'period', 'aggregation' => 'numerical', 'required' => 1,
        ]];
    }

    public function testAcceptsMetricBoundaryLengths(): void {
        $definition = $this->definition();
        $definition['application'] = str_repeat('a', 128);
        $definition['metrics'] = [[
            'category' => str_repeat('a', 128),
            'key' => str_repeat('b', 512),
            'type' => 'number',
            'kind' => 'period',
            'aggregation' => 'numerical',
            'description' => str_repeat('x', 512),
            'required' => false,
        ]];

        $validated = $this->validator->validateDefinition($definition);
        self::assertSame(str_repeat('a', 128), $validated['application']);
        self::assertSame(str_repeat('x', 512), $validated['metrics'][0]['description']);
    }

    public function testRejectsDuplicateMetricIdentity(): void {
        $definition = $this->definition();
        $definition['metrics'][] = $definition['metrics'][0];

        $this->expectException(InvalidReport::class);
        $this->validator->validateDefinition($definition);
    }

    public function testAllowsSameKeyInDifferentCategories(): void {
        $definition = $this->definition();
        $definition['metrics'][] = [
            'category' => 'other',
            'key' => 'version',
            'type' => 'string',
            'kind' => 'snapshot',
            'aggregation' => 'distribution',
        ];

        self::assertCount(3, $this->validator->validateDefinition($definition)['metrics']);
    }

    public function testValidReport(): void {
        $this->validator->validateReport($this->report(), $this->validator->validateDefinition($this->definition()));
        self::assertTrue(true);
    }

    #[DataProvider('reportIdentityMismatchProvider')]
    public function testRejectsReportIdentityMismatch(string $application, int $schemaVersion): void {
        $definition = $this->validator->validateDefinition($this->definition());
        $report = $this->report($application, $schemaVersion);

        $this->expectException(InvalidReport::class);
        $this->validator->validateReport($report, $definition);
    }

    /** @return iterable<string,array{string,int}> */
    public static function reportIdentityMismatchProvider(): iterable {
        yield 'application differs' => ['other', 1];
        yield 'schema version differs' => ['libresign', 2];
    }

    public function testRejectsUnknownMetric(): void {
        $definition = $this->validator->validateDefinition($this->definition());
        $report = $this->report(metrics: [new Metric('usage', 'unknown', 'integer', 1)]);

        $this->expectException(InvalidReport::class);
        $this->validator->validateReport($report, $definition);
    }

    public function testRejectsMetricTypeMismatch(): void {
        $definition = $this->validator->validateDefinition($this->definition());
        $report = $this->report(metrics: [
            new Metric('server', 'version', 'integer', 1),
            new Metric('usage', 'requests_completed', 'integer', 42),
        ]);

        $this->expectException(InvalidReport::class);
        $this->validator->validateReport($report, $definition);
    }

    public function testRejectsMissingRequiredMetric(): void {
        $definition = $this->validator->validateDefinition($this->definition());
        $report = $this->report(metrics: [new Metric('server', 'version', 'string', '12.0.0')]);

        $this->expectException(InvalidReport::class);
        $this->validator->validateReport($report, $definition);
    }

    public function testAllowsMissingOptionalMetric(): void {
        $definition = $this->definition();
        $definition['metrics'][1]['required'] = false;
        $definition = $this->validator->validateDefinition($definition);
        $report = $this->report(metrics: [new Metric('server', 'version', 'string', '12.0.0')]);

        $this->validator->validateReport($report, $definition);
        self::assertTrue(true);
    }

    #[DataProvider('invalidRegisteredMetricProvider')]
    public function testRejectsInvalidRegisteredMetric(array $metric): void {
        $definition = $this->validator->validateDefinition($this->definition());
        $definition['metrics'][0] = $metric;

        $this->expectException(InvalidReport::class);
        $this->validator->validateReport($this->report(), $definition);
    }

    /** @return iterable<string,array{array<string,mixed>}> */
    public static function invalidRegisteredMetricProvider(): iterable {
        yield 'missing category' => [[
            'key' => 'version', 'type' => 'string', 'required' => true,
        ]];
        yield 'missing key' => [[
            'category' => 'server', 'type' => 'string', 'required' => true,
        ]];
        yield 'missing type' => [[
            'category' => 'server', 'key' => 'version', 'required' => true,
        ]];
        yield 'missing required' => [[
            'category' => 'server', 'key' => 'version', 'type' => 'string',
        ]];
        yield 'required not boolean' => [[
            'category' => 'server', 'key' => 'version', 'type' => 'string', 'required' => 1,
        ]];
    }

    #[DataProvider('unknownSchemaFieldProvider')]
    public function testRejectsUnknownSchemaFields(string $scope): void {
        $definition = $this->definition();

        if ($scope === 'schema') {
            $definition['unexpected'] = true;
        } else {
            $definition['metrics'][0]['unexpected'] = true;
        }

        $this->expectException(InvalidReport::class);
        $this->validator->validateDefinition($definition);
    }

    /** @return iterable<string,array{string}> */
    public static function unknownSchemaFieldProvider(): iterable {
        yield 'schema' => ['schema'];
        yield 'metric' => ['metric'];
    }

    /** @param list<Metric>|null $metrics */
    private function report(string $application = 'libresign', int $schemaVersion = 1, ?array $metrics = null): Report {
        return new Report(
            1,
            $application,
            'installation-1',
            $schemaVersion,
            new \DateTimeImmutable('2026-08-01T00:00:00Z'),
            new \DateTimeImmutable('2026-09-01T00:00:00Z'),
            $metrics ?? [
                new Metric('server', 'version', 'string', '12.0.0'),
                new Metric('usage', 'requests_completed', 'integer', 42),
            ],
        );
    }

    /** @return array<string,mixed> */
    private function definition(): array {
        return [
            'application' => 'libresign',
            'schemaVersion' => 1,
            'metrics' => [
                [
                    'category' => 'server',
                    'key' => 'version',
                    'type' => 'string',
                    'kind' => 'snapshot',
                    'aggregation' => 'distribution',
                    'description' => 'LibreSign version',
                    'required' => true,
                ],
                [
                    'category' => 'usage',
                    'key' => 'requests_completed',
                    'type' => 'integer',
                    'kind' => 'period',
                    'aggregation' => 'numerical',
                    'description' => 'Completed signing requests',
                    'required' => true,
                ],
            ],
        ];
    }
}
