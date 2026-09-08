<?php

declare(strict_types=1);

return [
    'ocs' => [
        ['name' => 'report#create', 'url' => '/api/v1/reports', 'verb' => 'POST'],
        ['name' => 'statistics#summary', 'url' => '/api/v1/admin/{application}', 'verb' => 'GET'],
        ['name' => 'statistics#distribution', 'url' => '/api/v1/admin/{application}/metrics/{category}/{key}/distribution', 'verb' => 'GET'],
        ['name' => 'statistics#history', 'url' => '/api/v1/admin/{application}/metrics/{category}/{key}/history', 'verb' => 'GET'],
    ],
];
