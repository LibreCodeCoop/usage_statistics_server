<?php

declare(strict_types=1);

namespace OCA\UsageStatisticsServer\Controller;

use OCA\UsageStatisticsServer\Service\SettingsService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

final class SettingsController extends OCSController {
    public function __construct(
        string $appName,
        IRequest $request,
        private readonly SettingsService $settings,
    ) {
        parent::__construct($appName, $request);
    }

    public function get(): DataResponse {
        return new DataResponse([
            'retentionDays' => $this->settings->getRetentionDays(),
            'minimumRetentionDays' => SettingsService::MIN_RETENTION_DAYS,
            'maximumRetentionDays' => SettingsService::MAX_RETENTION_DAYS,
        ]);
    }

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
