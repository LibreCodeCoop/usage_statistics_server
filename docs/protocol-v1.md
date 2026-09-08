# Usage Statistics Protocol v1

## Purpose

Protocol v1 defines how an application submits one usage-statistics report for one installation and one reporting period.

The protocol is deliberately small. It avoids registration handshakes and client attestation because a self-hosted, open-source client controls its own runtime and can therefore fabricate values before any signature or attestation step.

## Transport

Clients send reports over HTTPS.

Recommended endpoint shape:

```text
POST /api/v1/reports
```

The server SHOULD support idempotent resubmission of the same logical report.

A successful submission, including an idempotent retry, returns the same application-level response:

```json
{
  "status": "accepted"
}
```

The protocol does not expose internal database identifiers and does not require the server to tell the client whether a successful submission created a row or matched an existing report.

## Report

Example:

```json
{
  "protocolVersion": 1,
  "application": "libresign",
  "installationId": "3cc1f9e4c8c81b8b0f0f3b3517d0565b3cc09d7f4f2bce60d079f4667f361c73",
  "schemaVersion": 1,
  "period": {
    "start": "2026-08-01T00:00:00Z",
    "end": "2026-09-01T00:00:00Z"
  },
  "metrics": [
    {
      "category": "environment",
      "key": "version",
      "type": "string",
      "value": "12.0.0"
    },
    {
      "category": "usage",
      "key": "requests_completed",
      "type": "integer",
      "value": 72
    }
  ]
}
```

## Fields

### `protocolVersion`

Protocol contract version. Version 1 clients MUST send `1`.

### `application`

Stable application identifier, such as `libresign`.

It MUST be supplied by the application integration and MUST NOT be inferred from the endpoint host.

### `installationId`

Stable pseudonymous identifier for one application installation.

The protocol does not prescribe how the client derives it, but it MUST:

- remain stable across reporting periods for the same installation;
- avoid sending the raw Nextcloud instance ID or other local identifiers when a derived identifier can be used;
- not contain a hostname, URL, email address, user ID, secret, token, or other directly identifying value.

The client library SHOULD provide a standard derivation strategy.

### `schemaVersion`

Version of the metric schema defined by the sending application.

The server stores this value so historical reports remain interpretable after an application evolves its metrics.

The `usage_statistics_server` implementation requires the `(application, schemaVersion)` definition to be registered by an administrator before reports using it are accepted. Registered schema versions are immutable.

A schema version is report content, not part of report identity. Applications SHOULD activate a new schema at a reporting-period boundary. A second submission for the same installation and period using another schema version is a conflict, not another report.

### `period`

The reporting interval represented by period metrics.

`start` is inclusive and `end` is exclusive.

Applications SHOULD use stable calendar periods. Monthly reporting is the initial recommended cadence.

### `metrics`

A list of typed aggregate values.

Each metric contains:

- `category`: namespace within the application report;
- `key`: stable metric identifier within the category;
- `type`: one of the types supported by the protocol;
- `value`: scalar value compatible with `type`.

Protocol v1 initially supports:

- `integer`;
- `number`;
- `boolean`;
- `string`.

Arrays, objects, arbitrary event payloads, and user-level records are intentionally excluded.

## Metric semantics

The protocol transports metrics but does not define application-specific meaning.

Application schemas SHOULD document whether each metric is one of:

- `snapshot`: state observed when the report was created;
- `period`: activity occurring within the report period;
- `counter`: cumulative value whose interpretation requires care across periods;
- `categorical`: a bounded string/boolean classification.

Servers MUST NOT blindly sum repeated snapshots across periods.

The `usage_statistics_server` application schema also declares the allowed aggregation for each metric (`distribution`, `numerical`, or `none`).

## Idempotency

The logical identity of a report is:

```text
(application, installationId, period.start, period.end)
```

A server MUST prevent accidental duplicate storage for the same logical report.

`schemaVersion` is intentionally excluded from this identity. Otherwise a schema transition during one period could create two reports and double-count that installation.

Protocol v1 does not require a preliminary handshake or server-issued report token.

The `usage_statistics_server` implementation keeps the first accepted report immutable:

- a retry using the same schema version is accepted idempotently;
- a retry for the same logical period using another schema version is rejected as a conflict;
- neither case creates a second logical report.

## Current installation state

Historical reports can arrive out of order, so receive order MUST NOT decide which report represents the current state of an installation.

The `usage_statistics_server` implementation orders reports by reporting period:

1. the report with the later `period.end` is newer;
2. when `period.end` is equal, the report with the later `period.start` is newer;
3. the current report pointer only moves forward according to that ordering.

`last_seen_at` is independent from the current report pointer and records recent valid reporting activity. This means an older delayed report can refresh the installation activity timestamp without replacing its current metric snapshot.

The current-state update is performed with a conditional database update rather than a read-then-write decision, so concurrent submissions cannot make the pointer move backwards.

## Storage guidance

Servers SHOULD persist normalized validated fields rather than the arbitrary request body.

The `usage_statistics_server` implementation does not retain the raw JSON payload. Metric values are stored in typed columns according to their declared type, with exactly one value column populated for each metric.

## Validation

The server MUST validate at least:

- supported `protocolVersion`;
- required fields;
- application identifier format and length;
- installation identifier format and length;
- period ordering and allowed duration;
- metric count per report;
- category/key format and length;
- metric type/value compatibility;
- scalar value size.

The `usage_statistics_server` implementation additionally requires a registered application schema and rejects unknown metrics, type mismatches, and missing required metrics.

Invalid reports MUST NOT be partially persisted.

## Privacy

Protocol v1 is designed for aggregate usage statistics.

Applications MUST NOT submit:

- names;
- email addresses;
- user IDs;
- document or file names;
- document contents;
- raw instance URLs or hostnames;
- authentication tokens;
- secrets;
- IP addresses as metric values;
- individual user event streams.

Applications SHOULD bucket or otherwise reduce precision where exact values are not needed for a product or maintenance decision.

Transport-level metadata such as the source IP can still be observed by the receiving infrastructure. Server operators must document logging and retention behavior separately.

## Reliability and abuse model

Protocol v1 assumes reports are voluntary and self-reported.

The server can validate syntax and semantics, provide idempotency, rate-limit abusive traffic, and detect anomalous submissions. It cannot prove that a self-hosted client reported truthful values.

Digital signatures are not required by v1. A signature could prove continuity of a client-controlled key and payload integrity, but it would not prove that the reported metrics correspond to real application state.

Statistics derived from the service MUST therefore be interpreted as statistics reported by participating installations rather than an authoritative census.

## Active installation

A consumer MAY define an active reporting installation as a distinct installation ID for which at least one valid report was received within the last 45 days.

If this definition is used, dashboards and documentation SHOULD label it precisely, for example:

> installations reporting usage statistics within the last 45 days

A missing report cannot distinguish an uninstall from an offline server, disabled statistics, blocked network access, or other causes.

## Endpoint override

The receiving endpoint is an application concern, not a protocol constant.

An application SHOULD ship with its own default endpoint and MAY allow a system administrator to override it through server-side configuration such as a CLI or configuration file.

Client libraries MUST NOT hard-code a LibreSign endpoint as the global default for unrelated applications.
