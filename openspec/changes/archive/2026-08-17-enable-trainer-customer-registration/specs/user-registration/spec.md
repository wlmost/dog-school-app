## ADDED Requirements

### Requirement: Endpoint access restricted to Admin and Trainer roles

The system SHALL allow only authenticated users with role `admin` or
`trainer` to call `POST /api/v1/auth/register`. Authenticated users with
role `customer` and unauthenticated requests SHALL be rejected.

#### Scenario: Admin can access the registration endpoint
- **WHEN** an authenticated user with role `admin` sends `POST /api/v1/auth/register` with valid data and `role: 'customer'`
- **THEN** the system returns HTTP 201

#### Scenario: Trainer can access the registration endpoint
- **WHEN** an authenticated user with role `trainer` sends `POST /api/v1/auth/register` with valid data and `role: 'customer'`
- **THEN** the system returns HTTP 201

#### Scenario: Customer is forbidden from the registration endpoint
- **WHEN** an authenticated user with role `customer` sends `POST /api/v1/auth/register`
- **THEN** the system returns HTTP 403

#### Scenario: Unauthenticated request is unauthorized
- **WHEN** an unauthenticated request sends `POST /api/v1/auth/register`
- **THEN** the system returns HTTP 401

### Requirement: Role assignment for Trainer-initiated registrations is restricted to customer

The system SHALL restrict the `role` value a Trainer-initiated
registration may set to `customer` only, regardless of the value
submitted in the request body. This restriction SHALL be derived
exclusively from the authenticated caller's own role (server-side auth
state), not from any client-supplied field, so it cannot be bypassed by
a manipulated request. Admin-initiated registrations remain unrestricted
and may set `role` to `admin`, `trainer`, or `customer`.

#### Scenario: Trainer registers a customer
- **WHEN** an authenticated user with role `trainer` sends `POST /api/v1/auth/register` with `role: 'customer'`
- **THEN** the system returns HTTP 201
- **AND** the created user has `role: 'customer'`

#### Scenario: Trainer attempts to register an admin
- **WHEN** an authenticated user with role `trainer` sends `POST /api/v1/auth/register` with `role: 'admin'`
- **THEN** the system returns HTTP 422 with a validation error on the `role` field
- **AND** no user is created

#### Scenario: Trainer attempts to register another trainer
- **WHEN** an authenticated user with role `trainer` sends `POST /api/v1/auth/register` with `role: 'trainer'`
- **THEN** the system returns HTTP 422 with a validation error on the `role` field
- **AND** no user is created

#### Scenario: Admin retains unrestricted role assignment
- **WHEN** an authenticated user with role `admin` sends `POST /api/v1/auth/register` with `role: 'admin'`, `role: 'trainer'`, or `role: 'customer'`
- **THEN** the system returns HTTP 201
- **AND** the created user has the requested role
