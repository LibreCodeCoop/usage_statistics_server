<?php

declare(strict_types=1);

namespace OCA\UsageStatisticsServer\Controller;

use OCA\UsageStatisticsServer\Db\SchemaRepository;
use OCA\UsageStatisticsServer\Service\InvalidReport;
use OCA\UsageStatisticsServer\Service\SchemaValidator;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

final class SchemaController extends OCSController {
    public function __construct(
        string $appName,
        IRequest $request,
        private readonly SchemaRepository $schemas,
        private readonly SchemaValidator $validator,
    ) {
        parent::__construct($appName, $request);
    }

    public function create(): DataResponse {
        try {
            $payload = json_decode($this->request->getRawInput(), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($payload)) {
                throw new InvalidReport('Request body must be a JSON object.');
            }

            $definition = $this->validator->validateDefinition($payload);
            $created = $this->schemas->store(
                $definition['application'],
                $definition['schemaVersion'],
                $definition,
            );
        } catch (InvalidReport|\JsonException $e) {
            return new DataResponse([
                'error' => 'invalid_schema',
                'message' => $e->getMessage(),
            ], Http::STATUS_BAD_REQUEST);
        } catch (\LogicException $e) {
            return new DataResponse([
                'error' => 'schema_conflict',
                'message' => $e->getMessage(),
            ], Http::STATUS_CONFLICT);
        }

        return new DataResponse([
            'application' => $definition['application'],
            'schemaVersion' => $definition['schemaVersion'],
            'status' => $created ? 'created' : 'already_registered',
        ], $created ? Http::STATUS_CREATED : Http::STATUS_OK);
    }

    public function get(string $application, int $schemaVersion): DataResponse {
        $definition = $this->schemas->find($application, $schemaVersion);
        if ($definition === null) {
            return new DataResponse([
                'error' => 'schema_not_found',
            ], Http::STATUS_NOT_FOUND);
        }

        return new DataResponse($definition);
    }
}
