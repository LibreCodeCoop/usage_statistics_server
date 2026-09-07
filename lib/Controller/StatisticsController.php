<?php

declare(strict_types=1);

namespace OCA\UsageStatisticsServer\Controller;

use OCA\UsageStatisticsServer\Db\StatisticsRepository;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

final class StatisticsController extends OCSController {
    private const ACTIVE_WINDOW_DAYS = 45;

    public function __construct(
        string $appName,
        IRequest $request,
        private readonly StatisticsRepository $statistics,
    ) {
        parent::__construct($appName, $request);
    }

    public function summary(string $application): DataResponse {
        $since = new \DateTimeImmutable(
            sprintf('-%d days', self::ACTIVE_WINDOW_DAYS),
            new \DateTimeZone('UTC'),
        );

        return new DataResponse([
            'application' => $application,
            'activeWindowDays' => self::ACTIVE_WINDOW_DAYS,
            'activeInstallations' => $this->statistics->countActiveInstallations($application, $since),
        ]);
    }

    public function distribution(string $application, string $category, string $key): DataResponse {
        return new DataResponse([
            'application' => $application,
            'category' => $category,
            'key' => $key,
            'values' => $this->statistics->currentDistribution($application, $category, $key),
        ]);
    }

    public function numerical(string $application, string $category, string $key): DataResponse {
        return new DataResponse([
            'application' => $application,
            'category' => $category,
            'key' => $key,
            'statistics' => $this->statistics->currentNumericalEvaluation($application, $category, $key),
        ]);
    }

    public function numericalHistory(
        string $application,
        string $category,
        string $key,
        string $from = '',
        string $to = '',
    ): DataResponse {
        try {
            $range = $this->parseRange($from, $to);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse([
                'error' => 'invalid_range',
                'message' => $e->getMessage(),
            ], Http::STATUS_BAD_REQUEST);
        }

        return new DataResponse([
            'application' => $application,
            'category' => $category,
            'key' => $key,
            'from' => $range['from']->format(DATE_ATOM),
            'to' => $range['to']->format(DATE_ATOM),
            'periods' => $this->statistics->numericalHistory(
                $application,
                $category,
                $key,
                $range['from'],
                $range['to'],
            ),
        ]);
    }

    /** @return array{from:\DateTimeImmutable,to:\DateTimeImmutable} */
    private function parseRange(string $from, string $to): array {
        $utc = new \DateTimeZone('UTC');
        $rangeTo = $to === '' ? new \DateTimeImmutable('now', $utc) : $this->parseDate($to);
        $rangeFrom = $from === '' ? $rangeTo->modify('-1 year') : $this->parseDate($from);

        if ($rangeFrom > $rangeTo) {
            throw new \InvalidArgumentException('The from value must be before or equal to the to value.');
        }

        return ['from' => $rangeFrom, 'to' => $rangeTo];
    }

    private function parseDate(string $value): \DateTimeImmutable {
        try {
            return (new \DateTimeImmutable($value))->setTimezone(new \DateTimeZone('UTC'));
        } catch (\Exception) {
            throw new \InvalidArgumentException('Dates must use a valid ISO 8601 value.');
        }
    }
}
