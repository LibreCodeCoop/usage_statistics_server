<?php

declare(strict_types=1);

namespace OCA\UsageStatisticsServer\Controller;

use OCA\UsageStatisticsServer\Db\StatisticsRepository;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

#[OpenAPI(scope: OpenAPI::SCOPE_ADMINISTRATION, tags: ['statistics'])]
final class StatisticsController extends OCSController {
    private const ACTIVE_WINDOW_DAYS = 45;
    private const RFC3339_PATTERN = '/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})(?:\.(\d{1,6}))?(Z|[+-]\d{2}:\d{2})$/D';

    public function __construct(
        string $appName,
        IRequest $request,
        private readonly StatisticsRepository $statistics,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * Get application usage statistics summary
     *
     * @param string $application Stable application identifier
     *
     * @return DataResponse<Http::STATUS_OK, array{application:string,activeWindowDays:int,activeInstallations:int}, array{}>
     *
     * 200: Application usage statistics summary
     */
    #[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/admin/applications/{application}', requirements: ['apiVersion' => '(v1)'])]
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

    /**
     * Get the current distribution for a metric
     *
     * @param string $application Stable application identifier
     * @param string $category Metric category
     * @param string $key Metric key
     *
     * @return DataResponse<Http::STATUS_OK, array{application:string,category:string,key:string,values:list<array{value:mixed,count:int}>}, array{}>
     *
     * 200: Current metric distribution
     */
    #[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/admin/applications/{application}/metrics/{category}/{key}/distribution', requirements: ['apiVersion' => '(v1)'])]
    public function distribution(string $application, string $category, string $key): DataResponse {
        return new DataResponse([
            'application' => $application,
            'category' => $category,
            'key' => $key,
            'values' => $this->statistics->currentDistribution($application, $category, $key),
        ]);
    }

    /**
     * Get the current numerical evaluation for a metric
     *
     * @param string $application Stable application identifier
     * @param string $category Metric category
     * @param string $key Metric key
     *
     * @return DataResponse<Http::STATUS_OK, array{application:string,category:string,key:string,statistics:array{count:int,average:float|null,min:float|null,max:float|null,total:float|null}}, array{}>
     *
     * 200: Current numerical metric evaluation
     */
    #[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/admin/applications/{application}/metrics/{category}/{key}/numerical', requirements: ['apiVersion' => '(v1)'])]
    public function numerical(string $application, string $category, string $key): DataResponse {
        return new DataResponse([
            'application' => $application,
            'category' => $category,
            'key' => $key,
            'statistics' => $this->statistics->currentNumericalEvaluation($application, $category, $key),
        ]);
    }

    /**
     * Get historical numerical evaluation for a metric
     *
     * @param string $application Stable application identifier
     * @param string $category Metric category
     * @param string $key Metric key
     * @param string $from Optional RFC3339 lower bound
     * @param string $to Optional RFC3339 upper bound
     *
     * @return DataResponse<Http::STATUS_OK, array{application:string,category:string,key:string,from:string,to:string,periods:list<array{periodStart:string,periodEnd:string,count:int,average:float|null,min:float|null,max:float|null,total:float|null}>}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error:string,message:string}, array{}>
     *
     * 200: Historical numerical metric evaluation
     * 400: Invalid date range
     */
    #[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/admin/applications/{application}/metrics/{category}/{key}/numerical/history', requirements: ['apiVersion' => '(v1)'])]
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
        if (preg_match(self::RFC3339_PATTERN, $value, $matches) !== 1) {
            throw new \InvalidArgumentException('Dates must use RFC3339.');
        }

        $year = (int)$matches[1];
        $month = (int)$matches[2];
        $day = (int)$matches[3];
        $hour = (int)$matches[4];
        $minute = (int)$matches[5];
        $second = (int)$matches[6];
        if (!checkdate($month, $day, $year) || $hour > 23 || $minute > 59 || $second > 59) {
            throw new \InvalidArgumentException('Dates must use RFC3339.');
        }

        $timezone = $matches[8];
        if ($timezone !== 'Z') {
            [$timezoneHour, $timezoneMinute] = array_map('intval', explode(':', substr($timezone, 1)));
            if ($timezoneHour > 23 || $timezoneMinute > 59) {
                throw new \InvalidArgumentException('Dates must use RFC3339.');
            }
        }

        try {
            return (new \DateTimeImmutable($value))->setTimezone(new \DateTimeZone('UTC'));
        } catch (\Exception) {
            throw new \InvalidArgumentException('Dates must use RFC3339.');
        }
    }
}
