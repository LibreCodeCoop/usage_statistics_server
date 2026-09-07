<?php

declare(strict_types=1);

return [
    'ocs' => [
        ['name' => 'report#create', 'url' => '/api/v1/reports', 'verb' => 'POST'],
        ['name' => 'schema#create', 'url' => '/api/v1/admin/schemas', 'verb' => 'POST'],
        ['name' => 'schema#get', 'url' => '/api/v1/admin/schemas/{application}/{schemaVersion}', 'verb' => 'GET'],
        ['name' => 'settings#get', 'url' => '/api/v1/admin/settings', 'verb' => 'GET'],
        ['name' => 'settings#update', 'url' => '/api/v1/admin/settings', 'verb' => 'PUT'],
        ['name' => 'statistics#summary', 'url' => '/api/v1/admin/{application}', 'verb' => 'GET'],
        ['name' => 'statistics#distribution', 'url' => '/api/v1/admin/{application}/metrics/{category}/{key}/distribution', 'verb' => 'GET'],
        ['name' => 'statistics#numerical', 'url' => '/api/v1/admin/{application}/metrics/{category}/{key}/numerical', 'verb' => 'GET'],
        ['name' => 'statistics#numericalHistory', 'url' => '/api/v1/admin/{application}/metrics/{category}/{key}/numerical/history', 'verb' => 'GET'],
    ],
];
