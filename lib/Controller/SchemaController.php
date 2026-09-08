<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\UsageStatisticsServer\Controller;

use OCA\UsageStatisticsServer\Db\SchemaRepository;
use OCA\UsageStatisticsServer\Service\InvalidReport;
use OCA\UsageStatisticsServer\Service\SchemaValidator;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

#[OpenAPI(scope: OpenAPI::SCOPE_ADMINISTRATION, tags: ['schemas'])]
final class SchemaController extends OCSController {
    public function __construct(
        string $appName,
        IRequest $request,
        private readonly SchemaRepository $schemas,
        private readonly SchemaValidator $validator,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * Register an application metric schema
     *
     * @param string $application Stable application identifier
     * @param int $schemaVersion Immutable schema version
     * @param list<array{category:string,key:string,type:string,kind:string,aggregation:string,description:string,required:bool}> $metrics Metric definitions
     *
     * @return DataResponse<Http::STATUS_OK|Http::STATUS_CREATED, array{application:string,schemaVersion:int,status:string}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_CONFLICT, array{error:string,message:string}, array{}>
     *
     * 200: Schema was already registered with the same definition
     * 201: Schema registered
     * 400: Invalid schema definition
     * 409: Schema version already exists with another definition
     */
    #[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/admin/schemas', requirements: ['apiVersion' => '(v1)'])]
    public function create(string $application, int $schemaVersion, array $metrics): DataResponse {
        try {
            $definition = $this->validator->validateDefinition([
                'application' => $application,
                'schemaVersion' => $schemaVersion,
                'metrics' => $metrics,
            ]);
            $created = $this->schemas->store(
                $definition['application'],
                $definition['schemaVersion'],
                $definition,
            );
        } catch (InvalidReport $e) {
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

    /**
     * Get one registered application metric schema
     *
     * @param string $application Stable application identifier
     * @param int $schemaVersion Schema version
     *
     * @return DataResponse<Http::STATUS_OK, array<string,mixed>, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{error:string}, array{}>
     *
     * 200: Registered schema definition
     * 404: Schema not found
     */
    #[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/admin/schemas/{application}/{schemaVersion}', requirements: ['apiVersion' => '(v1)'])]
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
