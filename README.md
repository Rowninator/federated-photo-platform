# Federated Photo Platform

This repository is the future home of a cross-platform, federated photo-sharing
social network inspired by Pixelfed. The product is intended to let people use
independently operated servers that can communicate with one another, rather
than requiring everyone to join one company's central service.

The project now includes a Laravel backend foundation in `server/`, including
local identity and session authentication. Broader product architecture and
federation implementation remain intentionally deferred.

## Start here

- [Documentation index](docs/README.md) — where project knowledge belongs
- [Architecture](ARCHITECTURE.md) — approved foundation-level architecture
  decisions
- [Product overview](docs/product/overview.md) — current facts, boundaries, and
  open questions
- [Decision records](docs/decisions/README.md) — how durable technical and
  product decisions will be recorded
- [Work plans](docs/plans/README.md) — how bounded implementation efforts will
  be planned once they begin
- [Agent guidance](AGENTS.md) — repository rules for coding agents and human
  contributors using them

## Current status

The Laravel application in `server/` is the current implementation foundation.
Documentation should remain concise and should distinguish confirmed facts
from proposals and unknowns as the product grows.
