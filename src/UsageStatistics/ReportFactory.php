<?php

declare(strict_types=1);

namespace App\UsageStatistics;

final class ReportFactory
{
    private const IDENTIFIER_PATTERN = '/^[A-Za-z0-9_.:-]+$/';
    private const TYPES = ['integer', 'number', 'boolean', 'string'];

    /** @param array<string, mixed> $payload */
    public function fromPayload(array $payload): Report
    {
        foreach (['protocolVersion', 'application', 'installationId', 'schemaVersion', 'period', 'metrics'] as $field) {
            if (!array_key_exists($field, $payload)) {
                throw new InvalidReport(sprintf('Missing required field "%s".', $field));
            }
        }

        if ($payload['protocolVersion'] !== 1) {
            throw new InvalidReport('Unsupported protocolVersion.');
        }

        if (!is_int($payload['schemaVersion']) || $payload['schemaVersion'] < 1) {
            throw new InvalidReport('schemaVersion must be a positive integer.');
        }

        $application = $this->identifier($payload['application'], 'application', 128);
        $installationId = $this->identifier($payload['installationId'], 'installationId', 128);

        if (!is_array($payload['period'])) {
            throw new InvalidReport('period must be an object.');
        }

        $start = $this->date($payload['period']['start'] ?? null, 'period.start');
        $end = $this->date($payload['period']['end'] ?? null, 'period.end');
        if ($end <= $start) {
            throw new InvalidReport('period.end must be after period.start.');
        }
        if (($end->getTimestamp() - $start->getTimestamp()) > 93 * 86400) {
            throw new InvalidReport('Reporting periods longer than 93 days are not accepted.');
        }

        if (!is_array($payload['metrics']) || array_is_list($payload['metrics']) === false) {
            throw new InvalidReport('metrics must be a list.');
        }
        if ($payload['metrics'] === [] || count($payload['metrics']) > 256) {
            throw new InvalidReport('metrics must contain between 1 and 256 items.');
        }

        $metrics = [];
        $seen = [];
        foreach ($payload['metrics'] as $rawMetric) {
            if (!is_array($rawMetric)) {
                throw new InvalidReport('Each metric must be an object.');
            }
            $metric = $this->metric($rawMetric);
            $identity = $metric->category . "\0" . $metric->key;
            if (isset($seen[$identity])) {
                throw new InvalidReport('Duplicate metric category/key in report.');
            }
            $seen[$identity] = true;
            $metrics[] = $metric;
        }

        return new Report(
            1,
            $application,
            $installationId,
            $payload['schemaVersion'],
            $start,
            $end,
            $metrics,
        );
    }

    /** @param array<string, mixed> $payload */
    private function metric(array $payload): Metric
    {
        foreach (['category', 'key', 'type', 'value'] as $field) {
            if (!array_key_exists($field, $payload)) {
                throw new InvalidReport(sprintf('Metric is missing required field "%s".', $field));
            }
        }

        $category = $this->identifier($payload['category'], 'metric.category', 128);
        $key = $this->identifier($payload['key'], 'metric.key', 256);
        $type = $payload['type'];
        if (!is_string($type) || !in_array($type, self::TYPES, true)) {
            throw new InvalidReport('Unsupported metric type.');
        }

        $value = $payload['value'];
        $valid = match ($type) {
            'integer' => is_int($value),
            'number' => is_int($value) || is_float($value),
            'boolean' => is_bool($value),
            'string' => is_string($value) && strlen($value) <= 1024,
            default => false,
        };
        if (!$valid) {
            throw new InvalidReport(sprintf('Metric value is incompatible with type "%s".', $type));
        }

        return new Metric($category, $key, $type, $value);
    }

    private function identifier(mixed $value, string $field, int $maxLength): string
    {
        if (!is_string($value) || $value === '' || strlen($value) > $maxLength || preg_match(self::IDENTIFIER_PATTERN, $value) !== 1) {
            throw new InvalidReport(sprintf('%s has an invalid format.', $field));
        }

        return $value;
    }

    private function date(mixed $value, string $field): \DateTimeImmutable
    {
        if (!is_string($value)) {
            throw new InvalidReport(sprintf('%s must be an RFC 3339 timestamp.', $field));
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            throw new InvalidReport(sprintf('%s must be an RFC 3339 timestamp.', $field));
        }
    }
}
