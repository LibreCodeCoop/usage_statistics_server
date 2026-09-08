Feature: metric aggregation contracts

  Scenario: aggregation endpoint must match the registered metric schema
    Given as user "admin"
    When sending "post" to ocs "/apps/usage_statistics_server/api/v1/admin/schemas"
      | application   | behat_aggregation |
      | schemaVersion | 1 |
      | metrics       | [{"category":"server","key":"version","type":"string","kind":"snapshot","aggregation":"distribution","description":"Application version","required":true}] |
    Then the response should have a status code 201
    When sending "get" to ocs "/apps/usage_statistics_server/api/v1/admin/applications/behat_aggregation/metrics/server/version/numerical"
    Then the response should have a status code 400
    And the response should be a JSON array with the following mandatory values
      | key                 | value               |
      | (jq).ocs.data.error | invalid_aggregation |

  Scenario: metric semantics cannot change between schema versions
    Given as user "admin"
    When sending "post" to ocs "/apps/usage_statistics_server/api/v1/admin/schemas"
      | application   | behat_schema_evolution |
      | schemaVersion | 1 |
      | metrics       | [{"category":"usage","key":"requests","type":"integer","kind":"period","aggregation":"numerical","description":"Requests","required":true}] |
    Then the response should have a status code 201
    When sending "post" to ocs "/apps/usage_statistics_server/api/v1/admin/schemas"
      | application   | behat_schema_evolution |
      | schemaVersion | 2 |
      | metrics       | [{"category":"usage","key":"requests","type":"integer","kind":"period","aggregation":"distribution","description":"Requests","required":true}] |
    Then the response should have a status code 409
    And the response should be a JSON array with the following mandatory values
      | key                 | value           |
      | (jq).ocs.data.error | schema_conflict |
