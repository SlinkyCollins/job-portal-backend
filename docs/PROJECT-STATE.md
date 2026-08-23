# JobNet Engineering State

## Project

JobNet modernization.

## Objective

Modernize the existing JobNet application while preserving current behavior.

Primary migration goals:

- Vanilla PHP → Laravel
- Angular 18 → Angular 22

## Current phase

Phase 1 — Engineering Safety Net

## Completed

### Phase 0 — Baseline

- [x] Baseline frozen
- [x] v1.0.0 tagged
- [x] SQL schema preserved
- [x] baseline documentation created

### Phase 1 — Testing

- [x] Testing strategy defined
- [x] PHPUnit installed
- [x] Testing database created
- [x] Testing environment configured
- [x] HTTP integration testing established
- [x] Native login valid credentials
- [x] Native login invalid password
- [x] Native login nonexistent user
- [x] Native login suspended user

## Current work

Native authentication safety net.

Remaining:

- [ ] Invalid/missing credentials

## Next workflow

Authorization / protected endpoints.

## Testing philosophy

Migration safety > coverage.

Use the smallest test set that protects risky existing behavior.

Prefer HTTP integration tests for legacy PHP APIs rather than coupling tests
to implementation details.

## Important decisions

- Production Aiven DB must never be used by tests.
- PHPUnit uses `.env.testing`.
- HTTP tests run against local XAMPP.
- Tests use `jobnet_test`.
- Tests should remain after migration and become behavioral regression tests.