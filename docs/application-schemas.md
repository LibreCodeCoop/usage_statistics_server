# Application metric schemas

The server uses versioned application schemas as an allowlist and interpretation contract for submitted metrics.

This is inspired by the role of `data.json` in Nextcloud's `survey_server`, but the schema is scoped to an application and version so unrelated applications can evolve independently.

## Definition

Example:

```json
{
  "application": "libresign",
  "schemaVersion": 1,
  "metrics": [
    {
      "category": "server",
      "key": "version",
      "type": "string",
      "kind": "snapshot",
      "aggregation": "distribution",
      "description": "LibreSign version",
      "required": true
    },
    {
      "category": "usage",
      "key": "requests_completed",
      "type": "integer",
      "kind": "period",
      "aggregation": "numerical",
      "description": "Completed signing requests during the reporting period",
      "required": true
    }
  ]
}
```

## Metric fields

- `category`: stable metric namespace;
- `key`: stable metric identifier inside the category;
- `type`: `integer`, `number`, `boolean`, or `string`;
- `kind`: `snapshot`, `period`, `counter`, or `categorical`;
- `aggregation`: `distribution`, `numerical`, or `none`;
- `description`: human-readable meaning;
- `required`: whether every report using this schema version must contain the metric.

`numerical` aggregation is only valid for `integer` and `number` metrics.

## Registration

Schemas are registered by a Nextcloud administrator:

```text
POST /ocs/v2.php/apps/usage_statistics_server/api/v1/admin/schemas
```

The request body is the schema definition JSON.

A stored schema can be inspected with:

```text
GET /ocs/v2.php/apps/usage_statistics_server/api/v1/admin/schemas/{application}/{schemaVersion}
```

Registration is idempotent when the same definition is submitted again.

A schema version is immutable after it is registered. Reusing the same `(application, schemaVersion)` with a different definition returns a conflict. Applications must increment `schemaVersion` when the contract changes.

## Ingestion behavior

A report is accepted only when its `(application, schemaVersion)` is registered.

The server rejects a report when:

- its schema is not registered;
- it contains a metric not present in the schema;
- a metric type does not match the schema;
- a required metric is missing.

This makes the schema an explicit allowlist rather than allowing arbitrary metric names into storage.

## Evolution

Schemas are immutable so historical reports keep the interpretation they had when received.

If a metric meaning or type changes incompatibly, prefer both a new `schemaVersion` and a new metric key. This keeps historical aggregates unambiguous when a query spans multiple schema versions.

Adding an optional metric or changing presentation metadata still requires a new `schemaVersion`; the client and server contract should never depend on silently changing a registered definition.
