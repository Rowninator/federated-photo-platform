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

## Intentionally deferred

Future web, mobile, and federation systems are intentionally not being added
yet.
