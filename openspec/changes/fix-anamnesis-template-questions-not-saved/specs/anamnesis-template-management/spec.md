# Spec Delta: Anamnesis Template Management

**Change:** fix-anamnesis-template-questions-not-saved
**Capability:** anamnesis-template-management (neu)

---

## ADDED Requirements

### Requirement: Template listings and detail views expose a questions count

The system SHALL include a `questionsCount` field in every
`AnamnesisTemplateResource` representation (list and detail views), so that
clients can display the number of questions belonging to a template without
requiring a separate request.

#### Scenario: List view reflects the true number of questions
- **WHEN** a trainer requests `GET /api/v1/anamnesis-templates`
- **THEN** each template in the `data` array SHALL include a `questionsCount`
  field
- **AND** the value SHALL equal the number of `AnamnesisQuestion` rows
  currently persisted for that template

#### Scenario: Detail view reflects the true number of questions
- **WHEN** a trainer requests `GET /api/v1/anamnesis-templates/{id}`
- **THEN** the response SHALL include a `questionsCount` field equal to the
  number of persisted questions for that template

#### Scenario: Create response reflects the true number of questions
- **WHEN** a trainer creates a template via
  `POST /api/v1/anamnesis-templates` including a `questions` array
- **THEN** the response SHALL include a `questionsCount` field equal to the
  number of questions that were actually persisted

---

### Requirement: Updating a template synchronizes its questions

The system SHALL allow a trainer to add, modify, and remove questions of an
existing anamnesis template via `PUT /api/v1/anamnesis-templates/{id}`,
using an optional `id` per question to distinguish existing questions from
new ones.

#### Scenario: New questions are created on update
- **WHEN** a trainer sends a `questions` array containing entries without an
  `id`
- **THEN** the system SHALL create a new `AnamnesisQuestion` for each such
  entry

#### Scenario: Existing questions are updated in place by id
- **WHEN** a trainer sends a `questions` array containing an entry with an
  `id` that belongs to the template being updated
- **THEN** the system SHALL update the existing `AnamnesisQuestion` row
  identified by that `id`
- **AND** SHALL NOT create a duplicate row

#### Scenario: Questions omitted from the payload are removed
- **WHEN** a trainer sends a `questions` array that omits the `id` of a
  question that currently exists on the template
- **AND** that question has no `AnamnesisAnswer` records
- **THEN** the system SHALL delete that question

#### Scenario: Questions with existing answers are protected from deletion
- **WHEN** a trainer sends a `questions` array that omits the `id` of a
  question that currently exists on the template
- **AND** that question has at least one `AnamnesisAnswer` record
- **THEN** the system SHALL NOT delete that question
- **AND** SHALL NOT delete its associated answers
- **AND** the question SHALL remain unchanged in the response

#### Scenario: A question id from a different template is rejected
- **WHEN** a trainer sends a `questions` array containing an `id` that
  belongs to a question of a different template
- **THEN** the system SHALL respond with HTTP 422
- **AND** SHALL NOT modify any question of either template

#### Scenario: Omitting the questions key leaves existing questions untouched
- **WHEN** a trainer sends an update request that does not include a
  `questions` key at all
- **THEN** the system SHALL leave all existing questions of the template
  unchanged

#### Scenario: Sending an empty questions array removes all unanswered questions
- **WHEN** a trainer sends an update request with `questions: []`
- **THEN** the system SHALL delete every existing question that has no
  `AnamnesisAnswer` records
- **AND** SHALL leave every question that has at least one `AnamnesisAnswer`
  record unchanged

---

### Requirement: Editing a template loads its full question set before display

The frontend SHALL load the full detail representation of a template
(including its `questions`) before opening the template editor, rather than
reusing the list-view representation of that template.

#### Scenario: Opening the editor for an existing template shows its actual questions
- **WHEN** a trainer clicks "Bearbeiten" for a template in the template list
- **THEN** the frontend SHALL fetch the template detail (including
  `questions`) via `GET /api/v1/anamnesis-templates/{id}`
- **AND** the editor SHALL display the questions returned by that request

#### Scenario: Opening the editor for a new template does not trigger a fetch
- **WHEN** a trainer clicks "Neue Vorlage"
- **THEN** the frontend SHALL open the editor with an empty form
- **AND** SHALL NOT perform a template detail fetch
