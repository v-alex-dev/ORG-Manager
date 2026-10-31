# ORG-Manager Backend API

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

A task carries a **reference code** that is generated automatically on creation and never changes, even if the task is moved to another OrgInstance. The format is `{TYPE}-{YEAR}-{NNN}` (e.g. `CFG-2026-007`).

A task can also be **duplicated** into an OrgInstance of the other type (CFG to COMITE or the reverse). The copy gets its own reference code and keeps a link to the original task.

```
OrgInstances ──< Tasks >── Services
                  │
                  └── reference_task_id (self reference, copies only)
```

---

## Business Rules

| Rule | Description |
|---|---|
| Reference year | The year of a reference code is the **year of the meeting** (`date_meeting`), never the creation date |
| Yearly reset | The number restarts at `001` for each type and each year |
| Immutable reference | The reference code never changes (move and duplicate included) |
| Move | Same ORG type only (CFG to CFG, COMITE to COMITE), neither ORG can be archived |
| Duplicate | Different ORG type only, neither ORG can be archived, one duplicate per task |
| Archiving | Manual only. An archived ORG is frozen: no task can enter or leave it |
| One meeting per day | A given type cannot have two meetings on the same date |
| Title length | 100 characters maximum |
| Description length | 500 characters maximum |

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
| date_meeting | DATE | Unique per type (one meeting per body and per day) |
| is_archived | BOOLEAN | Default `false`. Archiving is manual only |
| timestamps | | |

### `tasks`
| Column | Type | Notes |
|---|---|---|
| id | PK | |
| organization_id | FK | References `org_instances.id`, cascade delete |
| service_id | FK | References `services.id` |
| poj_title | VARCHAR(255) | Agenda item title, limited to 100 characters by validation |
| poj_description | TEXT | Nullable, limited to 500 characters by validation |
| status | ENUM | `TODO` or `DONE`, default `TODO` |
| has_duplicate | BOOLEAN | Default `false`. `true` on the original task and on its copy once duplicated |
| reference_task_id | FK | Nullable, unique, references `tasks.id` (null on delete). Set only on copies |
| reference_code | VARCHAR(20) | Unique, indexed. Format: `CFG-2026-001` |
| timestamps | | |

The unique constraint on `reference_task_id` guarantees that a task can be duplicated only once, even if two requests arrive at the same time. MySQL accepts several `NULL` values in a unique column, so regular tasks are not affected.

---

## Reference Code Generation

On task creation and on duplication, the `ReferenceCodeService` reads the highest existing number for the given type and year, then increments it by one.

```
Type  : type of the target OrgInstance
Year  : year of the target OrgInstance date_meeting
Number: MAX of existing numbers for this type and year, plus 1
```

Using `MAX()` instead of `COUNT()` ensures correctness when tasks have been permanently deleted:

```
CFG-2026-001
CFG-2026-002  <- deleted
CFG-2026-003  <- next generated code is CFG-2026-004, not CFG-2026-003
```

Because the year comes from the meeting date, a meeting held in January 2027 creates `CFG-2027-001` even if tasks are created in December 2026 and `CFG-2026-xxx` references already exist.

The reference code is immutable. Moving a task to another OrgInstance preserves it. A duplicated task receives a **new** code based on the target type and year.

The format uses three digits, so the design assumes fewer than 1000 tasks per type and per year.

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
php artisan test --filter TaskDuplicateTest
```

Current coverage: **86 tests, all passing**.

---

## API Reference

All endpoints except `/api/login` require a Sanctum token in the `Authorization` header:

```
Authorization: Bearer {token}
```

Every response is JSON. Validation errors return `422` with an `errors` object. Authentication failures return `401`. Unknown resources return `404`.

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

Returns `422` if a meeting of the same type already exists on this date.

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

Every task response includes these relations: `service`, `org_instance` and `reference_task`.

`reference_task` is `null` for a regular task. For a copy created by the duplicate endpoint, it contains the `id` and `reference_code` of the original task.

#### `GET /api/orgs/{id}/tasks`

Returns all tasks for a given OrgInstance, ordered by creation date. Returns `404` if the OrgInstance does not exist.

```json
// Response 200
{
  "data": [
    {
      "id": 1,
      "poj_title": "Review the budget",
      "poj_description": null,
      "status": "TODO",
      "has_duplicate": false,
      "reference_code": "CFG-2026-001",
      "reference_task": null,
      "service": { "id": 2, "name": "Finance" },
      "org_instance": { "id": 1, "type": "CFG", ... }
    }
  ]
}
```

#### `POST /api/tasks`

The `reference_code` is generated automatically from the type and the meeting year of the OrgInstance. It cannot be set manually. A new task always starts with `status = TODO` and `has_duplicate = false`.

| Field | Rules |
|---|---|
| org_instance_id | required, integer, must exist |
| service_id | required, integer, must exist |
| poj_title | required, string, max 100 characters |
| poj_description | optional, string, max 500 characters |

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
    "has_duplicate": false,
    "reference_code": "CFG-2026-001",
    "reference_task": null,
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

Moves a task to another OrgInstance of the **same type**. The `reference_code` and `status` are preserved.

Returns `422` in these cases:

| Case | Message |
|---|---|
| Target is the same instance | `The task is already in this ORG.` |
| Source ORG is archived | `Cannot move a task from an archived ORG.` |
| Target ORG is archived | `Cannot move a task to an archived ORG.` |
| Different ORG type | `Cannot move a task to a different ORG type.` |

```json
// Request
{ "org_instance_id": 5 }

// Response 200
{
  "data": { "id": 1, "reference_code": "CFG-2026-001", ... }
}
```

#### `POST /api/tasks/{id}/duplicate`

Duplicates a task into an OrgInstance of the **other type** (CFG to COMITE or COMITE to CFG). The original task stays where it is.

The copy:
- gets a **new** reference code based on the target type and the meeting year of the target OrgInstance
- keeps the same title, description and service
- starts with `status = TODO`
- has `has_duplicate = true` and points to the original task through `reference_task`

The original task is also updated to `has_duplicate = true`. Both writes happen in a single database transaction.

Returns `422` in these cases:

| Case | Message |
|---|---|
| Task already duplicated (also blocks duplicating a copy) | `This task has already been duplicated.` |
| Source ORG is archived | `Cannot duplicate a task from an archived ORG.` |
| Target ORG is archived | `Cannot duplicate a task to an archived ORG.` |
| Target has the same ORG type | `A task can only be duplicated to a different ORG type.` |
| Missing or unknown `org_instance_id` | validation error on `org_instance_id` |

```json
// Request
{ "org_instance_id": 8 }

// Response 201
{
  "data": {
    "id": 15,
    "poj_title": "Review the budget",
    "status": "TODO",
    "has_duplicate": true,
    "reference_code": "COMITE-2027-001",
    "reference_task": {
      "id": 12,
      "reference_code": "CFG-2026-012"
    },
    "service": { ... },
    "org_instance": { "id": 8, "type": "COMITE", ... }
  }
}
```

---

### Archives

#### `GET /api/archives`

Returns tasks from archived OrgInstances. All filters are optional and cumulative. Results are cursor-paginated at 25 per page and ordered by `reference_code`.

| Parameter | Type | Description |
|---|---|---|
| type | string | `CFG` or `COMITE` |
| year | integer (4 digits) | Year of the meeting date |
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
      AuthController.php        login, logout, user
      ServiceController.php     index, store
      OrgInstanceController.php active, store, archive
      TaskController.php        index, store, updateStatus, move, duplicate
      ArchiveController.php     index (with filters and cursor pagination)
    Requests/
      StoreTaskRequest.php      validation and length limits for task creation
      MoveTaskRequest.php       validation for move
      DuplicateTaskRequest.php  validation for duplicate
    Middleware/
      ForceJsonResponse.php     forces Accept: application/json on all API requests
  Models/
    User.php
    Service.php
    OrgInstance.php
    Task.php
  Services/
    ReferenceCodeService.php    MAX() based reference code generation
routes/
  api.php
tests/
  Feature/
    AuthTest.php
    ServiceTest.php
    OrgInstanceTest.php
    TaskTest.php
    TaskDuplicateTest.php
    ArchiveTest.php
    ReferenceCodeServiceTest.php
```

---

## Key Design Decisions

**`organization_id` as the FK column name on `tasks`**: the migration uses `organization_id` rather than the Laravel convention `org_instance_id`. The `Task` model explicitly declares the foreign key in the `belongsTo` relationship to match.

**Reference year comes from the meeting date**: using the creation date caused wrong numbering when a meeting was created in advance for the next year (a January 2027 meeting created in 2026 received `CFG-2026-004` instead of `CFG-2027-001`).

**`MAX()` over `COUNT()` for reference codes**: `COUNT()` would produce duplicate codes after a task is permanently deleted. `MAX()` always reads the highest existing number and increments from there. A counter table was considered and postponed, since fewer than 1000 tasks per year and per type are expected.

**Self reference instead of JSON for duplicates**: the link between a copy and its original is a plain foreign key (`reference_task_id`), not a JSON column. The API exposes it as a `reference_task` object (`id` and `reference_code`) through an Eloquent relation.

**Only the copy points to the original**: the original task only carries `has_duplicate = true`. This keeps the model simple and avoids two way links.

**Duplicate in a transaction**: creating the copy and flagging the original succeed or fail together.

**Business rules stay in the controller, format rules in Form Requests**: Form Requests validate the shape of the data (types, lengths, existence). Rules that depend on the state of the data (archived ORG, same type) return explicit `422` messages from the controller.

**Length limits live in validation only**: `poj_title` is a `VARCHAR(255)` and `poj_description` is a `TEXT` column, so the business limits (100 and 500) can change without a migration.

**`ForceJsonResponse` middleware**: renamed from `JsonResponse` to avoid a naming collision with `Illuminate\Http\JsonResponse`. Explicit return type hints were removed from some controllers for the same reason.

**No soft deletes on tasks**: task deletion is permanent by design. The `MAX()` strategy handles the resulting gaps in reference code sequences.

**No role system for MVP**: Spatie permissions is installed but unused. All authenticated users have equal access.
