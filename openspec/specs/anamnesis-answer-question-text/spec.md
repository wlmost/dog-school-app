# anamnesis-answer-question-text Specification

## Purpose
TBD - created by archiving change show-anamnese-questions-in-details. Update Purpose after archive.
## Requirements
### Requirement: Answer resource includes denormalized question text

The AnamnesisAnswerResource SHALL include a denormalized `questionText` field that provides direct access to the question's text without requiring frontend to traverse nested object relationships.

#### Scenario: Question text available when relationship loaded
- **WHEN** backend loads answer with question relationship
- **THEN** response includes flat `questionText` field with question's text

#### Scenario: Question text null when relationship not loaded
- **WHEN** backend loads answer without question relationship
- **THEN** response includes `questionText` field with null value

#### Scenario: Question text null when question is null
- **WHEN** answer has null question reference
- **THEN** response includes `questionText` field with null value using null-safe operators

### Requirement: Backend maintains nested question object

The AnamnesisAnswerResource SHALL maintain the existing nested `question` object for backward compatibility while adding the denormalized field.

#### Scenario: Both denormalized and nested fields present
- **WHEN** backend loads answer with question relationship
- **THEN** response includes both `questionText` (flat) and `question` (nested) fields

#### Scenario: Nested question object uses conditional loading
- **WHEN** backend loads answer without question relationship
- **THEN** response omits nested `question` object but includes `questionText` as null

### Requirement: Frontend displays question text in detail modal

The AnamnesisDetailModal SHALL display question text alongside each answer using the denormalized `questionText` field from the answer object.

#### Scenario: Questions visible in anamnesis detail view
- **WHEN** trainer opens anamnesis detail modal
- **THEN** each answer displays its associated question text
- **THEN** question metadata (type, required) is not displayed

#### Scenario: Graceful handling of missing question text
- **WHEN** answer has null questionText
- **THEN** modal displays answer without crashing
- **THEN** no question text shown for that answer

