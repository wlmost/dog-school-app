# Trainer Assignment Forms

## Purpose

Defines the trainer-assignment select box behavior in the customer-creation and course-creation forms: successful loading for all authorized roles, sensible preselection for self-service trainer use, and visible error feedback on load failure.

## Requirements

### Requirement: Trainer assignment select loads successfully for Admin and Trainer roles

The customer-creation form and the course-creation form SHALL successfully load the list of trainers for the trainer-assignment select box, regardless of whether the logged-in user has role `admin` or `trainer`.

#### Scenario: Trainer role sees trainer options when creating a customer
- **WHEN** a logged-in user with role `trainer` opens the customer-creation form
- **THEN** the trainer select box SHALL be populated with the available trainers
- **AND** no error SHALL be silently swallowed

#### Scenario: Trainer role sees trainer options when creating a course
- **WHEN** a logged-in user with role `trainer` opens the course-creation form
- **THEN** the trainer select box SHALL be populated with the available trainers
- **AND** no error SHALL be silently swallowed

### Requirement: Trainer is preselected for self-created customers but remains changeable

The customer-creation form SHALL default the trainer select box to the logged-in trainer when a logged-in trainer creates a new customer, but the trainer MAY change the selection to any other trainer before submitting, and the select box SHALL NOT be disabled.

#### Scenario: Preselection defaults to the current trainer
- **WHEN** a logged-in user with role `trainer` opens the customer-creation form for a new customer
- **THEN** the trainer select box SHALL default to the logged-in trainer's id
- **AND** the select box SHALL remain enabled and allow choosing a different trainer

### Requirement: Trainer-list load failures are surfaced to the user

The customer-creation form and the course-creation form SHALL display a user-visible error notification if loading the trainer list fails (e.g. authorization or network error), instead of only logging the error to the browser console.

#### Scenario: Load failure shows a visible error in the customer form
- **WHEN** the trainer-options request fails while the customer-creation form is open
- **THEN** a user-visible error notification SHALL be shown

#### Scenario: Load failure shows a visible error in the course form
- **WHEN** the trainer-options request fails while the course-creation form is open
- **THEN** a user-visible error notification SHALL be shown
