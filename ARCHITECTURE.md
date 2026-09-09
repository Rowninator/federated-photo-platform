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

## Intentionally deferred

Broader web, mobile, and federation systems are intentionally deferred.
