<?php

declare(strict_types=1);

namespace OCA\UsageStatisticsServer\Service;

final class ReportFactory {
    private const IDENTIFIER_PATTERN = '/^[A-Za-z0-9_.:-]+$/';
    private const MAX_APPLICATION_LENGTH = 128;
    private const MAX_INSTALLATION_ID_LENGTH = 128;
    private const MAX_CATEGORY_LENGTH = 128;
    private const MAX_KEY_LENGTH = 512;
    private const MAX_STRING_VALUE_LENGTH = 1024;
    private const MAX_METRICS = 256;

    public function fromPayload(array $payload): Report {
        if (($payload['protocolVersion'] ?? null) !== 1) {
            throw new InvalidReport('Unsupported protocol version.');
        }

        $application = $this->identifier($payload['application'] ?? null, 'application', self::MAX_APPLICATION_LENGTH);
        $installationId = $this->identifier($payload['installationId'] ?? null, 'installationId', self::MAX_INSTALLATION_ID_LENGTH);
        $schemaVersion = $payload['schemaVersion'] ?? null;
        if (!is_int($schemaVersion) || $schemaVersion < 1) {
            throw new InvalidReport('schemaVersion must be a positive integer.');
        }

        $period = $payload['period'] ?? null;
        if (!is_array($period)) {
            throw new InvalidReport('period is required.');
        }

        try {
            $start = (new \DateTimeImmutable((string)($period['start'] ?? '')))->setTimezone(new \DateTimeZone('UTC'));
            $end = (new \DateTimeImmutable((string)($period['end'] ?? '')))->setTimezone(new \DateTimeZone('UTC'));
        } catch (\Exception) {
            throw new InvalidReport('Invalid report period.');
        }

        if ($end <= $start || ($end->getTimestamp() - $start->getTimestamp()) > 2678400) {
            throw new InvalidReport('Invalid report period.');
        }

        $rawMetrics = $payload['metrics'] ?? null;
        if (!is_array($rawMetrics) || $rawMetrics === [] || count($rawMetrics) > self::MAX_METRICS) {
            throw new InvalidReport('metrics must be a non-empty bounded list.');
        }

        $metrics = [];
        $seen = [];
        foreach ($rawMetrics as $rawMetric) {
            if (!is_array($rawMetric)) {
                throw new InvalidReport('Invalid metric.');
            }
            $category = $this->identifier($rawMetric['category'] ?? null, 'metric category', self::MAX_CATEGORY_LENGTH);
            $key = $this->identifier($rawMetric['key'] ?? null, 'metric key', self::MAX_KEY_LENGTH);
            $type = $rawMetric['type'] ?? null;
            $value = $rawMetric['value'] ?? null;
            $identity = $category . ':' . $key;
            if (isset($seen[$identity])) {
                throw new InvalidReport('Duplicate metric.');
            }
            $seen[$identity] = true;

            $valid = match ($type) {
                'integer' => is_int($value),
                'number' => is_int($value) || (is_float($value) && is_finite($value)),
                'boolean' => is_bool($value),
                'string' => is_string($value) && strlen($value) <= self::MAX_STRING_VALUE_LENGTH,
                default => false,
            };
            if (!$valid) {
                throw new InvalidReport('Metric type and value do not match.');
            }
            $metrics[] = new Metric($category, $key, $type, $value);
        }

        return new Report(1, $application, $installationId, $schemaVersion, $start, $end, $metrics);
    }

    private function identifier(mixed $value, string $field, int $maxLength): string {
        if (!is_string($value) || $value === '' || strlen($value) > $maxLength || preg_match(self::IDENTIFIER_PATTERN, $value) !== 1) {
            throw new InvalidReport("Invalid {$field}.");
        }
        return $value;
    }
}
