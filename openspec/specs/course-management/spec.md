# Course Management

## Purpose

Defines how courses are displayed, filtered, and managed in the frontend application, including German localization and API integration.

## Requirements

### Requirement: Course list loads from API
The courses view SHALL load course data from the backend API instead of using hardcoded placeholder data.

#### Scenario: Successful course loading
- **WHEN** the courses view is mounted
- **THEN** it SHALL make a GET request to `/api/v1/courses`
- **AND** SHALL display the courses returned by the API
- **AND** SHALL NOT use placeholder or hardcoded course data

#### Scenario: Course loading with error
- **WHEN** the courses view attempts to load courses
- **AND** the API request fails
- **THEN** it SHALL display an error message via toast notification
- **AND** SHALL show an empty state message to the user
- **AND** SHALL NOT crash or show undefined data

#### Scenario: Empty course list
- **WHEN** the API returns an empty course array
- **THEN** the view SHALL display a "no courses found" message
- **AND** SHALL NOT show a loading spinner
- **AND** SHALL NOT display any placeholder courses

### Requirement: Course status badges display in German
Course status badges SHALL display localized German labels for all course status types.

#### Scenario: Planned course badge
- **WHEN** a course has status `planned`
- **THEN** the badge SHALL display "Geplant"
- **AND** SHALL have a blue background color (`bg-blue-100`)
- **AND** SHALL have blue text color (`text-blue-800`)

#### Scenario: Active course badge
- **WHEN** a course has status `active`
- **THEN** the badge SHALL display "Aktiv"
- **AND** SHALL have a green background color (`bg-green-100`)
- **AND** SHALL have green text color (`text-green-800`)

#### Scenario: Completed course badge
- **WHEN** a course has status `completed`
- **THEN** the badge SHALL display "Abgeschlossen"
- **AND** SHALL have a gray background color (`bg-gray-100`)
- **AND** SHALL have gray text color (`text-gray-800`)

#### Scenario: Cancelled course badge
- **WHEN** a course has status `cancelled`
- **THEN** the badge SHALL display "Abgesagt"
- **AND** SHALL have a red background color (`bg-red-100`)
- **AND** SHALL have red text color (`text-red-800`)

#### Scenario: Unknown status fallback
- **WHEN** a course has an unknown status value
- **THEN** the badge SHALL display the raw status value
- **AND** SHALL use gray styling as default

### Requirement: Course type labels display in German
Course type information SHALL be displayed in German throughout the courses view.

#### Scenario: Group training type
- **WHEN** a course has type `group`
- **THEN** it SHALL display as "Gruppentraining"

#### Scenario: Individual training type
- **WHEN** a course has type `individual`
- **THEN** it SHALL display as "Einzeltraining"

#### Scenario: Workshop type
- **WHEN** a course has type `workshop`
- **THEN** it SHALL display as "Workshop"

#### Scenario: Open group type
- **WHEN** a course has type `open_group`
- **THEN** it SHALL display as "Offene Gruppe"

### Requirement: Course status filter uses German labels
The course status filter dropdown SHALL display all filter options in German.

#### Scenario: Filter options in German
- **WHEN** the user opens the status filter dropdown
- **THEN** it SHALL show "Alle Kurse" as the default option
- **AND** SHALL show "Aktive Kurse" for active courses
- **AND** SHALL show "Geplante Kurse" for planned courses
- **AND** SHALL show "Abgeschlossene Kurse" for completed courses
- **AND** SHALL show "Abgesagte Kurse" for cancelled courses

#### Scenario: Filter applies correct API parameter
- **WHEN** the user selects a filter option
- **THEN** the API request SHALL include the correct status parameter value
- **AND** SHALL use English enum values (`active`, `planned`, `completed`, `cancelled`)
- **AND** SHALL NOT send the German label to the API

### Requirement: Course loading state displays feedback
The courses view SHALL provide visual feedback during course data loading.

#### Scenario: Loading spinner during fetch
- **WHEN** courses are being loaded from the API
- **THEN** a loading spinner SHALL be displayed
- **AND** a "Lade Kursdaten..." message SHALL be shown
- **AND** the course grid SHALL be hidden until loading completes

#### Scenario: Content display after loading
- **WHEN** courses finish loading successfully
- **THEN** the loading spinner SHALL be hidden
- **AND** the course grid SHALL be displayed
- **AND** all course cards SHALL render with complete data

### Requirement: Course data displays correct formatting
Course information SHALL be formatted appropriately for German locale and readability.

#### Scenario: Date formatting
- **WHEN** a course has start and end dates
- **THEN** dates SHALL be formatted as DD.MM.YYYY
- **AND** SHALL use German locale (`de-DE`)

#### Scenario: Missing date handling
- **WHEN** a course has no end date
- **THEN** the end date field SHALL display "-"
- **AND** SHALL NOT show "null" or "undefined"

#### Scenario: Participant count display
- **WHEN** a course card is rendered
- **THEN** it SHALL display current participants vs. max participants
- **AND** SHALL show "0 / 8" format when no participants enrolled
- **AND** SHALL calculate percentage for progress bar

### Requirement: Course card displays occupancy visualization
Each course card SHALL show a visual progress bar indicating enrollment level.

#### Scenario: Progress bar calculation
- **WHEN** a course has participants enrolled
- **THEN** the progress bar SHALL show percentage of (current / max) * 100
- **AND** SHALL round to nearest whole number
- **AND** SHALL display the percentage text alongside the bar

#### Scenario: Empty course progress bar
- **WHEN** a course has 0 participants
- **THEN** the progress bar SHALL show 0% fill
- **AND** SHALL still render the empty bar background

#### Scenario: Full course progress bar
- **WHEN** a course has participants equal to max
- **THEN** the progress bar SHALL show 100% fill
- **AND** SHALL fill the entire width of the bar container
