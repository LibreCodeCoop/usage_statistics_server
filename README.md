# Usage Statistics Server

A generic server for receiving, storing, aggregating, and exposing privacy-preserving usage statistics from participating applications.

This project is intentionally application-agnostic. LibreSign is expected to be its first real consumer, but the protocol and storage model must not depend on LibreSign-specific concepts.

## Goals

- Receive opt-in, self-reported usage statistics from applications.
- Preserve historical reports instead of only the latest snapshot.
- Support both current-state and time-series aggregations.
- Keep the ingestion protocol small, versioned, and implementation-independent.
- Provide strict schema and payload validation.
- Make repeated submissions for the same reporting period idempotent.
- Treat submitted data as voluntary self-reported statistics, not as an authoritative census.
- Provide operational abuse protection without pretending to solve client-side data falsification.

## Non-goals

- Collect personal data or user-level event streams.
- Prove that a self-hosted client reports truthful values.
- Require a central registration or handshake before a client can submit a report.
- Couple the protocol to Nextcloud, LibreSign, or any specific frontend framework.

## Planned architecture

The initial design separates reports from their individual metric values:

- `applications`: logical producers of statistics;
- `reports`: immutable reporting-period submissions associated with an installation;
- `metrics`: typed values belonging to a report;
- aggregation/query services for latest-state and historical views.

The exact persistence and application framework will be selected after the v1 protocol and threat model are reviewed.

## Protocol

The protocol specification lives in [`docs/protocol-v1.md`](docs/protocol-v1.md).

## Data reliability

Reports are self-declared by participating installations. Server-side validation can verify protocol conformance, reject malformed values, make retries idempotent, limit abuse, and identify statistical anomalies. It cannot prove that software running on infrastructure controlled by the sender reported truthful application data.

Public or product-facing statistics must therefore be described as statistics reported by participating installations, not as an audited count of all installations.

## Status

Early design and implementation.
