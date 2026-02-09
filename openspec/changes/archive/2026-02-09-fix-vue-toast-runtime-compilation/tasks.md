## 1. Fix ToastContainer Vue Runtime Compilation

- [x] 1.1 Import Vue's `h()` function and `Component` type in ToastContainer.vue
- [x] 1.2 Convert `toastIcon.success` template string to render function using `h('svg', ...)`
- [x] 1.3 Convert `toastIcon.error` template string to render function using `h('svg', ...)`
- [x] 1.4 Convert `toastIcon.warning` template string to render function using `h('svg', ...)`
- [x] 1.5 Convert `toastIcon.info` template string to render function using `h('svg', ...)`
- [x] 1.6 Add type annotation `Record<string, Component>` to toastIcon object
- [x] 1.7 Test toast notifications render correctly without console errors

## 2. Populate Database with Sample Courses

- [x] 2.1 Verify trainer user (ID 2) exists in database
- [x] 2.2 Insert "Welpenspielgruppe" course (group, planned status)
- [x] 2.3 Insert "Grundgehorsam" course (group, active status)
- [x] 2.4 Insert "Einzeltraining" course (individual, active status)
- [x] 2.5 Insert "Agility Workshop" course (workshop, planned status)
- [x] 2.6 Insert "Fortgeschrittene Tricks" course (group, planned status)
- [x] 2.7 Insert "Nasenarbeit Schnupperkurs" course (workshop, cancelled status)
- [x] 2.8 Insert "Grundgehorsam Komplettkurs" course (group, completed status)
- [x] 2.9 Verify all courses inserted successfully with correct status values

## 3. Fix CoursesView API Loading

- [x] 3.1 Remove hardcoded placeholder course data from onMounted hook
- [x] 3.2 Update onMounted to call loadCourses() function instead
- [x] 3.3 Update loadCourses() to handle empty array response (`|| []`)
- [x] 3.4 Improve error handling in loadCourses() using handleApiError utility
- [x] 3.5 Ensure courses array is reset to empty on error
- [x] 3.6 Test course list loads from API successfully

## 4. Localize Course Status Badges to German

- [x] 4.1 Update courseStatusClass() to include `cancelled` status with red styling
- [x] 4.2 Update courseStatusClass() fallback to return gray styling instead of `classes.upcoming`
- [x] 4.3 Update courseStatusLabel() to map `planned` → "Geplant" (was `upcoming`)
- [x] 4.4 Add courseStatusLabel() mapping for `cancelled` → "Abgesagt"
- [x] 4.5 Update filter dropdown option from "Bevorstehende Kurse" to "Geplante Kurse"
- [x] 4.6 Update filter dropdown option value from `upcoming` to `planned`
- [x] 4.7 Add filter dropdown option for cancelled courses ("Abgesagte Kurse")
- [x] 4.8 Verify all status badges display correct German labels
- [x] 4.9 Verify all status badges have correct color styling

## 5. Testing and Verification

- [x] 5.1 Verify no Vue compilation warnings in browser console
- [x] 5.2 Test success toast displays with green styling and checkmark icon
- [x] 5.3 Test error toast displays with red styling and X icon
- [x] 5.4 Test warning toast displays with yellow styling and warning icon
- [x] 5.5 Test info toast displays with blue styling and info icon
- [x] 5.6 Verify course list loads all 7 sample courses
- [x] 5.7 Verify "Geplant" badge appears blue on planned courses
- [x] 5.8 Verify "Aktiv" badge appears green on active courses
- [x] 5.9 Verify "Abgeschlossen" badge appears gray on completed courses
- [x] 5.10 Verify "Abgesagt" badge appears red on cancelled courses
- [x] 5.11 Test course filter dropdown works with all status options
- [x] 5.12 Verify proper error messaging when API fails
- [x] 5.13 Verify empty state displays when no courses match filter

## 6. Documentation

- [x] 6.1 Create proposal.md documenting the why and what of the change
- [x] 6.2 Create design.md explaining technical decisions and implementation approach
- [x] 6.3 Create frontend-toast-notifications spec defining toast system requirements
- [x] 6.4 Create course-management spec defining course display requirements
- [x] 6.5 Create tasks.md with complete implementation checklist
