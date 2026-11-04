# ORG-Manager Backend API

REST API for managing decision-making body meetings (CFG and COMITE). Built with Laravel 13, PHP 8.3, and MySQL. Designed API-first for a mobile frontend (smartphone and tablet).

---

## Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 13 |
| Language | PHP 8.3 |
| Database | MySQL |
| Authentication | Laravel Sanctum (token-based, 480 minutes lifetime) |
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

### Task lifecycle

```
create ──> TODO <──> DONE        (status toggle or forced value)
             │
             ├── edit            (title, description, service)
             ├── move            (same type, any date)
             ├── duplicate       (other type, once)
             └── delete          (permanent)

All of the above are refused once the ORG is archived.
An ORG can only be archived when no task is TODO.
```

---

## Business Rules

| Rule | Description |
|---|---|
| Reference year | The year of a reference code is the **year of the meeting** (`date_meeting`), never the creation date |
| Yearly reset | The number restarts at `001` for each type and each year |
| Immutable reference | The reference code never changes (move, edit and duplicate included) |
| Move | Same ORG type only (CFG to CFG, COMITE to COMITE), **any date** (past or future), neither ORG can be archived |
| Duplicate | Different ORG type only, neither ORG can be archived, one duplicate per task |
| Archiving | Manual only. An ORG with at least one `TODO` task cannot be archived |
| Archived ORG | Frozen: no task can enter or leave it, be edited, be deleted, or have its status changed |
| Edit and delete | Allowed unless the ORG is archived. Deletion is permanent |
| Delete a copy | Sets `has_duplicate = false` on its original, so it can be duplicated again |
| Delete an original | The copy is kept. Its `reference_task_id` becomes `null` and `has_duplicate` stays `true` |
| Bulk actions | `bulk-done` and `bulk-move` check every task first. Nothing is modified if one task fails |
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
| name | VARCHAR | Unique department name, enforced by a unique index at database level |
| timestamps | | |

### `org_instances`
| Column | Type | Notes |
|---|---|---|
| id | PK | |
| type | ENUM | `CFG` or `COMITE` |
| recurrence_type | ENUM | `HEBDO` (weekly) or `OCCASIONNEL` |
| date_meeting | DATE | Unique per type (one meeting per body and per day). No weekday is enforced |
| is_archived | BOOLEAN | Default `false`. Archiving is manual only |
| timestamps | | |

### `tasks`
| Column | Type | Notes |
|---|---|---|
| id | PK | |
| organization_id | FK | References `org_instances.id`, cascade delete |
| service_id | FK | References `services.id`, restrict on delete (a service with tasks cannot be deleted) |
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

The reference code is immutable. Moving or editing a task preserves it. A duplicated task receives a **new** code based on the target type and year.

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

The `DatabaseSeeder` creates a test user (`test@example.com`, password `password`) **only in the `local` environment**. Never seed this user in production.

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
php artisan test --filter TaskBulkTest
php artisan test --filter TaskDuplicateTest
```

Current coverage: **127 tests, all passing**.

---

## API Reference

All endpoints except `/api/login` require a Sanctum token in the `Authorization` header:

```
Authorization: Bearer {token}
```

Every response is JSON (the `ForceJsonResponse` middleware forces `Accept: application/json`).

### Common errors

| Status | Meaning | Body |
|---|---|---|
| 401 | Missing, invalid or expired token | `{ "message": "Unauthenticated. Please provide a valid token." }` |
| 404 | Unknown route or resource | `{ "message": "Resource not found." }` |
| 405 | HTTP method not allowed | `{ "message": "Method not allowed." }` |
| 422 | Validation error | `{ "message": "...", "errors": { "field": ["..."] } }` |
| 422 | Business rule refused (archived ORG, wrong type, ...) | `{ "message": "..." }` |
| 429 | Too many login attempts | Laravel default throttle response |

Route parameters named `id` only accept digits, so `/api/orgs/abc/tasks` returns `404`.

---

### Authentication

#### `POST /api/login`

Limited to **5 attempts per minute**. The 6th attempt returns `429`, even with the right password.

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

// Response 422 (wrong credentials)
{
  "message": "The provided credentials are incorrect.",
  "errors": { "email": ["The provided credentials are incorrect."] }
}
```

Tokens expire after **480 minutes** (8 hours). An expired token returns `401`. Expired tokens are pruned daily by the scheduler (`sanctum:prune-expired`).

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

The name must be unique (`422` otherwise).

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

Returns all non-archived instances for a given type, ordered by `date_meeting` ascending. The `type` parameter is required (`CFG` or `COMITE`).

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

Any date is accepted (the Monday rule for weekly meetings is a frontend convention, not enforced). Returns `422` if a meeting of the same type already exists on this date.

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

Sets `is_archived` to `true`. Archiving is irreversible: there is no endpoint to unarchive.

Returns `422` in these cases:

| Case | Message |
|---|---|
| Already archived | `This ORG is already archived.` |
| At least one task is still `TODO` | `Cannot archive an ORG that still has 3 pending tasks.` (`1 pending task` in the singular) |

Before archiving, the frontend can clear the pending tasks with `PATCH /api/tasks/{id}/status`, `PATCH /api/tasks/bulk-done` or `PATCH /api/tasks/bulk-move`.

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

Returns `422` with `Cannot add a task to an archived ORG.` if the ORG is archived.

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

#### `PUT /api/tasks/{id}`

Edits the title, the description and the service of a task. The `reference_code`, the `status` and the ORG are never changed by this endpoint, even if they are sent in the body.

Returns `422` with `Cannot update a task in an archived ORG.` if the ORG is archived.

| Field | Rules |
|---|---|
| service_id | required, integer, must exist |
| poj_title | required, string, max 100 characters |
| poj_description | optional, string, max 500 characters |

```json
// Request
{
  "service_id": 2,
  "poj_title": "Corrected title",
  "poj_description": "Corrected description"
}

// Response 200
{
  "data": { "id": 1, "poj_title": "Corrected title", "reference_code": "CFG-2026-001", ... }
}
```

#### `DELETE /api/tasks/{id}`

Permanently deletes a task. Returns `204` with no body.

Returns `422` with `Cannot delete a task from an archived ORG.` if the ORG is archived.

- Deleting a **copy** sets `has_duplicate = false` on its original, in the same transaction.
- Deleting an **original** keeps the copy untouched.

#### `PATCH /api/tasks/{id}/status`

Changes the task status. Returns `422` with `Cannot change the status of a task in an archived ORG.` if the ORG is archived.

| Body | Behavior |
|---|---|
| none | Toggles between `TODO` and `DONE` |
| `{ "status": "DONE" }` or `{ "status": "TODO" }` | Applies the given value |

Sending a forced status twice gives the same result, which protects against double taps and network retries. An invalid value returns `422`.

```json
// Request (optional)
{ "status": "DONE" }

// Response 200
{
  "data": { "id": 1, "status": "DONE", ... }
}
```

#### `PATCH /api/tasks/bulk-done`

Marks several tasks as `DONE` at once. Tasks that are already `DONE` stay `DONE` (no toggle). Every task is checked before anything is written: if one task belongs to an archived ORG, nothing is modified.

| Field | Rules |
|---|---|
| task_ids | required, array, 1 to 100 items |
| task_ids.* | integer, distinct, must exist |

```json
// Request
{ "task_ids": [1, 2, 3] }

// Response 200
{
  "data": [ { "id": 1, "status": "DONE", ... }, { "id": 2, "status": "DONE", ... } ]
}
```

Returns `422` with `Cannot change the status of a task in an archived ORG.` if one task is frozen.

#### `PATCH /api/tasks/{id}/move`

Moves a task to another OrgInstance of the **same type**. Any date is accepted, including an earlier one. The `reference_code` and `status` are preserved.

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

#### `PATCH /api/tasks/bulk-move`

Moves several tasks to another OrgInstance of the **same type**, on any date. Reference codes and statuses are preserved. Every task is checked before anything is written, so one invalid task cancels the whole operation.

| Field | Rules |
|---|---|
| task_ids | required, array, 1 to 100 items |
| task_ids.* | integer, distinct, must exist |
| org_instance_id | required, integer, must exist |

Returns `422` in these cases:

| Case | Message |
|---|---|
| Target ORG is archived | `Cannot move a task to an archived ORG.` |
| A task is already in the target | `The task is already in this ORG.` |
| A task comes from an archived ORG | `Cannot move a task from an archived ORG.` |
| A task has a different ORG type | `Cannot move a task to a different ORG type.` |

```json
// Request
{ "task_ids": [1, 2], "org_instance_id": 5 }

// Response 200
{
  "data": [ { "id": 1, "organization_id": 5, ... }, { "id": 2, "organization_id": 5, ... } ]
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

If the copy is deleted later, the original can be duplicated again.

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

Returns tasks from archived OrgInstances. All filters are optional and cumulative. Results are cursor-paginated at 25 per page and ordered by `reference_code`. Each task includes `service`, `org_instance` and `reference_task`.

| Parameter | Type | Description |
|---|---|---|
| type | string | `CFG` or `COMITE` (`422` otherwise) |
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

## Security

| Measure | Detail |
|---|---|
| Token authentication | Every route except `/api/login` is behind `auth:sanctum` |
| Token lifetime | 480 minutes, expired tokens are rejected and pruned daily |
| Login throttle | 5 attempts per minute |
| Hidden root | `GET /` returns `404`, no page is exposed |
| Test user | Seeded only in the `local` environment |
| Mass assignment | Edit endpoints accept only whitelisted fields. `reference_code`, `status` and `organization_id` cannot be forged through `PUT /api/tasks/{id}` |
| Transport | HTTPS is required in production, no data is stored in clear on the device other than the token |

---

## Project Structure

```
app/
  Exceptions/
    ArchivedOrgException.php    thrown when an archived ORG is modified, rendered as 422
  Http/
    Controllers/Api/
      AuthController.php        login, logout, user
      ServiceController.php     index, store
      OrgInstanceController.php active, store, archive
      TaskController.php        index, store, updateStatus, move, duplicate,
                                update, destroy, bulkDone, bulkMove
      ArchiveController.php     index (with filters and cursor pagination)
    Requests/
      StoreTaskRequest.php          validation for task creation
      UpdateTaskRequest.php         validation for task edition (title, description, service)
      UpdateTaskStatusRequest.php   optional status value
      MoveTaskRequest.php           validation for move
      DuplicateTaskRequest.php      validation for duplicate
      BulkDoneTasksRequest.php      validation for bulk-done
      BulkMoveTasksRequest.php      validation for bulk-move
    Middleware/
      ForceJsonResponse.php     forces Accept: application/json on all API requests
  Models/
    User.php
    Service.php
    OrgInstance.php             includes ensureNotArchived()
    Task.php
  Services/
    ReferenceCodeService.php    MAX() based reference code generation
routes/
  api.php
tests/
  Feature/
    AuthTest.php
    SecurityTest.php
    ServiceTest.php
    OrgInstanceTest.php
    TaskTest.php
    TaskBulkTest.php
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

**Duplicate and delete in a transaction**: creating the copy and flagging the original succeed or fail together. Deleting a copy and freeing its original follow the same rule.

**Archiving is guarded and irreversible**: an ORG cannot be archived while `TODO` tasks remain. Three tools let the user clean up first: the status endpoint, `bulk-done` and `bulk-move`.

**Bulk actions are all or nothing**: the controller checks every task first, then writes with a single `UPDATE`. A failure never leaves a half modified state.

**Move accepts any date**: a task can be completed late because of a collaborator or another blocker, so moving to an earlier meeting is a valid case. Only archived ORGs block a move.

**Idempotent status**: the optional `status` field lets the frontend send the wanted value, so a double tap or a network retry gives the same result. Without it, the endpoint keeps toggling.

**Permanent delete, no soft deletes**: `reference_code` is unique and `MAX()` must never miss an existing code, so soft deleted rows would break the numbering. Deleting a copy frees its original.

**No Monday rule for weekly ORGs**: the idea is kept for later, but the MVP lets the user choose any date. The `unique(type, date_meeting)` constraint already prevents duplicates.

**Business rules stay in the controller, format rules in Form Requests**: Form Requests validate the shape of the data (types, lengths, existence). Rules that depend on the state of the data (archived ORG, same type) return explicit `422` messages from the controller or from `ensureNotArchived()`.

**Length limits live in validation only**: `poj_title` is a `VARCHAR(255)` and `poj_description` is a `TEXT` column, so the business limits (100 and 500) can change without a migration.

**`ForceJsonResponse` middleware**: renamed from `JsonResponse` to avoid a naming collision with `Illuminate\Http\JsonResponse`. Explicit return type hints were removed from some controllers for the same reason.

**No role system for MVP**: Spatie permissions is installed but unused. All authenticated users have equal access.
