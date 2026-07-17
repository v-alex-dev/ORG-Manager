# ORG-Manager — Backend API

REST API for managing decision-making body meetings (CFG and COMITE). Built with Laravel 13, PHP 8.3, and MySQL. Designed API-first for a mobile frontend.

---

## Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 13 |
| Language | PHP 8.3 |
| Database | MySQL |
| Authentication | Laravel Sanctum (token-based) |
| Testing | PHPUnit with a dedicated MySQL test database |

---

## Domain Overview

The application manages two types of decision-making bodies: **CFG** and **COMITE**. Each body holds meetings called **OrgInstances**. Each OrgInstance contains **Tasks** (agenda items), each belonging to a **Service** (department).

A task carries a **reference code** that is generated automatically on creation and never changes — even if the task is moved to another OrgInstance. The format is `{TYPE}-{YEAR}-{NNN}` (e.g. `CFG-2026-007`).

```
Users ──< OrgInstances ──< Tasks >── Services
```

---

## Database Schema

### `users`
Standard Laravel auth table managed by Sanctum.

### `services`
| Column | Type | Notes |
|---|---|---|
| id | PK | |
| name | VARCHAR | Unique department name (e.g. "Logistics") |
| timestamps | | |

### `org_instances`
| Column | Type | Notes |
|---|---|---|
| id | PK | |
| type | ENUM | `CFG` or `COMITE` |
| recurrence_type | ENUM | `HEBDO` (weekly) or `OCCASIONNEL` |
| date_meeting | DATE | |
| is_archived | BOOLEAN | Default `false`. Archiving is manual only. |
| timestamps | | |

### `tasks`
| Column | Type | Notes |
|---|---|---|
| id | PK | |
| organization_id | FK | References `org_instances.id`, cascade delete |
| service_id | FK | References `services.id` |
| poj_title | VARCHAR | Agenda item title |
| poj_description | TEXT | Nullable |
| status | ENUM | `TODO` or `DONE`, default `TODO` |
| reference_code | VARCHAR(20) | Unique, indexed. Format: `CFG-2026-001` |
| timestamps | | |

---

## Reference Code Generation

On every `POST /api/tasks`, the `ReferenceCodeService` queries the `MAX()` of existing reference codes for the given type and year, then increments by one. Using `MAX()` instead of `COUNT()` ensures correctness when tasks have been permanently deleted.

```
CFG-2026-001
CFG-2026-002  <- deleted
CFG-2026-003  <- next generated code is CFG-2026-004, not CFG-2026-003
```

The reference code is immutable. Moving a task to another OrgInstance preserves it.

---

## Installation

**Requirements:** PHP 8.3, Composer, MySQL.

```bash
git clone <repository-url>
cd org-manager

cp .env.example .env
composer install
php artisan key:generate
```

Configure your `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=org_manager
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

Run migrations:

```bash
php artisan migrate
```

Start the development server:

```bash
php artisan serve
```

---

## Running Tests

Tests use a dedicated MySQL database to avoid SQLite compatibility issues.

Create the test database:

```sql
CREATE DATABASE org_manager_test;
```

The test database is already configured in `phpunit.xml`:

```xml
<env name="DB_CONNECTION" value="mysql"/>
<env name="DB_DATABASE" value="org_manager_test"/>
```

Run the full test suite:

```bash
php artisan test
```

Run a specific suite:

```bash
php artisan test --filter TaskTest
```

Current coverage: **55 tests, all passing**.

---

## API Reference

All endpoints except `/api/login` require a Sanctum token in the `Authorization` header:

```
Authorization: Bearer {token}
```

Every response is JSON. Validation errors return `422` with an `errors` object. Authentication failures return `401`.

---

### Authentication

#### `POST /api/login`

```json
// Request
{
  "email": "user@example.com",
  "password": "secret"
}

// Response 200
{
  "token": "1|abc...",
  "user": { "id": 1, "name": "John", "email": "user@example.com" }
}
```

#### `POST /api/logout`

Revokes all tokens for the authenticated user.

```json
// Response 200
{ "message": "Logged out successfully." }
```

#### `GET /api/user`

```json
// Response 200
{
  "user": { "id": 1, "name": "John", "email": "user@example.com" }
}
```

---

### Services

#### `GET /api/services`

Returns all services ordered alphabetically.

```json
// Response 200
{
  "data": [
    { "id": 1, "name": "HR" },
    { "id": 2, "name": "Logistics" }
  ]
}
```

#### `POST /api/services`

```json
// Request
{ "name": "Logistics" }

// Response 201
{
  "data": { "id": 2, "name": "Logistics" }
}
```

---

### ORG Instances

#### `GET /api/orgs/active?type=CFG`

Returns all non-archived instances for a given type, ordered by `date_meeting` ascending. The `type` parameter is required.

```json
// Response 200
{
  "data": [
    {
      "id": 1,
      "type": "CFG",
      "recurrence_type": "HEBDO",
      "date_meeting": "2026-07-21T00:00:00.000000Z",
      "is_archived": false
    }
  ]
}
```

#### `POST /api/orgs`

```json
// Request
{
  "type": "CFG",
  "recurrence_type": "HEBDO",
  "date_meeting": "2026-07-21"
}

// Response 201
{
  "data": { "id": 1, "type": "CFG", "recurrence_type": "HEBDO", "date_meeting": "2026-07-21T00:00:00.000000Z", "is_archived": false }
}
```

#### `PUT /api/orgs/{id}/archive`

Sets `is_archived` to `true`. Returns `422` if the instance is already archived.

```json
// Response 200
{
  "data": { "id": 1, "is_archived": true, ... }
}
```

---

### Tasks

#### `GET /api/orgs/{id}/tasks`

Returns all tasks for a given OrgInstance, ordered by creation date. Includes `service` and `org_instance` relations. Returns `404` if the OrgInstance does not exist.

```json
// Response 200
{
  "data": [
    {
      "id": 1,
      "poj_title": "Review the budget",
      "poj_description": null,
      "status": "TODO",
      "reference_code": "CFG-2026-001",
      "service": { "id": 2, "name": "Finance" },
      "org_instance": { "id": 1, "type": "CFG", ... }
    }
  ]
}
```

#### `POST /api/tasks`

The `reference_code` is generated automatically. It cannot be set manually.

```json
// Request
{
  "org_instance_id": 1,
  "service_id": 2,
  "poj_title": "Review the budget",
  "poj_description": "Optional description"
}

// Response 201
{
  "data": {
    "id": 1,
    "poj_title": "Review the budget",
    "status": "TODO",
    "reference_code": "CFG-2026-001",
    "service": { ... },
    "org_instance": { ... }
  }
}
```

#### `PATCH /api/tasks/{id}/status`

Toggles the task status between `TODO` and `DONE`. No request body needed.

```json
// Response 200
{
  "data": { "id": 1, "status": "DONE", ... }
}
```

#### `PATCH /api/tasks/{id}/move`

Moves a task to another OrgInstance. The target must be of the same type (CFG to CFG only). The `reference_code` and `status` are preserved. Returns `422` if the target is the same instance or a different type.

```json
// Request
{ "org_instance_id": 5 }

// Response 200
{
  "data": { "id": 1, "reference_code": "CFG-2026-001", ... }
}
```

---

### Archives

#### `GET /api/archives`

Returns tasks from archived OrgInstances. All filters are optional and cumulative. Results are cursor-paginated at 25 per page.

| Parameter | Type | Description |
|---|---|---|
| type | string | `CFG` or `COMITE` |
| year | integer | Filters on `date_meeting` year |
| poj_title | string | Partial match (`LIKE %value%`) |
| reference_code | string | Partial match (`LIKE %value%`) |
| cursor | string | Cursor token from a previous response |

```json
// GET /api/archives?type=CFG&year=2026&poj_title=budget

// Response 200
{
  "data": [ { ... }, { ... } ],
  "path": "http://localhost/api/archives",
  "per_page": 25,
  "next_cursor": "eyJpZCI6...",
  "next_page_url": "http://localhost/api/archives?cursor=eyJpZCI6...",
  "prev_cursor": null,
  "prev_page_url": null
}
```

---

## Project Structure

```
app/
  Http/
    Controllers/Api/
      AuthController.php        — login, logout, user
      ServiceController.php     — index, store
      OrgInstanceController.php — active, store, archive
      TaskController.php        — index, store, updateStatus, move
      ArchiveController.php     — index (with filters + cursor pagination)
    Middleware/
      ForceJsonResponse.php     — forces Accept: application/json on all API requests
  Models/
    User.php
    Service.php
    OrgInstance.php
    Task.php
  Services/
    ReferenceCodeService.php    — MAX()-based reference code generation
routes/
  api.php
tests/
  Feature/
    AuthTest.php
    ServiceTest.php
    OrgInstanceTest.php
    TaskTest.php
    ArchiveTest.php
    ReferenceCodeServiceTest.php
```

---

## Key Design Decisions

**`organization_id` as the FK column name on `tasks`** — the migration uses `organization_id` rather than the Laravel convention `org_instance_id`. The `Task` model explicitly declares the foreign key in the `belongsTo` relationship to match.

**`ForceJsonResponse` middleware** — renamed from `JsonResponse` to avoid a naming collision with `Illuminate\Http\JsonResponse`. Explicit return type hints were removed from controllers for the same reason.

**`MAX()` over `COUNT()` for reference codes** — `COUNT()` would produce duplicate codes after a task is permanently deleted. `MAX()` always reads the highest existing number and increments from there.

**No soft deletes on tasks** — task deletion is permanent by design. The `MAX()` strategy handles the resulting gaps in reference code sequences.

**No role system for MVP** — Spatie permissions is installed but unused. All authenticated users have equal access.
