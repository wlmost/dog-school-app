## Context

The DogFormModal component (`frontend/src/components/DogFormModal.vue`) was created with references to an `error` reactive property and helper functions (`handleApiError`, `showSuccess`) that were never declared or imported. This causes Vue runtime warnings flooding the console and prevents form submission from working.

**Current State:**
- Template includes `<div v-if="error">` for error display
- Script includes `error.value = null` assignment
- Script calls `handleApiError()` and `showSuccess()` functions
- None of these (error ref, functions) are declared/imported
- Existing `errorHandler` utility at `frontend/src/utils/errorHandler.ts` already provides the needed functions

**Constraints:**
- Must maintain existing form structure and API contract
- Must use TypeScript for type safety
- Must integrate with existing toast notification system
- Should follow Vue 3 Composition API patterns used throughout frontend

## Goals / Non-Goals

**Goals:**
- Fix missing error state declaration
- Import existing error handling utilities
- Enable proper error display in form UI
- Ensure success/error feedback reaches users
- Make form submission functional with proper error recovery

**Non-Goals:**
- Changing the form's input fields or validation rules
- Modifying the API endpoints or request/response payloads
- Adding new error handling utilities (they already exist)
- Refactoring other form components
- Changing the overall dialog UX/UI design

## Decisions

### Decision 1: Use Existing ErrorHandler Utility
**Choice:** Import `handleApiError` and `showSuccess` from `@/utils/errorHandler`

**Rationale:**
- Utility already exists and is battle-tested
- Consistent error handling across all forms
- Already integrates with toast store
- Handles all HTTP status codes appropriately
- German-language error messages match application locale

**Alternatives Considered:**
- ❌ Inline error handling logic → Code duplication, inconsistent UX
- ❌ Create new error handler → Redundant, maintenance burden

### Decision 2: Declare Error Ref with Proper Typing
**Choice:** Add `const error = ref<string | null>(null)` in script setup

**Rationale:**
- Template already expects this reactive variable
- TypeScript null union type allows resetting to null
- Follows Vue 3 Composition API ref() pattern
- Matches usage pattern in handleSubmit function

**Alternatives Considered:**
- ❌ Remove error display → Users get no feedback on failures
- ❌ Use different variable name → Template already references "error"

### Decision 3: Capture and Display Validation Errors
**Choice:** Catch errors in try/catch, set error.value for inline display, call handleApiError for toast

**Rationale:**
- Dual feedback: inline error message + toast notification
- handleApiError already returns void (no message string returned)
- Need to manually capture error message for inline display
- Best of both worlds: persistent inline message + transient toast

**Implementation Pattern:**
```typescript
try {
  // API call
} catch (err: any) {
  const errorMessage = err.response?.data?.message || 'Fehler beim Speichern des Hundes'
  error.value = errorMessage
  handleApiError(err, errorMessage)
}
```

### Decision 4: Reset Error State on Modal Operations
**Choice:** Clear `error.value = null` in:
- `closeModal()` - when user cancels
- Beginning of `handleSubmit()` - fresh attempt
- `resetForm()` - form cleanup

**Rationale:**
- Prevents stale error messages on retry
- Clean slate for new operations
- Consistent with form reset pattern

## Risks / Trade-offs

### Risk: ErrorHandler doesn't return error message string
**Mitigation:** Parse error manually before calling handleApiError. Extract `err.response?.data?.message` for inline display.

### Risk: Duplicate error notifications
**Impact:** User sees both inline error (persistent) and toast (transient)  
**Mitigation:** This is intentional and beneficial. Toast provides immediate feedback; inline message persists for reference while user corrects input.

### Trade-off: Simple fix vs. comprehensive form validation
**Decision:** Keep fix minimal - just add missing properties
**Reasoning:** More comprehensive validation (field-level errors, custom rules) would expand scope beyond bug fix. Current validation works; we're just fixing the error display mechanism.

## Migration Plan

**Deployment:**
1. Add imports to DogFormModal.vue
2. Declare error ref
3. Update error handling in handleSubmit
4. Test form submission (success + error cases)
5. Deploy with standard frontend build process

**Rollback:**
- If issues arise, revert the single file change
- No database migrations or API changes involved
- No breaking changes to component interface

**Testing Checklist:**
- ✅ Form opens without console errors
- ✅ Validation errors display inline
- ✅ Toast notifications appear on success/failure
- ✅ Dialog closes on successful save
- ✅ Error clears when retrying submission
- ✅ Customer dropdown loads correctly

## Open Questions

None - implementation path is straightforward.
