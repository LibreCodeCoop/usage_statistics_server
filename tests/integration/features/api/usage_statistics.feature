Feature: usage statistics OCS API

  Scenario: administrator registers and reads an application schema
    Given as user "admin"
    When sending "post" to ocs "/apps/usage_statistics_server/api/v1/admin/schemas"
      | application   | behat_schema |
      | schemaVersion | 1 |
      | metrics       | [{"category":"server","key":"version","type":"string","kind":"snapshot","aggregation":"distribution","description":"Application version","required":true}] |
    Then the response should have a status code 201
    And the response should be a JSON array with the following mandatory values
      | key                         | value        |
      | (jq).ocs.data.application   | behat_schema |
      | (jq).ocs.data.schemaVersion | 1            |
      | (jq).ocs.data.status        | created      |
    When sending "get" to ocs "/apps/usage_statistics_server/api/v1/admin/schemas/behat_schema/1"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                         | value        |
      | (jq).ocs.data.application   | behat_schema |
      | (jq).ocs.data.schemaVersion | 1            |

  Scenario: registering the same schema is idempotent
    Given as user "admin"
    When sending "post" to ocs "/apps/usage_statistics_server/api/v1/admin/schemas"
      | application   | behat_schema_idempotent |
      | schemaVersion | 1 |
      | metrics       | [{"category":"server","key":"version","type":"string","kind":"snapshot","aggregation":"distribution","description":"Application version","required":true}] |
    Then the response should have a status code 201
    When sending "post" to ocs "/apps/usage_statistics_server/api/v1/admin/schemas"
      | application   | behat_schema_idempotent |
      | schemaVersion | 1 |
      | metrics       | [{"category":"server","key":"version","type":"string","kind":"snapshot","aggregation":"distribution","description":"Application version","required":true}] |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                  | value              |
      | (jq).ocs.data.status | already_registered |

  Scenario: administrator cannot replace an immutable schema version
    Given as user "admin"
    When sending "post" to ocs "/apps/usage_statistics_server/api/v1/admin/schemas"
      | application   | behat_schema_conflict |
      | schemaVersion | 1 |
      | metrics       | [{"category":"server","key":"version","type":"string","kind":"snapshot","aggregation":"distribution","description":"Application version","required":true}] |
    Then the response should have a status code 201
    When sending "post" to ocs "/apps/usage_statistics_server/api/v1/admin/schemas"
      | application   | behat_schema_conflict |
      | schemaVersion | 1 |
      | metrics       | [{"category":"server","key":"version","type":"string","kind":"snapshot","aggregation":"distribution","description":"Changed meaning","required":true}] |
    Then the response should have a status code 409
    And the response should be a JSON array with the following mandatory values
      | key                 | value           |
      | (jq).ocs.data.error | schema_conflict |

  Scenario: anonymous report submission is accepted and retry is idempotent
    Given as user "admin"
    When sending "post" to ocs "/apps/usage_statistics_server/api/v1/admin/schemas"
      | application   | behat_report |
      | schemaVersion | 1 |
      | metrics       | [{"category":"server","key":"version","type":"string","kind":"snapshot","aggregation":"distribution","description":"Application version","required":true},{"category":"usage","key":"requests_completed","type":"integer","kind":"period","aggregation":"numerical","description":"Completed requests","required":true}] |
    Then the response should have a status code 201
    Given as anonymous user
    When sending "post" to ocs "/apps/usage_statistics_server/api/v1/reports"
      | protocolVersion | 1 |
      | application     | behat_report |
      | installationId  | install-behat-report |
      | schemaVersion   | 1 |
      | period          | {"start":"2026-08-01T00:00:00Z","end":"2026-09-01T00:00:00Z"} |
      | metrics         | [{"category":"server","key":"version","type":"string","value":"1.0.0"},{"category":"usage","key":"requests_completed","type":"integer","value":12}] |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                  | value    |
      | (jq).ocs.data.status | accepted |
    When sending "post" to ocs "/apps/usage_statistics_server/api/v1/reports"
      | protocolVersion | 1 |
      | application     | behat_report |
      | installationId  | install-behat-report |
      | schemaVersion   | 1 |
      | period          | {"start":"2026-08-01T00:00:00Z","end":"2026-09-01T00:00:00Z"} |
      | metrics         | [{"category":"server","key":"version","type":"string","value":"1.0.0"},{"category":"usage","key":"requests_completed","type":"integer","value":12}] |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                  | value    |
      | (jq).ocs.data.status | accepted |

  Scenario: report with an unknown metric is rejected
    Given as user "admin"
    When sending "post" to ocs "/apps/usage_statistics_server/api/v1/admin/schemas"
      | application   | behat_unknown_metric |
      | schemaVersion | 1 |
      | metrics       | [{"category":"server","key":"version","type":"string","kind":"snapshot","aggregation":"distribution","description":"Application version","required":true}] |
    Then the response should have a status code 201
    Given as anonymous user
    When sending "post" to ocs "/apps/usage_statistics_server/api/v1/reports"
      | protocolVersion | 1 |
      | application     | behat_unknown_metric |
      | installationId  | install-unknown-metric |
      | schemaVersion   | 1 |
      | period          | {"start":"2026-08-01T00:00:00Z","end":"2026-09-01T00:00:00Z"} |
      | metrics         | [{"category":"server","key":"version","type":"string","value":"1.0.0"},{"category":"usage","key":"unexpected","type":"integer","value":1}] |
    Then the response should have a status code 400
    And the response should be a JSON array with the following mandatory values
      | key                 | value          |
      | (jq).ocs.data.error | invalid_report |

  Scenario: changing schema version for the same installation and reporting period conflicts
    Given as user "admin"
    When sending "post" to ocs "/apps/usage_statistics_server/api/v1/admin/schemas"
      | application   | behat_period_conflict |
      | schemaVersion | 1 |
      | metrics       | [{"category":"server","key":"version","type":"string","kind":"snapshot","aggregation":"distribution","description":"Application version","required":true}] |
    Then the response should have a status code 201
    When sending "post" to ocs "/apps/usage_statistics_server/api/v1/admin/schemas"
      | application   | behat_period_conflict |
      | schemaVersion | 2 |
      | metrics       | [{"category":"server","key":"version","type":"string","kind":"snapshot","aggregation":"distribution","description":"Application version","required":true}] |
    Then the response should have a status code 201
    Given as anonymous user
    When sending "post" to ocs "/apps/usage_statistics_server/api/v1/reports"
      | protocolVersion | 1 |
      | application     | behat_period_conflict |
      | installationId  | install-period-conflict |
      | schemaVersion   | 1 |
      | period          | {"start":"2026-08-01T00:00:00Z","end":"2026-09-01T00:00:00Z"} |
      | metrics         | [{"category":"server","key":"version","type":"string","value":"1.0.0"}] |
    Then the response should have a status code 200
    When sending "post" to ocs "/apps/usage_statistics_server/api/v1/reports"
      | protocolVersion | 1 |
      | application     | behat_period_conflict |
      | installationId  | install-period-conflict |
      | schemaVersion   | 2 |
      | period          | {"start":"2026-08-01T00:00:00Z","end":"2026-09-01T00:00:00Z"} |
      | metrics         | [{"category":"server","key":"version","type":"string","value":"1.0.0"}] |
    Then the response should have a status code 409
    And the response should be a JSON array with the following mandatory values
      | key                 | value              |
      | (jq).ocs.data.error | conflicting_report |

  Scenario: administrator can read current aggregates
    Given as user "admin"
    When sending "post" to ocs "/apps/usage_statistics_server/api/v1/admin/schemas"
      | application   | behat_statistics |
      | schemaVersion | 1 |
      | metrics       | [{"category":"server","key":"version","type":"string","kind":"snapshot","aggregation":"distribution","description":"Application version","required":true},{"category":"usage","key":"requests_completed","type":"integer","kind":"period","aggregation":"numerical","description":"Completed requests","required":true}] |
    Then the response should have a status code 201
    Given as anonymous user
    When sending "post" to ocs "/apps/usage_statistics_server/api/v1/reports"
      | protocolVersion | 1 |
      | application     | behat_statistics |
      | installationId  | install-behat-statistics |
      | schemaVersion   | 1 |
      | period          | {"start":"2026-08-15T00:00:00Z","end":"2026-09-01T00:00:00Z"} |
      | metrics         | [{"category":"server","key":"version","type":"string","value":"1.2.3"},{"category":"usage","key":"requests_completed","type":"integer","value":7}] |
    Then the response should have a status code 200
    Given as user "admin"
    When sending "get" to ocs "/apps/usage_statistics_server/api/v1/admin/applications/behat_statistics"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                               | value            |
      | (jq).ocs.data.application         | behat_statistics |
      | (jq).ocs.data.activeInstallations | 1                |
    When sending "get" to ocs "/apps/usage_statistics_server/api/v1/admin/applications/behat_statistics/metrics/server/version/distribution"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                          | value |
      | (jq).ocs.data.values[0].value | 1.2.3 |
      | (jq).ocs.data.values[0].count | 1     |
    When sending "get" to ocs "/apps/usage_statistics_server/api/v1/admin/applications/behat_statistics/metrics/usage/requests_completed/numerical"
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                            | value |
      | (jq).ocs.data.statistics.count   | 1     |
      | (jq).ocs.data.statistics.average | 7     |
      | (jq).ocs.data.statistics.total   | 7     |

  Scenario: historical range requires RFC3339
    Given as user "admin"
    When sending "get" to ocs "/apps/usage_statistics_server/api/v1/admin/applications/behat_statistics/metrics/usage/requests_completed/numerical/history?from=2026-09-01"
    Then the response should have a status code 400
    And the response should be a JSON array with the following mandatory values
      | key                 | value         |
      | (jq).ocs.data.error | invalid_range |

  Scenario: non-admin user cannot access administration endpoints
    Given user "behat-user" exists
    And as user "behat-user"
    When sending "get" to ocs "/apps/usage_statistics_server/api/v1/admin/settings"
    Then the response should have a status code 403

  Scenario: administrator can read and update retention settings
    Given as user "admin"
    When sending "get" to ocs "/apps/usage_statistics_server/api/v1/admin/settings"
    Then the response should have a status code 200
    When sending "put" to ocs "/apps/usage_statistics_server/api/v1/admin/settings"
      | retentionDays | 365 |
    Then the response should have a status code 200
    And the response should be a JSON array with the following mandatory values
      | key                         | value |
      | (jq).ocs.data.retentionDays | 365   |
