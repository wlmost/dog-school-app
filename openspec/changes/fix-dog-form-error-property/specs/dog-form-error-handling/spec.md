## ADDED Requirements

### Requirement: Form displays error state property
The DogFormModal component SHALL declare an error reactive property to track and display validation and API errors.

#### Scenario: Error property is declared
- **WHEN** the component initializes
- **THEN** an error ref SHALL be declared with type `Ref<string | null>`
- **AND** the initial value SHALL be `null`

#### Scenario: Error property is accessible in template
- **WHEN** the template renders
- **THEN** the error property SHALL be available for conditional rendering
- **AND** SHALL NOT cause Vue runtime warnings about undefined properties

### Requirement: Form imports error handling utilities
The DogFormModal component SHALL import error handling functions from the application's error handler utility.

#### Scenario: Error handler functions are imported
- **WHEN** the component script is evaluated
- **THEN** `handleApiError` function SHALL be imported from `@/utils/errorHandler`
- **AND** `showSuccess` function SHALL be imported from `@/utils/errorHandler`

#### Scenario: Imported functions are callable
- **WHEN** error handling or success feedback is needed
- **THEN** imported functions SHALL be invocable without runtime errors
- **AND** SHALL integrate with the toast notification system

### Requirement: Form displays inline error messages
The DogFormModal component SHALL display error messages inline within the form when validation or submission fails.

#### Scenario: Error message displays when error is set
- **WHEN** the error property contains a string value
- **THEN** a red error box SHALL be visible in the form
- **AND** SHALL display the error message text
- **AND** SHALL have appropriate styling (red background, red text)

#### Scenario: Error message hidden when no error
- **WHEN** the error property is `null`
- **THEN** the error box SHALL NOT be rendered
- **AND** SHALL NOT occupy space in the form layout

### Requirement: Form captures API errors during submission
The DogFormModal component SHALL capture and display errors when dog creation or update API calls fail.

#### Scenario: API error is captured
- **WHEN** an API call fails in handleSubmit
- **THEN** the error SHALL be caught in a try/catch block
- **AND** the error property SHALL be set to the error message
- **AND** handleApiError util SHALL be called with the error

#### Scenario: Error message extracted from API response
- **WHEN** API returns an error response
- **THEN** the error message SHALL be extracted from `error.response?.data?.message`
- **AND** SHALL fallback to a default message if no message provided
- **AND** the extracted message SHALL be displayed both inline and via toast

#### Scenario: Loading state ends on error
- **WHEN** an API error occurs
- **THEN** the loading state SHALL be set to false
- **AND** the submit button SHALL become enabled again
- **AND** the user SHALL be able to retry submission

### Requirement: Form clears error state at appropriate times
The DogFormModal component SHALL reset error messages when the user takes actions that should clear previous errors.

#### Scenario: Error cleared before new submission
- **WHEN** handleSubmit function is called
- **AND** before the API request is made
- **THEN** the error property SHALL be set to `null`
- **AND** any previous error message SHALL be removed from display

#### Scenario: Error cleared when modal closes
- **WHEN** closeModal function is called
- **THEN** the error property SHALL be set to `null`
- **AND** form state SHALL be reset

#### Scenario: Error cleared when form resets
- **WHEN** resetForm function is called
- **THEN** the error property SHALL be set to `null`
- **AND** SHALL ensure clean state for next form usage

### Requirement: Form displays success feedback on save
The DogFormModal component SHALL display success notifications when dog creation or update succeeds.

#### Scenario: Success toast on dog creation
- **WHEN** a new dog is successfully created via API
- **THEN** showSuccess function SHALL be called
- **AND** SHALL display title "Hund erstellt"
- **AND** SHALL display message with dog's name
- **AND** SHALL show for standard toast duration

#### Scenario: Success toast on dog update
- **WHEN** an existing dog is successfully updated via API
- **THEN** showSuccess function SHALL be called
- **AND** SHALL display title "Hund aktualisiert"
- **AND** SHALL display message with dog's name

#### Scenario: Modal closes after successful save
- **WHEN** API save succeeds and success toast is shown
- **THEN** the modal SHALL close
- **AND** the parent component SHALL be notified via 'saved' event
- **AND** error state SHALL be cleared

### Requirement: Form handles multiple error scenarios
The DogFormModal component SHALL properly handle different types of errors with appropriate user feedback.

#### Scenario: Validation error displays specific message
- **WHEN** API returns 422 validation error
- **THEN** handleApiError SHALL extract first validation message
- **AND** error SHALL be displayed inline in the form
- **AND** toast notification SHALL show validation-specific message

#### Scenario: Network error displays connection message
- **WHEN** API call fails due to network error
- **THEN** handleApiError SHALL display network error toast
- **AND** inline error SHALL show connection-related message

#### Scenario: Server error displays friendly message
- **WHEN** API returns 500-series error
- **THEN** handleApiError SHALL display server error toast
- **AND** SHALL NOT expose technical stack traces to user
- **AND** SHALL suggest trying again later

### Requirement: Form maintains type safety with error handling
The DogFormModal component SHALL use proper TypeScript types for error handling to prevent runtime type errors.

#### Scenario: Error property has correct type
- **WHEN** error property is declared
- **THEN** it SHALL have type `Ref<string | null>`
- **AND** assignments SHALL be type-checked at compile time

#### Scenario: Catch block handles unknown error type
- **WHEN** errors are caught in try/catch blocks
- **THEN** error parameter SHALL be typed as `any` or `unknown`
- **AND** error properties SHALL be safely accessed with optional chaining
- **AND** SHALL NOT cause TypeScript compilation errors
