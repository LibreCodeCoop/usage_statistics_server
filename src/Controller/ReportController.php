<?php

declare(strict_types=1);

namespace App\Controller;

use App\UsageStatistics\InvalidReport;
use App\UsageStatistics\ReportFactory;
use App\UsageStatistics\ReportRepository;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final readonly class ReportController
{
    public function __construct(
        private ReportFactory $factory,
        private ReportRepository $repository,
    ) {
    }

    #[Route('/api/v1/reports', name: 'api_v1_reports_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $payload = $request->toArray();
            $report = $this->factory->fromPayload($payload);
            $stored = $this->repository->store($report, $payload);
        } catch (InvalidReport|JsonException $exception) {
            return new JsonResponse([
                'error' => 'invalid_report',
                'message' => $exception->getMessage(),
            ], 400);
        }

        return new JsonResponse([
            'id' => $stored['id'],
            'status' => $stored['created'] ? 'created' : 'already_received',
        ], $stored['created'] ? 201 : 200);
    }
}
