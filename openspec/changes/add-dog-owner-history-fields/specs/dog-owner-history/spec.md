# Spec Delta: Dog Owner History

**Change:** add-dog-owner-history-fields
**Capability:** dog-owner-history (neu)

---

## ADDED Requirements

### Requirement: Dog records store owner history fields

The system SHALL allow a `Dog` record to optionally store three owner
history fields: `owner_since` (date), `age_at_acquisition` (free text), and
`origin` (one of `breeder`, `shelter`, `private`, `unknown`). All three
fields SHALL be nullable.

#### Scenario: Admin creates a dog with all three owner history fields
- **WHEN** an admin or trainer submits `POST /api/v1/dogs` with `ownerSince`,
  `ageAtAcquisition`, and `origin` set
- **THEN** the system SHALL persist all three values on the `Dog` record
- **AND** the response SHALL include all three values

#### Scenario: Admin creates a dog without owner history fields
- **WHEN** an admin or trainer submits `POST /api/v1/dogs` without
  `ownerSince`, `ageAtAcquisition`, or `origin`
- **THEN** the system SHALL create the dog successfully
- **AND** the three fields SHALL be `null` in the response

#### Scenario: Admin updates a dog's owner history fields
- **WHEN** an admin or trainer submits `PUT /api/v1/dogs/{dog}` with new
  values for `ownerSince`, `ageAtAcquisition`, or `origin`
- **THEN** the system SHALL update the corresponding fields on the `Dog`
  record
- **AND** fields not included in the request SHALL remain unchanged

#### Scenario: Invalid origin value is rejected
- **WHEN** a request to create or update a dog includes an `origin` value
  that is not one of `breeder`, `shelter`, `private`, `unknown`
- **THEN** the system SHALL respond with HTTP 422
- **AND** SHALL NOT persist the invalid value

#### Scenario: Owner since date cannot be in the future
- **WHEN** a request to create or update a dog includes an `ownerSince`
  value that is later than today
- **THEN** the system SHALL respond with HTTP 422

#### Scenario: Age at acquisition is stored as free text without calculation
- **WHEN** a request includes an `ageAtAcquisition` value such as
  `"ca. 2 Jahre"`
- **THEN** the system SHALL store the value verbatim
- **AND** SHALL NOT derive or overwrite it from `dateOfBirth` or
  `ownerSince`

---

### Requirement: Dog registration requests capture owner history fields

The system SHALL allow a customer submitting a dog registration request
(`POST /api/v1/dog-registration-requests`) to optionally provide the same
three owner history fields (`ownerSince`, `ageAtAcquisition`, `origin`),
validated with the same rules as the admin dog form.

#### Scenario: Customer submits a registration request with owner history fields
- **WHEN** a customer submits `POST /api/v1/dog-registration-requests` with
  `ownerSince`, `ageAtAcquisition`, and `origin` set
- **THEN** the system SHALL persist all three values on the
  `DogRegistrationRequest` record
- **AND** the response SHALL include all three values

#### Scenario: Customer submits a registration request without owner history fields
- **WHEN** a customer submits `POST /api/v1/dog-registration-requests`
  without `ownerSince`, `ageAtAcquisition`, or `origin`
- **THEN** the system SHALL create the request successfully
- **AND** the three fields SHALL be `null` in the response

#### Scenario: Invalid origin value in a registration request is rejected
- **WHEN** a registration request includes an `origin` value that is not
  one of `breeder`, `shelter`, `private`, `unknown`
- **THEN** the system SHALL respond with HTTP 422

---

### Requirement: Approving a registration request carries owner history fields into the dog record

The system SHALL copy `owner_since`, `age_at_acquisition`, and `origin`
from an approved `DogRegistrationRequest` into the newly created `Dog`
record.

#### Scenario: Approval copies populated owner history fields
- **WHEN** an admin approves a pending `DogRegistrationRequest` that has
  `owner_since`, `age_at_acquisition`, and `origin` set
- **THEN** the system SHALL create a `Dog` record with the same three
  values
- **AND** the existing approval behavior (status change to `approved`,
  `reviewed_by`, `reviewed_at`, confirmation email) SHALL remain unchanged

#### Scenario: Approval copies unset owner history fields as null
- **WHEN** an admin approves a pending `DogRegistrationRequest` that has
  `owner_since`, `age_at_acquisition`, and `origin` all `null`
- **THEN** the system SHALL create a `Dog` record with all three fields
  `null`

---

### Requirement: Owner history fields are editable in the admin/trainer dog form

The `DogFormModal.vue` component SHALL provide input fields for
"Beim Halter seit" (date), "Herkunft" (select: Züchter/Tierschutz/Privat/
unbekannt), and "Alter bei Einzug" (free text), for both creating and
editing a dog.

#### Scenario: Fields are pre-filled when editing an existing dog
- **WHEN** an admin or trainer opens the form to edit a dog that has
  `ownerSince`, `ageAtAcquisition`, and `origin` set
- **THEN** the form SHALL display the current values in the corresponding
  inputs

#### Scenario: Fields are empty when creating a new dog
- **WHEN** an admin or trainer opens the form to create a new dog
- **THEN** all three owner history inputs SHALL be empty

#### Scenario: Empty inputs are submitted as null, not empty string
- **WHEN** an admin or trainer submits the form without filling in one or
  more of the three owner history fields
- **THEN** the submitted payload SHALL contain `null` for that field, not
  an empty string

---

### Requirement: Owner history fields are editable in the customer self-service dog registration form

The `CustomerDogRequestModal.vue` component SHALL provide input fields for
"Beim Halter seit" (date), "Herkunft" (select: Züchter/Tierschutz/Privat/
unbekannt), and "Alter bei Einzug" (free text).

#### Scenario: Customer fills in owner history fields when registering a dog
- **WHEN** a customer fills in `ownerSince`, `ageAtAcquisition`, and
  `origin` in the registration form and submits it
- **THEN** the submitted payload SHALL contain all three values

#### Scenario: Form resets owner history fields when reopened
- **WHEN** the registration modal is reopened after a previous submission
- **THEN** all three owner history inputs SHALL be reset to empty
