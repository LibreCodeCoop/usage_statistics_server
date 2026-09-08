<!--
SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Usage Statistics Server

Usage Statistics Server is a Nextcloud app that gives other apps a shared place to receive and aggregate opt-in usage statistics.

Instead of each app building its own collection backend, schema registry, retention policy, storage model, and administration API, it can send versioned reports to this server and reuse the same infrastructure.

LibreSign is the first intended consumer, but the protocol is generic and can be used by other Nextcloud apps.

## Why use it?

An app may need answers to questions such as:

- Which application versions are still being used?
- Which optional features are enabled?
- How does adoption change between releases?
- How many participating installations reported during a period?

Usage Statistics Server provides the backend for these questions without requiring user-level event tracking or application-specific database tables.

Reports are opt-in and self-reported by participating installations. The server validates them against an application schema, stores historical reporting periods, and exposes aggregated results to Nextcloud administrators.

## How it works

Each participating app:

1. defines the metrics it can report in a versioned schema;
2. shows users exactly which data can be submitted;
3. sends periodic reports with a persistent installation identifier;
4. lets this server validate, store, retain, and aggregate the reports.

The ingestion protocol is application-agnostic. Metric values are typed and schema-controlled, and retries for the same reporting period are idempotent.

The server does not treat submitted data as an audited census. Reports come from software running on infrastructure controlled by the sender, so aggregated results should be described as statistics reported by participating installations.

## Documentation

Start here if you want to integrate another Nextcloud app:

- [Protocol v1](docs/protocol-v1.md) — report format, validation rules, and ingestion behavior.
- [Application schemas](docs/application-schemas.md) — how applications define metrics and evolve schemas.
- [Administrative API](docs/admin-api.md) — endpoints for querying aggregated statistics.
- [Retention](docs/retention.md) — historical data retention and cleanup behavior.

The OpenAPI specifications are generated from the Nextcloud controller contracts and are kept in the repository for API consumers and generated types.

## Current support

The app currently targets Nextcloud 35 and 36 and is tested with SQLite, MySQL, MariaDB, and PostgreSQL.

## License

Usage Statistics Server is licensed under the GNU Affero General Public License v3 or later. See [COPYING](COPYING).
