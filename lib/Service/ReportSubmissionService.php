<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\UsageStatisticsServer\Service;

use OCA\UsageStatisticsServer\Db\ReportRepository;
use OCA\UsageStatisticsServer\Db\SchemaRepository;

final class ReportSubmissionService {
    public function __construct(
        private readonly ReportFactory $factory,
        private readonly ReportRepository $repository,
        private readonly SchemaRepository $schemas,
        private readonly SchemaValidator $schemaValidator,
    ) {
    }

    /**
     * @param array{
     *     protocolVersion:int,
     *     application:string,
     *     installationId:string,
     *     schemaVersion:int,
     *     period:array{start:string,end:string},
     *     metrics:list<array{category:string,key:string,type:string,value:string|int|float|bool}>
     * } $payload
     *
     * @throws InvalidReport
     * @throws ConflictingReport
     */
    public function submit(array $payload): void {
        $report = $this->factory->fromPayload($payload);
        $schema = $this->schemas->find($report->application, $report->schemaVersion);
        if ($schema === null) {
            throw new InvalidReport('Application schema is not registered.');
        }

        $this->schemaValidator->validateReport($report, $schema);
        $this->repository->store($report);
    }
}
