<?php

declare(strict_types=1);

namespace OCA\UsageStatisticsServer\Tests\Integration\Db;

use OCA\UsageStatisticsServer\Db\SchemaRepository;
use OCP\IDBConnection;
use OCP\Server;
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
        $definition = [
            'application' => 'libresign',
            'schemaVersion' => 1,
            'metrics' => [],
        ];

        self::assertTrue($this->schemas->store('libresign', 1, $definition));
        self::assertFalse($this->schemas->store('libresign', 1, $definition));
        self::assertSame($definition, $this->schemas->find('libresign', 1));

        $changed = $definition;
        $changed['metrics'][] = ['category' => 'server'];

        $this->expectException(\LogicException::class);
        $this->schemas->store('libresign', 1, $changed);
    }
}
