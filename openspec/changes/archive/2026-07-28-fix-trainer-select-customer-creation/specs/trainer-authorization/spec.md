## ADDED Requirements

### Requirement: Reduced-data trainer-options endpoint accessible to Admin and Trainer roles

The system SHALL provide a reduced-data trainer listing endpoint (`GET /api/v1/trainers/options`) accessible to authenticated users with role `admin` or `trainer`. The response SHALL contain only `id`, `firstName`, `lastName`, and `fullName` per trainer, and SHALL NOT include `email`, `phone`, `mobilePhone`, `street`, `postalCode`, `city`, `country`, `qualifications`, or `specializations`.

This endpoint is additive and does not change the existing admin-only `GET /api/v1/trainers` endpoint (full profile data, see "Admin-only access to Trainer CRUD API"), which remains unchanged.

#### Scenario: Admin can load trainer options
- **WHEN** an authenticated user with role `admin` sends `GET /api/v1/trainers/options`
- **THEN** the system returns HTTP 200
- **AND** each returned trainer contains only `id`, `firstName`, `lastName`, `fullName`

#### Scenario: Trainer can load trainer options
- **WHEN** an authenticated user with role `trainer` sends `GET /api/v1/trainers/options`
- **THEN** the system returns HTTP 200
- **AND** each returned trainer contains only `id`, `firstName`, `lastName`, `fullName`

#### Scenario: Customer role is forbidden on trainer options
- **WHEN** an authenticated user with role `customer` sends `GET /api/v1/trainers/options`
- **THEN** the system returns HTTP 403

#### Scenario: Unauthenticated request is unauthorized on trainer options
- **WHEN** an unauthenticated request sends `GET /api/v1/trainers/options`
- **THEN** the system returns HTTP 401

#### Scenario: Trainer-options response excludes sensitive profile fields
- **WHEN** an authorized user (role `admin` or `trainer`) requests `GET /api/v1/trainers/options`
- **THEN** the response items SHALL NOT include `email`, `phone`, `mobilePhone`, `street`, `postalCode`, `city`, `country`, `qualifications`, or `specializations`
