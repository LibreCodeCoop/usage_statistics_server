<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\UsageStatisticsServer\Tests\Unit\Service;

use OCA\UsageStatisticsServer\Service\MetricIdentity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MetricIdentityTest extends TestCase {
    #[DataProvider('distinctIdentityProvider')]
    public function testDistinctCategoryAndKeyPairsCannotCollide(
        string $leftCategory,
        string $leftKey,
        string $rightCategory,
        string $rightKey,
    ): void {
        self::assertNotSame(
            MetricIdentity::fromParts($leftCategory, $leftKey),
            MetricIdentity::fromParts($rightCategory, $rightKey),
        );
    }

    /** @return iterable<string,array{string,string,string,string}> */
    public static function distinctIdentityProvider(): iterable {
        yield 'colon moves from category to key' => ['a:b', 'c', 'a', 'b:c'];
        yield 'separator must remain between parts' => ['a', 'bc', 'ab', 'c'];
        yield 'same category different key' => ['usage', 'a', 'usage', 'b'];
        yield 'different category same key' => ['usage', 'count', 'server', 'count'];
    }

    public function testSamePairHasStableIdentity(): void {
        self::assertSame(
            MetricIdentity::fromParts('usage:period', 'request:count'),
            MetricIdentity::fromParts('usage:period', 'request:count'),
        );
    }
}
