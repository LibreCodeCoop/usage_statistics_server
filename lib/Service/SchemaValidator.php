<?php

declare(strict_types=1);

namespace OCA\UsageStatisticsServer\Service;

final class SchemaValidator {
    private const IDENTIFIER_PATTERN = '/^[A-Za-z0-9_.:-]+$/';
    private const TYPES = ['integer', 'number', 'boolean', 'string'];
    private const KINDS = ['snapshot', 'period', 'counter', 'categorical'];
    private const AGGREGATIONS = ['distribution', 'numerical', 'none'];

    /** @return array<string,mixed> */
    public function validateDefinition(array $definition): array {
        if (array_is_list($definition)) {
            throw new InvalidReport('Schema definition must be an object.');
        }
        $this->assertAllowedKeys($definition, ['application', 'schemaVersion', 'metrics'], 'schema');

        $application = $this->identifier($definition['application'] ?? null, 'application', 128);
        $schemaVersion = $definition['schemaVersion'] ?? null;
        if (!is_int($schemaVersion) || $schemaVersion < 1) {
            throw new InvalidReport('schemaVersion must be a positive integer.');
        }

        $rawMetrics = $definition['metrics'] ?? null;
        if (!is_array($rawMetrics) || !array_is_list($rawMetrics) || $rawMetrics === [] || count($rawMetrics) > 256) {
            throw new InvalidReport('Schema metrics must be a non-empty bounded list.');
        }

        $metrics = [];
        $seen = [];
        foreach ($rawMetrics as $rawMetric) {
            if (!is_array($rawMetric) || array_is_list($rawMetric)) {
                throw new InvalidReport('Invalid schema metric.');
            }
            $this->assertAllowedKeys($rawMetric, ['category', 'key', 'type', 'kind', 'aggregation', 'description', 'required'], 'schema metric');

            $category = $this->identifier($rawMetric['category'] ?? null, 'metric category', 128);
            $key = $this->identifier($rawMetric['key'] ?? null, 'metric key', 512);
            $identity = $category . ':' . $key;
            if (isset($seen[$identity])) {
                throw new InvalidReport('Duplicate schema metric.');
            }
            $seen[$identity] = true;

            $type = $rawMetric['type'] ?? null;
            $kind = $rawMetric['kind'] ?? null;
            $aggregation = $rawMetric['aggregation'] ?? null;
            $description = $rawMetric['description'] ?? '';
            $required = $rawMetric['required'] ?? false;

            if (!is_string($type) || !in_array($type, self::TYPES, true)) {
                throw new InvalidReport('Invalid schema metric type.');
            }
            if (!is_string($kind) || !in_array($kind, self::KINDS, true)) {
                throw new InvalidReport('Invalid schema metric kind.');
            }
            if (!is_string($aggregation) || !in_array($aggregation, self::AGGREGATIONS, true)) {
                throw new InvalidReport('Invalid schema metric aggregation.');
            }
            if ($aggregation === 'numerical' && !in_array($type, ['integer', 'number'], true)) {
                throw new InvalidReport('Numerical aggregation requires an integer or number metric.');
            }
            if (!is_string($description) || strlen($description) > 512) {
                throw new InvalidReport('Invalid schema metric description.');
            }
            if (!is_bool($required)) {
                throw new InvalidReport('Schema metric required must be boolean.');
            }

            $metrics[] = [
                'category' => $category,
                'key' => $key,
                'type' => $type,
                'kind' => $kind,
                'aggregation' => $aggregation,
                'description' => $description,
                'required' => $required,
            ];
        }

        return [
            'application' => $application,
            'schemaVersion' => $schemaVersion,
            'metrics' => $metrics,
        ];
    }

    public function validateReport(Report $report, array $definition): void {
        if (($definition['application'] ?? null) !== $report->application
            || ($definition['schemaVersion'] ?? null) !== $report->schemaVersion) {
            throw new InvalidReport('Report does not match the registered application schema.');
        }

        $allowed = [];
        $required = [];
        foreach ($definition['metrics'] ?? [] as $metric) {
            if (!is_array($metric)) {
                throw new InvalidReport('Invalid registered application schema.');
            }
            $identity = ($metric['category'] ?? '') . ':' . ($metric['key'] ?? '');
            $allowed[$identity] = $metric;
            if (($metric['required'] ?? false) === true) {
                $required[$identity] = true;
            }
        }

        foreach ($report->metrics as $metric) {
            $identity = $metric->category . ':' . $metric->key;
            $schemaMetric = $allowed[$identity] ?? null;
            if (!is_array($schemaMetric)) {
                throw new InvalidReport('Report contains a metric that is not registered in the application schema.');
            }
            if (($schemaMetric['type'] ?? null) !== $metric->type) {
                throw new InvalidReport('Report metric type does not match the application schema.');
            }
            unset($required[$identity]);
        }

        if ($required !== []) {
            throw new InvalidReport('Report is missing a required metric.');
        }
    }

    /** @param array<string,mixed> $value
     *  @param list<string> $allowed
     */
    private function assertAllowedKeys(array $value, array $allowed, string $scope): void {
        if (array_diff(array_keys($value), $allowed) !== []) {
            throw new InvalidReport("Unknown {$scope} field.");
        }
    }

    private function identifier(mixed $value, string $field, int $maxLength): string {
        if (!is_string($value) || $value === '' || strlen($value) > $maxLength || preg_match(self::IDENTIFIER_PATTERN, $value) !== 1) {
            throw new InvalidReport("Invalid {$field}.");
        }
        return $value;
    }
}
