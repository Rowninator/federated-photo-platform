# Laravel application guidance

This file supplements the repository-level [`AGENTS.md`](../AGENTS.md). Follow
that guidance and the broader architecture reference in
[`ARCHITECTURE.md`](../ARCHITECTURE.md).

- Treat `server/` as a Laravel 12 application targeting PHP 8.3-8.4.
- Do not introduce future-module systems or similar structural conventions
  without explicit authorization.
- Run the server test suite from this directory with `php artisan test`.
- Laravel Pint is available in this scaffold. Check formatting without changing
  files with `vendor\bin\pint.bat --test`.
- Inspect the relevant diff and test results before accepting changes.
