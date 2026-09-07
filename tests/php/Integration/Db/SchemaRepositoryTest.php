<?php

declare(strict_types=1);

namespace OCA\UsageStatisticsServer\Tests\Integration\Db;

use OCA\UsageStatisticsServer\Db\SchemaRepository;
use OCP\IDBConnection;
use OCP\Server;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Test\TestCase;

#[Group('DB')]
final class SchemaRepositoryTest extends TestCase {
    private IDBConnection $db;
    private SchemaRepository $schemas;

    protected function setUp(): void {
        parent::setUp();
        $this->db = Server::get(IDBConnection::class);
        $this->schemas = new SchemaRepository($this->db);
        $this->db->getQueryBuilder()->delete('usage_stats_schemas')->executeStatement();
    }

    protected function tearDown(): void {
        $this->db->getQueryBuilder()->delete('usage_stats_schemas')->executeStatement();
        parent::tearDown();
    }

    public function testRegistrationIsIdempotentAndImmutable(): void {
        $definition = $this->definition(1);

        self::assertTrue($this->schemas->store('libresign', 1, $definition));
        self::assertFalse($this->schemas->store('libresign', 1, $definition));
        self::assertSame($definition, $this->schemas->find('libresign', 1));

        $changed = $definition;
        $changed['metrics'][0]['description'] = 'Changed within the same version';

        $this->expectException(\LogicException::class);
        $this->schemas->store('libresign', 1, $changed);
    }

    public function testCompatibleMetricCanEvolvePresentationAcrossVersions(): void {
        $first = $this->definition(1);
        $second = $this->definition(2);
        $second['metrics'][0]['description'] = 'New description';
        $second['metrics'][0]['required'] = false;

        self::assertTrue($this->schemas->store('libresign', 1, $first));
        self::assertTrue($this->schemas->store('libresign', 2, $second));
        self::assertSame($first['metrics'][0], $this->schemas->findMetric('libresign', 'usage', 'requests_completed'));
        self::assertSame($second, $this->schemas->find('libresign', 2));
    }

    public function testCompatibilityChecksAllPreviousSchemaVersions(): void {
        $first = $this->definition(1);
        $second = $this->definition(2);
        $second['metrics'] = [[
            'category' => 'server',
            'key' => 'version',
            'type' => 'string',
            'kind' => 'snapshot',
            'aggregation' => 'distribution',
            'description' => 'Version',
            'required' => false,
        ]];
        $third = $this->definition(3);
        $third['metrics'][0]['type'] = 'number';

        self::assertTrue($this->schemas->store('libresign', 1, $first));
        self::assertTrue($this->schemas->store('libresign', 2, $second));

        $this->expectException(\LogicException::class);
        $this->schemas->store('libresign', 3, $third);
    }

    public function testCompatibilityContinuesAfterNewMetric(): void {
        $first = $this->definition(1);
        $second = $this->definition(2);
        $second['metrics'] = [
            [
                'category' => 'new',
                'key' => 'metric',
                'type' => 'boolean',
                'kind' => 'snapshot',
                'aggregation' => 'distribution',
                'description' => 'New metric',
                'required' => false,
            ],
            [
                ...$first['metrics'][0],
                'type' => 'number',
            ],
        ];

        $this->schemas->store('libresign', 1, $first);

        $this->expectException(\LogicException::class);
        $this->schemas->store('libresign', 2, $second);
    }

    #[DataProvider('incompatibleMetricProvider')]
    public function testRejectsIncompatibleMetricSemantics(string $field, string $value): void {
        $first = $this->definition(1);
        $second = $this->definition(2);
        $second['metrics'][0][$field] = $value;

        self::assertTrue($this->schemas->store('libresign', 1, $first));

        $this->expectException(\LogicException::class);
        $this->schemas->store('libresign', 2, $second);
    }

    /** @return iterable<string,array{string,string}> */
    public static function incompatibleMetricProvider(): iterable {
        yield 'type' => ['type', 'number'];
        yield 'kind' => ['kind', 'snapshot'];
        yield 'aggregation' => ['aggregation', 'none'];
    }

    public function testMetricLookupRequiresCategoryAndKeyToMatch(): void {
        $definition = $this->definition(1);
        $definition['metrics'] = [
            [
                ...$definition['metrics'][0],
                'key' => 'other_key',
            ],
            [
                ...$definition['metrics'][0],
                'category' => 'other_category',
            ],
            $definition['metrics'][0],
        ];
        $this->schemas->store('libresign', 1, $definition);

        self::assertSame(
            $definition['metrics'][2],
            $this->schemas->findMetric('libresign', 'usage', 'requests_completed'),
        );
    }

    public function testMetricLookupSearchesAllStoredDefinitions(): void {
        $first = $this->definition(1);
        $second = $this->definition(2);
        $second['metrics'] = [[
            'category' => 'server',
            'key' => 'version',
            'type' => 'string',
            'kind' => 'snapshot',
            'aggregation' => 'distribution',
            'description' => 'Version',
            'required' => false,
        ]];

        $this->schemas->store('libresign', 1, $first);
        $this->schemas->store('libresign', 2, $second);

        self::assertSame($first['metrics'][0], $this->schemas->findMetric('libresign', 'usage', 'requests_completed'));
        self::assertSame($second['metrics'][0], $this->schemas->findMetric('libresign', 'server', 'version'));
        self::assertNull($this->schemas->findMetric('libresign', 'missing', 'metric'));
    }

    /** @return array<string,mixed> */
    private function definition(int $version): array {
        return [
            'application' => 'libresign',
            'schemaVersion' => $version,
            'metrics' => [[
                'category' => 'usage',
                'key' => 'requests_completed',
                'type' => 'integer',
                'kind' => 'period',
                'aggregation' => 'numerical',
                'description' => 'Completed signing requests',
                'required' => true,
            ]],
        ];
    }
}
