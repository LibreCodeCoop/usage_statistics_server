<?php

declare(strict_types=1);

namespace OCA\UsageStatisticsServer\Tests\Unit\Service;

use OCA\UsageStatisticsServer\Service\InvalidReport;
use OCA\UsageStatisticsServer\Service\Metric;
use OCA\UsageStatisticsServer\Service\Report;
use OCA\UsageStatisticsServer\Service\SchemaValidator;
use PHPUnit\Framework\TestCase;

final class SchemaValidatorTest extends TestCase {
    private SchemaValidator $validator;

    protected function setUp(): void {
        $this->validator = new SchemaValidator();
    }

    public function testValidDefinitionAndReport(): void {
        $definition = $this->validator->validateDefinition($this->definition());

        $report = new Report(
            1,
            'libresign',
            'installation-1',
            1,
            new \DateTimeImmutable('2026-08-01T00:00:00Z'),
            new \DateTimeImmutable('2026-09-01T00:00:00Z'),
            [
                new Metric('server', 'version', 'string', '12.0.0'),
                new Metric('usage', 'requests_completed', 'integer', 42),
            ],
        );

        $this->validator->validateReport($report, $definition);
        self::assertTrue(true);
    }

    public function testRejectsUnknownMetric(): void {
        $definition = $this->validator->validateDefinition($this->definition());
        $report = new Report(
            1,
            'libresign',
            'installation-1',
            1,
            new \DateTimeImmutable('2026-08-01T00:00:00Z'),
            new \DateTimeImmutable('2026-09-01T00:00:00Z'),
            [new Metric('usage', 'unknown', 'integer', 1)],
        );

        $this->expectException(InvalidReport::class);
        $this->validator->validateReport($report, $definition);
    }

    public function testRejectsMissingRequiredMetric(): void {
        $definition = $this->validator->validateDefinition($this->definition());
        $report = new Report(
            1,
            'libresign',
            'installation-1',
            1,
            new \DateTimeImmutable('2026-08-01T00:00:00Z'),
            new \DateTimeImmutable('2026-09-01T00:00:00Z'),
            [new Metric('server', 'version', 'string', '12.0.0')],
        );

        $this->expectException(InvalidReport::class);
        $this->validator->validateReport($report, $definition);
    }

    public function testRejectsNumericalAggregationForStringMetric(): void {
        $definition = $this->definition();
        $definition['metrics'][0]['aggregation'] = 'numerical';

        $this->expectException(InvalidReport::class);
        $this->validator->validateDefinition($definition);
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
