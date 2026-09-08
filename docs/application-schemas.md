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

## Ingestion behavior

A report is accepted only when its `(application, schemaVersion)` is registered.

The server rejects a report when:

- its schema is not registered;
- it contains a metric not present in the schema;
- a metric type does not match the schema;
- a required metric is missing.

This makes the schema an explicit allowlist rather than allowing arbitrary metric names into storage.

## Evolution

Schemas are immutable from the client's point of view. If the meaning, type, or required set of metrics changes incompatibly, the application should publish a new `schemaVersion`.

The server currently allows an administrator to replace a stored definition for operational correction. This should be used carefully once reports for that version exist, because historical reports are interpreted using the registered definition.
