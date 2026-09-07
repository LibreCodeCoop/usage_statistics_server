<?php

declare(strict_types=1);

namespace OCA\UsageStatisticsServer\Controller;

use OCA\UsageStatisticsServer\Db\ReportRepository;
use OCA\UsageStatisticsServer\Db\SchemaRepository;
use OCA\UsageStatisticsServer\Service\ConflictingReport;
use OCA\UsageStatisticsServer\Service\InvalidReport;
use OCA\UsageStatisticsServer\Service\ReportFactory;
use OCA\UsageStatisticsServer\Service\SchemaValidator;
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
        private readonly SchemaRepository $schemas,
        private readonly SchemaValidator $schemaValidator,
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
            $schema = $this->schemas->find($report->application, $report->schemaVersion);
            if ($schema === null) {
                throw new InvalidReport('Application schema is not registered.');
            }
            $this->schemaValidator->validateReport($report, $schema);

            $this->repository->store($report);
        } catch (InvalidReport|\JsonException $e) {
            return new DataResponse(['error' => 'invalid_report', 'message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (ConflictingReport $e) {
            return new DataResponse(['error' => 'conflicting_report', 'message' => $e->getMessage()], Http::STATUS_CONFLICT);
        }

        return new DataResponse(['status' => 'accepted'], Http::STATUS_OK);
    }
}
