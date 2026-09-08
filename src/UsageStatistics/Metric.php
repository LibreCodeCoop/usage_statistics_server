<?php

declare(strict_types=1);

namespace App\UsageStatistics;

final readonly class Metric
{
    public function __construct(
        public string $category,
        public string $key,
        public string $type,
        public string|int|float|bool $value,
    ) {
    }
}
