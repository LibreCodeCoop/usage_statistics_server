# Administrative statistics API

The administrative API exposes aggregated usage statistics to authenticated Nextcloud administrators. These endpoints are not public by default.

All endpoints use the OCS namespace of the `usage_statistics_server` app.

The API intentionally exposes aggregates rather than individual installation reports. Normalized reports remain internal so later privacy thresholds and abuse filtering can be applied without changing the public contract. The server does not retain the arbitrary raw JSON request body after validation.

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

Returns a categorical distribution using only the latest report for every known installation.

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

## Current numerical evaluation

`GET /ocs/v2.php/apps/usage_statistics_server/api/v1/admin/{application}/metrics/{category}/{key}/numerical`

Returns the standard numerical evaluation for a numeric metric using only the latest report from each installation.

The shape follows the same useful aggregation set used by Nextcloud's `survey_server`: count, average, minimum, maximum, and total.

```json
{
  "application": "libresign",
  "category": "usage",
  "key": "requests_completed",
  "statistics": {
    "count": 142,
    "average": 26.34,
    "min": 0,
    "max": 820,
    "total": 3740
  }
}
```

## Numerical history

`GET /ocs/v2.php/apps/usage_statistics_server/api/v1/admin/{application}/metrics/{category}/{key}/numerical/history`

Optional query parameters:

- `from`: ISO 8601 timestamp;
- `to`: ISO 8601 timestamp.

When omitted, `to` defaults to the current time and `from` defaults to one year before `to`.

Reports are grouped by their reporting period. Individual installation values are not returned by this endpoint.

```json
{
  "application": "libresign",
  "category": "usage",
  "key": "requests_completed",
  "from": "2026-01-01T00:00:00+00:00",
  "to": "2026-09-01T00:00:00+00:00",
  "periods": [
    {
      "periodStart": "2026-07-01 00:00:00",
      "periodEnd": "2026-08-01 00:00:00",
      "count": 120,
      "average": 22.4,
      "min": 0,
      "max": 820,
      "total": 2688
    }
  ]
}
```

## Reference to survey_server

The Nextcloud `survey_server` is used as prior art for OCS ingestion and aggregation conventions. In particular, its distinction between diagram-like categorical statistics and numerical evaluation is useful and is retained here.

This app differs intentionally in important areas:

- historical reports are preserved instead of replacing the previous report from an installation;
- latest installation state is materialized separately from report history;
- metrics are explicitly typed by the protocol and stored in typed columns;
- application schemas are versioned;
- individual report values are not exposed through the administrative statistics API.

## Interpretation

Reports are voluntary self-reported data. These APIs provide usage indicators and operational statistics, not an authenticated census. Consumers must preserve this distinction when displaying or publishing derived numbers.
