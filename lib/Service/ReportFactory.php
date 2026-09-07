<?php

declare(strict_types=1);

namespace OCA\UsageStatisticsServer\Service;

final class ReportFactory {
    private const IDENTIFIER_PATTERN = '/^[A-Za-z0-9_.:-]+$/';
    private const RFC3339_PATTERN = '/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})(?:\.(\d{1,6}))?(Z|[+-]\d{2}:\d{2})$/D';
    private const MAX_APPLICATION_LENGTH = 128;
    private const MAX_INSTALLATION_ID_LENGTH = 128;
    private const MAX_CATEGORY_LENGTH = 128;
    private const MAX_KEY_LENGTH = 512;
    private const MAX_STRING_VALUE_LENGTH = 1024;
    private const MAX_METRICS = 256;

    public function fromPayload(array $payload): Report {
        if (array_is_list($payload)) {
            throw new InvalidReport('Request body must be a JSON object.');
        }
        $this->assertAllowedKeys($payload, ['protocolVersion', 'application', 'installationId', 'schemaVersion', 'period', 'metrics'], 'report');

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
        if (!is_array($period) || array_is_list($period)) {
            throw new InvalidReport('period is required.');
        }
        $this->assertAllowedKeys($period, ['start', 'end'], 'period');

        $start = $this->parseRfc3339($period['start'] ?? null);
        $end = $this->parseRfc3339($period['end'] ?? null);

        if ($end <= $start || ($end->getTimestamp() - $start->getTimestamp()) > 2678400) {
            throw new InvalidReport('Invalid report period.');
        }

        $rawMetrics = $payload['metrics'] ?? null;
        if (!is_array($rawMetrics) || !array_is_list($rawMetrics) || $rawMetrics === [] || count($rawMetrics) > self::MAX_METRICS) {
            throw new InvalidReport('metrics must be a non-empty bounded list.');
        }

        $metrics = [];
        $seen = [];
        foreach ($rawMetrics as $rawMetric) {
            if (!is_array($rawMetric) || array_is_list($rawMetric)) {
                throw new InvalidReport('Invalid metric.');
            }
            $this->assertAllowedKeys($rawMetric, ['category', 'key', 'type', 'value'], 'metric');

            $category = $this->identifier($rawMetric['category'] ?? null, 'metric category', self::MAX_CATEGORY_LENGTH);
            $key = $this->identifier($rawMetric['key'] ?? null, 'metric key', self::MAX_KEY_LENGTH);
            $type = $rawMetric['type'] ?? null;
            $value = $rawMetric['value'] ?? null;
            $identity = MetricIdentity::fromParts($category, $key);
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

    /** @param array<string,mixed> $value
     *  @param list<string> $allowed
     */
    private function assertAllowedKeys(array $value, array $allowed, string $scope): void {
        $unknown = array_diff(array_keys($value), $allowed);
        if ($unknown !== []) {
            throw new InvalidReport("Unknown {$scope} field.");
        }
    }

    private function parseRfc3339(mixed $value): \DateTimeImmutable {
        if (!is_string($value) || preg_match(self::RFC3339_PATTERN, $value, $matches) !== 1) {
            throw new InvalidReport('Invalid report period.');
        }

        $year = (int)$matches[1];
        $month = (int)$matches[2];
        $day = (int)$matches[3];
        $hour = (int)$matches[4];
        $minute = (int)$matches[5];
        $second = (int)$matches[6];
        $timezone = $matches[8];

        if (!checkdate($month, $day, $year) || $hour > 23 || $minute > 59 || $second > 59) {
            throw new InvalidReport('Invalid report period.');
        }

        if ($timezone !== 'Z') {
            [$timezoneHour, $timezoneMinute] = array_map('intval', explode(':', substr($timezone, 1)));
            if ($timezoneHour > 23 || $timezoneMinute > 59) {
                throw new InvalidReport('Invalid report period.');
            }
        }

        try {
            return (new \DateTimeImmutable($value))->setTimezone(new \DateTimeZone('UTC'));
        } catch (\Exception) {
            throw new InvalidReport('Invalid report period.');
        }
    }

    private function identifier(mixed $value, string $field, int $maxLength): string {
        if (!is_string($value) || $value === '' || strlen($value) > $maxLength || preg_match(self::IDENTIFIER_PATTERN, $value) !== 1) {
            throw new InvalidReport("Invalid {$field}.");
        }
        return $value;
    }
}
