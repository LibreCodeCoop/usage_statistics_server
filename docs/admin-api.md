# Administrative statistics API

The administrative API exposes aggregated and historical usage statistics to authenticated Nextcloud administrators. These endpoints are not public by default.

All endpoints use the OCS namespace of the `usage_statistics_server` app.

## Summary

`GET /ocs/v2.php/apps/usage_statistics_server/api/v1/admin/{application}`

Returns the number of installations that submitted a valid report during the active window.

The active window is currently fixed at 45 days.

Example response data:

```json
{
  "application": "libresign",
  "activeWindowDays": 45,
  "activeInstallations": 142
}
```

`activeInstallations` means installations reporting usage statistics within the last 45 days. It must not be presented as the total number of installations of the application.

## Current distribution

`GET /ocs/v2.php/apps/usage_statistics_server/api/v1/admin/{application}/metrics/{category}/{key}/distribution`

Returns the distribution of a metric using only the latest report for every known installation.

Example:

```json
{
  "application": "libresign",
  "category": "server",
  "key": "version",
  "values": [
    {"value": "12.0.0", "count": 80},
    {"value": "11.0.2", "count": 45}
  ]
}
```

This endpoint represents current state. Older reports from the same installation do not contribute to the distribution.

## Metric history

`GET /ocs/v2.php/apps/usage_statistics_server/api/v1/admin/{application}/metrics/{category}/{key}/history`

Optional query parameters:

- `from`: ISO 8601 timestamp;
- `to`: ISO 8601 timestamp.

When omitted, `to` defaults to the current time and `from` defaults to one year before `to`.

Example response data:

```json
{
  "application": "libresign",
  "category": "signing",
  "key": "requests_completed",
  "from": "2026-01-01T00:00:00+00:00",
  "to": "2026-09-01T00:00:00+00:00",
  "values": [
    {
      "periodStart": "2026-07-01 00:00:00",
      "periodEnd": "2026-08-01 00:00:00",
      "value": 120,
      "type": "integer"
    }
  ]
}
```

This endpoint returns historical report values. It deliberately does not collapse reports into the latest installation state.

## Interpretation

Reports are voluntary self-reported data. These APIs provide usage indicators and operational statistics, not an authenticated census. Consumers must preserve this distinction when displaying or publishing derived numbers.
