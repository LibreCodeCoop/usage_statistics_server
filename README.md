<!--
SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Usage Statistics Server

A generic Nextcloud app for receiving, storing, aggregating, and exposing privacy-preserving usage statistics from participating applications.

LibreSign is expected to be its first real consumer, but the protocol and storage model are application-agnostic so other Nextcloud apps can use the same server.

## Goals

- Receive opt-in, self-reported usage statistics from applications.
- Preserve historical reports instead of only the latest snapshot.
- Support both current-state and time-series aggregations.
- Keep the ingestion protocol small and versioned.
- Provide strict schema and payload validation.
- Make repeated submissions for the same reporting period idempotent.
- Treat submitted data as voluntary self-reported statistics, not as an authoritative census.
- Provide operational abuse protection without pretending to solve client-side data falsification.
- Use native Nextcloud APIs for persistence, migrations, routing, administration, and background processing.

## Non-goals

- Collect personal data or user-level event streams.
- Prove that a self-hosted client reports truthful values.
- Require a central registration or handshake before a client can submit a report.
- Depend on LibreSign-specific concepts in the protocol or database model.

## Architecture

The server is a native Nextcloud app supporting Nextcloud 35 and 36.

The storage model separates current installation state from immutable report history:

- `usage_stats_installations`: materialized current state for each application/installation pair;
- `usage_stats_reports`: immutable reporting-period submissions;
- `usage_stats_metrics`: typed metric values belonging to reports.

This allows current-state queries to use only the latest report for each installation while historical queries continue to use all reports.

## Protocol

The ingestion protocol specification lives in [`docs/protocol-v1.md`](docs/protocol-v1.md).

Administrative query endpoints are documented in [`docs/admin-api.md`](docs/admin-api.md).

## Data reliability

Reports are self-declared by participating installations. Server-side validation can verify protocol conformance, reject malformed values, make retries idempotent, limit operational abuse, and identify statistical anomalies. It cannot prove that software running on infrastructure controlled by the sender reported truthful application data.

Public or product-facing statistics must therefore be described as statistics reported by participating installations, not as an audited count of all installations.

## Status

Early design and implementation.
