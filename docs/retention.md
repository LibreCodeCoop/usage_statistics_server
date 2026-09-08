# Historical data retention

The server preserves report history so usage trends can be recomputed and compared over time. Historical data is not retained indefinitely by default.

## Default

The default retention period is 1095 days (three years).

A daily, time-insensitive Nextcloud background job removes data older than the configured retention period.

Cleanup removes:

- stale installation state whose last report is older than the cutoff;
- metrics belonging to expired reports;
- expired reports.

Registered application schemas are preserved because they define how retained and externally referenced report versions are interpreted.

## Limits

Retention can be configured between 45 and 3650 days.

The minimum matches the active-installation window. Allowing a shorter retention period would make the server forget installations that should still qualify as reporting within the last 45 days.

## Administrative API

Read the current value:

```text
GET /ocs/v2.php/apps/usage_statistics_server/api/v1/admin/settings
```

Update it:

```text
PUT /ocs/v2.php/apps/usage_statistics_server/api/v1/admin/settings
```

with `retentionDays` as an integer value.

The setting is stored using Nextcloud `IAppConfig` as an integer.

## Operational behavior

Report deletion is performed in batches of 500 reports to avoid a single large cleanup transaction. Metrics are deleted before their parent reports in the same transaction.

Retention is based on the server-side `received_at` timestamp, not on a client-provided timestamp. This prevents a client-controlled period value from extending server retention.

The approach is inspired by the configurable cleanup in Nextcloud's `survey_server`, but this application uses a finite default because it intentionally preserves historical reports rather than replacing each installation's previous snapshot.
