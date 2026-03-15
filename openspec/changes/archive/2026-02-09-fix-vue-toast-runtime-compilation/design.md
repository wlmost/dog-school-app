## Context

The application currently has two distinct issues affecting the course management functionality:

**Vue Runtime Compilation Issue:**
The `ToastContainer.vue` component uses inline template strings within a JavaScript object to define SVG icons for different toast types (success, error, warning, info). Vue's default production build (ESM bundler) does not include the runtime template compiler, causing console warnings and preventing proper rendering of toast icons.

**Empty Database State:**
The courses table was created via migrations but contains no sample data. This prevents testing of course CRUD operations and causes 404 errors when attempting to load or edit courses. The `CoursesView.vue` component was using hardcoded placeholder data in `onMounted`, bypassing the actual API.

**Current Architecture:**
- Frontend: Vue 3 with Composition API, Vite bundler
- Backend: Laravel API with PostgreSQL database
- Toast system: Pinia store + component with dynamic icons
- Build mode: Production build uses runtime-core only (no template compiler)

## Goals / Non-Goals

**Goals:**
- Eliminate Vue runtime compilation requirement by converting template strings to render functions
- Populate database with realistic sample course data for development/testing
- Fix CoursesView to load courses from actual API instead of placeholder data
- Improve error handling for missing or failed course data
- Localize all course status badges and labels to German
- Maintain existing toast notification functionality and appearance

**Non-Goals:**
- Redesigning the toast notification system architecture
- Changing the build configuration to include runtime compiler (adds unnecessary bundle size)
- Creating a seeder class (direct SQL insert is sufficient for small demo data)
- Migrating to a different notification library
- Internationalizing the entire application (only course status labels for now)

## Decisions

### Decision 1: Use Vue h() Render Functions Instead of Template Strings

**Rationale:**
Converting template strings to `h()` render functions allows icon components to be defined at compile-time, eliminating the need for Vue's runtime template compiler.

**Alternatives Considered:**
1. **Enable runtime compiler in Vite config** - Rejected because it increases bundle size by ~30KB and goes against Vue 3 best practices for production builds
2. **Create separate .vue SFC files for each icon** - Rejected due to file proliferation (4 simple icons don't warrant 4 files)
3. **Use inline SVG in template with v-html** - Rejected due to security concerns and less type safety

**Implementation:**
```typescript
// Before (requires runtime compilation)
const toastIcon = {
  success: {
    template: `<svg>...</svg>`
  }
}

// After (compile-time safe)
import { h, type Component } from 'vue'

const toastIcon: Record<string, Component> = {
  success: {
    render: () => h('svg', { class: 'w-5 h-5', ... }, [
      h('path', { 'd': '...' })
    ])
  }
}
```

**Benefits:**
- No bundle size increase
- Type-safe component definitions
- Follows Vue 3 best practices
- Works in all build modes

### Decision 2: Direct SQL Insert for Sample Data

**Rationale:**
For a small set of demo courses (5-7 records), executing direct SQL INSERT statements is simpler and more transparent than creating a Laravel seeder class.

**Alternatives Considered:**
1. **Create DatabaseSeeder class** - Rejected as overkill for 7 simple records; would require more boilerplate
2. **Use Laravel factories** - Rejected because we want specific, realistic course data, not random generated data
3. **Leave database empty and use mock data** - Rejected because it doesn't test the full API integration

**Implementation:**
- Execute SQL directly via `docker exec` and `psql`
- Insert 7 courses covering all status types: planned, active, completed, cancelled
- Include variety of course types: group, individual, workshop
- Realistic German course names and descriptions

**Benefits:**
- Immediate feedback (no need to rebuild/restart)
- Easy to verify and modify
- Self-documenting (SQL is explicit about what's created)
- Reproducible

### Decision 3: Replace Placeholder Data with Actual API Calls

**Rationale:**
The `CoursesView` component had hardcoded placeholder data in `onMounted`, which prevented testing of the actual API integration. Replacing with real API calls ensures the full data flow works.

**Implementation Changes:**
```typescript
// Before
onMounted(async () => {
  courses.value = [/* hardcoded array */]
})

// After
onMounted(() => {
  loadCourses()
})

async function loadCourses() {
  const response = await apiClient.get('/api/v1/courses')
  courses.value = response.data.data || []
}
```

**Benefits:**
- Tests full API integration
- Reveals any serialization/mapping issues
- Enables testing of filters and pagination
- Proper error handling via existing `handleApiError`

### Decision 4: German Localization for Course Status

**Rationale:**
The application is for a German dog training school. Status labels should be in German for consistency with the rest of the UI.

**Implementation:**
- Map database values (`planned`, `active`, `completed`, `cancelled`) to German labels
- Update filter dropdown to use German terms
- Maintain original database enum values (English) for API compatibility

**Mapping:**
- `planned` → "Geplant" (blue badge)
- `active` → "Aktiv" (green badge)
- `completed` → "Abgeschlossen" (gray badge)
- `cancelled` → "Abgesagt" (red badge)

## Risks / Trade-offs

### Risk: h() Function Verbosity
**Description:** Render functions are more verbose than templates, potentially making the code harder to read.

**Mitigation:** 
- Icons are simple SVGs that rarely change
- Type safety and compile-time validation offset verbosity
- Added comments to clarify each icon's purpose

### Risk: Database State Inconsistency
**Description:** Manual SQL inserts could be lost if database is reset or migrations are re-run.

**Mitigation:**
- Document the SQL commands in this design doc
- Consider creating a seeder for production setup if needed
- Current approach is acceptable for development/demo purposes

### Risk: Missing Course Relationships
**Description:** Sample courses reference trainer_id=2, which may not exist in all environments.

**Mitigation:**
- Verified trainer exists before inserting courses
- Error handling in API gracefully handles missing relationships
- If deployment script is created, ensure users are seeded first

### Trade-off: No i18n Framework
**Description:** Hardcoding German labels instead of using an internationalization framework limits future multilingual support.

**Acceptance Rationale:**
- Application scope is German-only for foreseeable future
- i18n framework adds complexity and bundle size
- Can be added later if multi-language support is required
- Current approach is simpler and more maintainable for single-language app

## Migration Plan

**Deployment Steps:**
1. Deploy frontend changes (ToastContainer.vue, CoursesView, badge labels)
2. Verify no console errors related to Vue compilation
3. Insert sample course data via SQL (if needed in other environments)
4. Test course list, create, edit, delete operations
5. Verify toast notifications display correctly

**Rollback Strategy:**
- Frontend changes are independent and can be rolled back via git revert
- Sample data is additive (won't break existing functionality)
- No database schema changes, so no migration rollback needed

**Validation:**
- Console should have no Vue compilation warnings
- Toast notifications should display with icons
- Course list should load from API
- All 4 status badge types should display with correct colors
- German labels should appear throughout course management UI

## Open Questions

None - all implementation decisions have been made and validated through testing.
