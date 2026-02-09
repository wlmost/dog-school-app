## Why

The application is experiencing two critical issues affecting the course management functionality:

1. **Vue Runtime Compilation Error**: The ToastContainer component uses template strings in the `toastIcon` object, requiring Vue's runtime template compiler which is not available in the production build. This causes console warnings and prevents toast notifications from displaying properly.

2. **Empty Database**: The courses table exists but contains no sample data, causing 404 errors when users attempt to view or edit courses. This blocks testing and development of course management features.

These issues prevent proper testing of the course management UI and notification system.

## What Changes

- **Fix ToastContainer Component**: Convert template-based icon definitions to render functions using Vue's `h()` function, eliminating the need for runtime template compilation
- **Seed Database**: Add sample course data (5 courses with various types: group, individual, workshop) to enable testing
- **Improve Error Handling**: Enhance API error handling in CoursesView to gracefully handle empty results and display proper error messages
- **Call Actual API**: Replace placeholder data in CoursesView `onMounted` with actual API call to `/api/v1/courses`

## Capabilities

### New Capabilities

None - this is a bug fix that doesn't introduce new functionality.

### Modified Capabilities

- `frontend-toast-notifications`: Updated implementation to use compile-time safe render functions instead of runtime template compilation
- `course-management`: Fixed to properly load courses from API instead of using hardcoded placeholder data

## Impact

**Frontend Changes:**
- `/frontend/src/components/ToastContainer.vue` - Converted template strings to render functions
- `/frontend/src/views/courses/CoursesView.vue` - Fixed API loading, improved error handling

**Backend Changes:**
- Database seeding - Added 5 sample courses to `dog_school_app.courses` table

**User Experience:**
- Toast notifications now display correctly without console errors
- Course list properly loads from database
- Better error messages when courses don't exist
- Enables testing of course create/edit/delete functionality

**Technical Debt Resolved:**
- Eliminates Vue runtime compilation requirement
- Removes placeholder data pattern in favor of actual API calls
- Improves error handling consistency
