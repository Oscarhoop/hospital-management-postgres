# Improvement Roadmap

This roadmap prioritizes upgrades that make the system more secure, maintainable, and production-ready.

## 1) Security and Production Hardening (Highest Priority)

### Problems observed
- Multiple backend files force `display_errors=1`, which can leak internals in production.
- Authentication endpoint logs request bodies and detailed login events, including metadata that should be reduced in production.
- CORS is permissive (`*`) in shared middleware.
- Database initialization seeds a predictable default admin account.

### Recommended upgrades
- Introduce `APP_ENV` (`local`, `staging`, `production`) and centralize PHP error handling in one bootstrap.
- Disable `display_errors` in non-local environments and use structured logs with correlation IDs.
- Replace wildcard/dynamic CORS with a strict allowlist from environment variables.
- Remove default credentials from production paths; require admin bootstrap via one-time setup token.
- Add rate limiting / lockout for failed logins and basic CSRF protections for session-based auth.

## 2) Stability and Data Safety

### Problems observed
- `init_db.php` drops and recreates core tables before re-seeding data.
- Startup bootstrap runs initialization/seeding scripts automatically by default.

### Recommended upgrades
- Replace destructive initialization with versioned, idempotent migrations only.
- Split scripts into explicit modes:
  - `migrate` (safe, no data loss)
  - `seed:dev` (local-only)
  - `seed:demo` (optional)
- Guard any destructive reset behind explicit confirmation env vars and never execute in production.

## 3) Architecture and Maintainability

### Problems observed
- Frontend logic is concentrated in a very large single JS file.
- Backend endpoints mix routing, validation, and business logic inline.

### Recommended upgrades
- Refactor frontend into modules by domain (patients, appointments, billing, auth).
- Add a minimal build step (e.g., Vite) for bundling, linting, and environment separation.
- Introduce backend layering:
  - Request validation
  - Service layer (business logic)
  - Repository/data access layer
- Standardize API response/error schema for consistent client handling.

## 4) Performance and User Experience

### Problems observed
- Documentation identifies cross-region DB latency as a significant issue.
- The app makes many server round trips and can benefit from better caching/indexing.

### Recommended upgrades
- Add indexes based on real query patterns (`EXPLAIN ANALYZE` on key endpoints).
- Cache low-volatility datasets (doctor lists, room lists, permissions).
- Batch dashboard/reporting reads to reduce round trips.
- Add optimistic UI updates and better loading skeletons for perceived performance.

## 5) Quality Engineering and Delivery

### Problems observed
- No visible test framework/config in repository root.

### Recommended upgrades
- Add automated tests in layers:
  - Backend API tests (auth, RBAC, CRUD flows)
  - DB migration smoke tests
  - Frontend E2E smoke tests (login, create patient, create appointment)
- Add CI checks: linting, tests, and migration validation for each PR.
- Add release checklists and rollback steps for deployments.

## Suggested 30/60/90 Day Plan

### First 30 days
- Production hardening (`APP_ENV`, strict CORS, secure logging)
- Migration safety (disable destructive defaults)
- Add baseline CI with lint + smoke tests

### 31–60 days
- Modularize frontend and backend service/repository layers
- Add indexes and caching for high-traffic endpoints
- Introduce observability dashboards (error rates, p95 latency)

### 61–90 days
- Full automated test suite and staging gates
- Performance tuning informed by real metrics
- Disaster recovery drills and security review
