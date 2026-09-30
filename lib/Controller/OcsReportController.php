<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\UsageStatisticsServer\Controller;

use OCA\UsageStatisticsServer\Service\ConflictingReport;
use OCA\UsageStatisticsServer\Service\InvalidReport;
use OCA\UsageStatisticsServer\Service\ReportSubmissionService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

#[OpenAPI(tags: ['reports'])]
final class OcsReportController extends OCSController {
    public function __construct(
        string $appName,
        IRequest $request,
        private readonly ReportSubmissionService $submission,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * Submit a usage statistics report through the Nextcloud OCS API
     *
     * This endpoint has the same Protocol v1 request semantics as the plain JSON endpoint,
     * but wraps the response using the standard Nextcloud OCS envelope.
     *
     * @param int $protocolVersion Usage statistics protocol version
     * @param string $application Stable application identifier
     * @param string $installationId Stable pseudonymous installation identifier
     * @param int $schemaVersion Registered application schema version
     * @param array{start:string,end:string} $period RFC3339 reporting period, start inclusive and end exclusive
     * @param list<array{category:string,key:string,type:string,value:string|int|float|bool}> $metrics Aggregate metric values
     *
     * @return DataResponse<Http::STATUS_OK, array{status:string}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_CONFLICT, array{error:string,message:string}, array{}>
     *
     * 200: Report accepted
     * 400: Invalid report
     * 409: Conflicting report for the reporting period
     */
    #[PublicPage]
    #[NoCSRFRequired]
    #[AnonRateLimit(limit: 60, period: 3600)]
    #[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/reports', requirements: ['apiVersion' => '(v1)'])]
    public function create(
        int $protocolVersion,
        string $application,
        string $installationId,
        int $schemaVersion,
        array $period,
        array $metrics,
    ): DataResponse {
        try {
            $this->submission->submit([
                'protocolVersion' => $protocolVersion,
                'application' => $application,
                'installationId' => $installationId,
                'schemaVersion' => $schemaVersion,
                'period' => $period,
                'metrics' => $metrics,
            ]);
        } catch (InvalidReport $e) {
            return new DataResponse(['error' => 'invalid_report', 'message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (ConflictingReport $e) {
            return new DataResponse(['error' => 'conflicting_report', 'message' => $e->getMessage()], Http::STATUS_CONFLICT);
        }

        return new DataResponse(['status' => 'accepted'], Http::STATUS_OK);
    }
}
