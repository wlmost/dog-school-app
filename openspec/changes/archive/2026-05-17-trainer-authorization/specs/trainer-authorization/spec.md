# Spec: trainer-authorization

## ADDED Requirements

### Requirement: Admin-only access to Trainer CRUD API

The system SHALL restrict all trainer CRUD endpoints (`GET`, `POST`, `PUT`, `PATCH`, `DELETE` on `/api/v1/trainers` and `/api/v1/trainers/{id}`) to authenticated users with the `admin` role.

#### Scenario: Admin can list trainers
- **WHEN** an authenticated user with role `admin` sends `GET /api/v1/trainers`
- **THEN** the system returns HTTP 200

#### Scenario: Admin can create a trainer
- **WHEN** an authenticated user with role `admin` sends `POST /api/v1/trainers` with valid data
- **THEN** the system returns HTTP 201

#### Scenario: Admin can view a trainer
- **WHEN** an authenticated user with role `admin` sends `GET /api/v1/trainers/{id}`
- **THEN** the system returns HTTP 200

#### Scenario: Admin can update a trainer
- **WHEN** an authenticated user with role `admin` sends `PUT /api/v1/trainers/{id}` with valid data
- **THEN** the system returns HTTP 200

#### Scenario: Admin can delete a trainer
- **WHEN** an authenticated user with role `admin` sends `DELETE /api/v1/trainers/{id}`
- **THEN** the system returns HTTP 204

---

### Requirement: Non-admin authenticated users receive 403

The system SHALL return HTTP 403 Forbidden for any user with role `trainer` or `customer` attempting to access any trainer CRUD endpoint.

#### Scenario: Trainer role is forbidden on list
- **WHEN** an authenticated user with role `trainer` sends `GET /api/v1/trainers`
- **THEN** the system returns HTTP 403

#### Scenario: Customer role is forbidden on list
- **WHEN** an authenticated user with role `customer` sends `GET /api/v1/trainers`
- **THEN** the system returns HTTP 403

#### Scenario: Trainer role is forbidden on create
- **WHEN** an authenticated user with role `trainer` sends `POST /api/v1/trainers`
- **THEN** the system returns HTTP 403

#### Scenario: Customer role is forbidden on create
- **WHEN** an authenticated user with role `customer` sends `POST /api/v1/trainers`
- **THEN** the system returns HTTP 403

#### Scenario: Trainer role is forbidden on update
- **WHEN** an authenticated user with role `trainer` sends `PUT /api/v1/trainers/{id}`
- **THEN** the system returns HTTP 403

#### Scenario: Customer role is forbidden on update
- **WHEN** an authenticated user with role `customer` sends `PUT /api/v1/trainers/{id}`
- **THEN** the system returns HTTP 403

#### Scenario: Trainer role is forbidden on delete
- **WHEN** an authenticated user with role `trainer` sends `DELETE /api/v1/trainers/{id}`
- **THEN** the system returns HTTP 403

#### Scenario: Customer role is forbidden on delete
- **WHEN** an authenticated user with role `customer` sends `DELETE /api/v1/trainers/{id}`
- **THEN** the system returns HTTP 403

---

### Requirement: Unauthenticated requests receive 401

The system SHALL return HTTP 401 Unauthorized for unauthenticated requests to any trainer CRUD endpoint.

#### Scenario: Unauthenticated list request
- **WHEN** an unauthenticated request sends `GET /api/v1/trainers`
- **THEN** the system returns HTTP 401

#### Scenario: Unauthenticated create request
- **WHEN** an unauthenticated request sends `POST /api/v1/trainers`
- **THEN** the system returns HTTP 401

#### Scenario: Unauthenticated delete request
- **WHEN** an unauthenticated request sends `DELETE /api/v1/trainers/{id}`
- **THEN** the system returns HTTP 401
