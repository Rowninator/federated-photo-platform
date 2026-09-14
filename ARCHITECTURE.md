# Architecture

## Purpose

This document records approved, foundation-level architecture decisions. The
project maintainers should update it when those decisions change.

## Current foundation

- The real backend application lives in `server/`.
- The backend framework is Laravel 12.x.
- The supported course PHP environment is PHP 8.3 or 8.4.
- The production-target relational database is MySQL.
- SQLite may be used temporarily for foundation and test convenience.

## Local identity and authentication

- `User` represents private authentication and account identity; `Profile`
  represents public social identity.
- Each local `User` has one `Profile`.
- Local usernames are normalized to lowercase and are unique.
- Local web authentication uses Laravel session authentication.
- Token and mobile authentication, username changes, and federation identity
  remain separate and intentionally deferred.

## Local media foundation

- `Media` belongs to `Profile`; deletion is restricted to the owning user.
- Image bytes live in filesystem/object storage, not the relational database.
  The database stores ownership and media metadata.
- Application code addresses media by Laravel filesystem disk + server-generated
  relative path, not machine-specific paths or client filenames.
- Development uses a private local `media` disk.
- Uploaded originals remain private. Display and thumbnail variants are derived
  from the retained original and can be rebuilt from it.
- Initial supported upload formats are JPEG and PNG.
- Image processing is synchronous for now; asynchronous processing is deferred
  to the future queue module.
- Production storage can later move behind the same Laravel filesystem
  abstraction without rewriting domain behavior.

## Intentionally deferred

Broader web, mobile, and federation systems are intentionally deferred.
