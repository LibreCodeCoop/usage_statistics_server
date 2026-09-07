<?php

declare(strict_types=1);

namespace OCA\UsageStatisticsServer\Controller;

use OCA\UsageStatisticsServer\Db\ReportRepository;
use OCA\UsageStatisticsServer\Service\InvalidReport;
use OCA\UsageStatisticsServer\Service\ReportFactory;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

final class ReportController extends OCSController {
    public function __construct(
        string $appName,
        IRequest $request,
        private readonly ReportFactory $factory,
        private readonly ReportRepository $repository,
    ) {
        parent::__construct($appName, $request);
    }

    #[PublicPage]
    #[NoCSRFRequired]
    #[AnonRateLimit(limit: 60, period: 3600)]
    public function create(): DataResponse {
        try {
            $payload = json_decode($this->request->getRawInput(), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($payload)) {
                throw new InvalidReport('Request body must be a JSON object.');
            }
            $report = $this->factory->fromPayload($payload);
            $stored = $this->repository->store($report, $payload);
        } catch (InvalidReport|\JsonException $e) {
            return new DataResponse(['error' => 'invalid_report', 'message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        }

        return new DataResponse([
            'id' => $stored['id'],
            'status' => $stored['created'] ? 'created' : 'already_received',
        ], $stored['created'] ? Http::STATUS_CREATED : Http::STATUS_OK);
    }
}
