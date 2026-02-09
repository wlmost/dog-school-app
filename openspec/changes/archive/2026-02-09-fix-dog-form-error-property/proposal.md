## Why

The DogFormModal component references an `error` reactive property in both the template (`v-if="error"`) and script (`error.value = null`) that was never declared, causing Vue runtime errors and preventing the form from functioning. When trainers attempt to add or edit dogs, the form submission fails silently, the dialog doesn't close, and no data is saved, making dog management completely unusable.

## What Changes

- **Add missing error state**: Declare `const error = ref<string | null>(null)` in the component's reactive data
- **Add missing helper functions**: Import or implement `showSuccess()` and `handleApiError()` utilities that are referenced but not available
- **Fix error handling flow**: Ensure error messages are captured and displayed when API calls fail
- **Add proper error reset**: Clear error state when dialog closes or opens fresh
- **Improve user feedback**: Display success toasts and error messages appropriately

## Capabilities

### New Capabilities
- `dog-form-error-handling`: Error handling and validation feedback for dog management form

### Modified Capabilities
None - this is a bug fix that adds missing functionality, not a requirement change to existing specs.

## Impact

**Affected Components:**
- `frontend/src/components/DogFormModal.vue` - Add missing reactive properties and error handlers

**Affected Views:**
- `frontend/src/views/dogs/DogsView.vue` - Indirectly benefits from working form submission

**User Impact:**
- Trainers can successfully create and edit dog records
- Form validation errors are visible to users
- Success/failure feedback is properly communicated
- Dialog closes correctly after successful save

**Dependencies:**
- May need `errorHandler` utility (check if exists in `@/utils/errorHandler.ts`)
- May need toast notification store/composable integration
