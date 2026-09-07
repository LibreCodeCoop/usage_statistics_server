<?php

declare(strict_types=1);

namespace OCA\UsageStatisticsServer\Controller;

use OCA\UsageStatisticsServer\Service\SettingsService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

#[OpenAPI(scope: OpenAPI::SCOPE_ADMINISTRATION, tags: ['settings'])]
final class SettingsController extends OCSController {
    public function __construct(
        string $appName,
        IRequest $request,
        private readonly SettingsService $settings,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * Get usage statistics server settings
     *
     * @return DataResponse<Http::STATUS_OK, array{retentionDays:int,minimumRetentionDays:int,maximumRetentionDays:int}, array{}>
     */
    #[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/admin/settings', requirements: ['apiVersion' => '(v1)'])]
    public function get(): DataResponse {
        return new DataResponse([
            'retentionDays' => $this->settings->getRetentionDays(),
            'minimumRetentionDays' => SettingsService::MIN_RETENTION_DAYS,
            'maximumRetentionDays' => SettingsService::MAX_RETENTION_DAYS,
        ]);
    }

    /**
     * Update usage statistics server settings
     *
     * @param int $retentionDays Number of days to retain reports
     *
     * @return DataResponse<Http::STATUS_OK, array{retentionDays:int}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error:string,message:string}, array{}>
     */
    #[ApiRoute(verb: 'PUT', url: '/api/{apiVersion}/admin/settings', requirements: ['apiVersion' => '(v1)'])]
    public function update(int $retentionDays): DataResponse {
        try {
            $retentionDays = $this->settings->setRetentionDays($retentionDays);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse([
                'error' => 'invalid_retention',
                'message' => $e->getMessage(),
            ], Http::STATUS_BAD_REQUEST);
        }

        return new DataResponse(['retentionDays' => $retentionDays]);
    }
}
