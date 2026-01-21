# Manual Testing Scenarios

This document lists manual test scenarios for the application. Because there are currently no frontend (Vue) tests, these scenarios should be used as a checklist whenever you change relevant parts of the UI.

For each scenario, verify:
- Correct URL and routing behavior.
- Page renders without JavaScript errors (browser console is clean).
- Inertia navigation works (no full page reload between Inertia pages).
- Validation and flash messages are shown where expected.

---

## Quick Smoke Test (Pre-deploy)

Run these fast checks before each deploy or after significant changes:

1. **Programs Overview**
  - Visit `/programs`.
  - Confirm programs list renders without errors and each item links to its detail page.

2. **Program Show & Enrollment**
  - Open a program detail page from the index.
  - Verify program details and workouts list (after deferred load) display correctly.
  - Enroll in the program if not enrolled; confirm the state updates and no duplicates occur on refresh.

3. **Start Workout from Template**
  - From a program or direct link, open a workout template page.
  - Click "Start Workout" and confirm you are redirected to `/workout-logs/{id}/edit` for a new log.

4. **Workout Logs Index & Show**
  - Visit `/workout-logs` and verify logs are listed with correct status.
  - Open a log’s show page; confirm basic info appears immediately and activities load after a short delay.

5. **Edit Activities and Save**
  - Open `/workout-logs/{id}/edit` for an in-progress log.
  - Change repetitions/weight on a couple of sets and save.
  - Refresh the page and confirm changes persisted.

6. **Complete Workout**
  - From the edit page, complete a workout and verify status changes to `completed` in the index.

7. **Delete Workout**
  - Delete a workout from the index or edit page and ensure it disappears and its URL now returns 404.

8. **Authorization Sanity Check**
  - With a second user, attempt to access another user’s workout log edit URL.
  - Confirm you see a 403/404 and cannot edit their data.

---

## 1. Programs (Index, Show, Enrollment)

### 1.1 Programs Index – Basic Rendering

- **Preconditions:** At least one program exists.
- **Steps:**
  - Visit `/programs` while authenticated and verified.
- **Expected:**
  - List of programs with name, description, and any configured metadata.
  - Each program has a link to its details page.
  - No unnecessary exposure of enrolled users (depending on privacy rules).

### 1.2 Program Detail – Show Page

- **Steps:**
  - Click on a program from the index.
- **Expected:**
  - Program details are shown (name, description, etc.).
  - Enrollment state is clear (e.g. "Enroll" button vs "Enrolled").
  - Workouts list:
    - Initially shows a loading/placeholder state due to deferred props.
    - After loading, renders a list of associated workouts.

### 1.3 Enroll in Program – Happy Path

- **Preconditions:** User is not yet enrolled in the selected program.
- **Steps:**
  - On the program show page, submit the enrollment form.
- **Expected:**
  - Request completes without full page reload.
  - Either redirects back to the show page or updates in-place.
  - Enrollment state changes to enrolled (button label or badge updates).

### 1.4 Enroll in Program – Idempotence

- **Steps:**
  - After enrolling, refresh the page or attempt to enroll again if the UI allows it.
- **Expected:**
  - No duplicate enrollment or errors.
  - Enrollment state remains consistent.

### 1.5 Program Show – Unauthorized Access

- **Preconditions:** A program the user should not see or a non-existent ID.
- **Steps:**
  - Attempt to access `/programs/{id}` for a program you should not access, or a random/non-existent ID.
- **Expected:**
  - Application shows a 404 or 403 page (according to design), not a broken or partial Inertia component.

----

## 2. Workout Templates (Show & Start Workout)

### 2.1 Workout Template Show

- **Steps:**
  - Navigate to a workout template show page (e.g. from a program or direct link).
- **Expected:**
  - Template details are visible (name, description, etc.).
  - Any associated activities or exercises are displayed if configured.
  - A "Start Workout" form/button is available.

### 2.2 Start Workout – Happy Path

- **Preconditions:** Valid workout template.
- **Steps:**
  - Click "Start Workout".
- **Expected:**
  - New workout log is created.
  - User is redirected to `/workout-logs/{id}/edit` for the newly created log.
  - Activities and sets match the template.

### 2.3 Start Workout – Missing Template

- **Steps:**
  - If possible, remove or disable a template, then try visiting its show page and starting a workout.
- **Expected:**
  - User receives a friendly error or 404, not a broken page.

----

## 3. Workout Logs (Index, Show, Edit, Complete, Delete)

### 3.1 Workout Logs Index – Basic Rendering

- **Preconditions:** User has at least one workout log.
- **Steps:**
  - Visit `/workout-logs` while logged in.
- **Expected:**
  - A list of logs appears with:
    - Workout template name.
    - Status (e.g. in_progress, completed).
    - Relevant dates/times.
  - Links for viewing and editing logs work and navigate via Inertia.

### 3.2 Workout Log Show – Deferred Activities

- **Steps:**
  - Open a log via its "view" link.
- **Expected:**
  - Basic log info (status, template name, etc.) is visible immediately.
  - Activities area:
    - Initially shows a loading/placeholder message (deferred prop).
    - Then displays read-only activities and sets once loaded.
  - An "Edit" button/link navigates to the edit page without full reload.

### 3.3 Workout Log Edit – Initial Rendering

- **Steps:**
  - Open `/workout-logs/{id}/edit` for an existing log.
- **Expected:**
  - Workout template information is displayed.
  - Activities and sets are pre-filled with the current data from the database.
  - Inputs for repetitions, weight, and order (if editable) show correct values.

### 3.4 Edit Activities & Sets – Simple Update

- **Steps:**
  - Modify repetitions and weight for one or more sets.
  - Save the changes using the UI control that triggers activity update.
- **Expected:**
  - No validation errors for valid input.
  - Page remains on the edit screen or refreshes with updated values.
  - Reloading the page shows the updated data.

### 3.5 Edit Activities – Validation Errors

- **Steps:**
  - Clear a required field (e.g. repetitions) or enter invalid values (negative weight, etc.).
  - Attempt to save.
- **Expected:**
  - Validation errors are displayed clearly (near fields or in a common area).
  - Error messages use readable field names when configured.
  - Invalid data is not saved.

### 3.6 Complete Workout

- **Preconditions:** Workout log status is `in_progress`.
- **Steps:**
  - Click the UI control to complete the workout.
- **Expected:**
  - Status changes to `completed`.
  - Redirect behavior (back to edit or index) is consistent.
  - In the logs index, the workout is shown as completed.

### 3.7 Delete Workout

- **Preconditions:** At least one workout log exists.
- **Steps:**
  - From the index or edit page, trigger a delete (via a delete button or action).
  - Confirm any confirmation dialog if present.
- **Expected:**
  - Log is removed from the database.
  - User is redirected appropriately (usually to the index).
  - Attempting to revisit the deleted log URL results in 404.

----

## 4. Activity Updates (Within Workout Edit)

### 4.1 Multiple Sets Update

- **Steps:**
  - Change values for several sets across one or more activities.
  - Save once.
- **Expected:**
  - All changes persist together.
  - No missing or duplicated sets.

### 4.2 Sets Ordering

- **Steps:**
  - Adjust order fields for sets (if editable in UI).
  - Save changes.
- **Expected:**
  - After saving and reloading, sets appear in the desired order.

### 4.3 Activity Ownership (UI-Level)

- **Preconditions:** Two different users (A and B) with their own workouts.
- **Steps:**
  - Log in as User A, create a workout log and note its ID.
  - Log in as User B and manually visit User A's workout edit URL.
- **Expected:**
  - User B is blocked (403 or redirect) and cannot edit activities of User A.

----

## 5. Authorization & Access Control (UI-Focused)

### 5.1 Viewing Other Users’ Programs/Workouts

- **Preconditions:** Users A and B with separate data.
- **Steps:**
  - As User A, note program and workout log IDs.
  - As User B, attempt to access `/programs/{id}` and `/workout-logs/{id}` for User A’s items.
- **Expected:**
  - Access is denied or returns 404/403 (depending on design).
  - No data for other users is visible in the UI.

### 5.2 Updating Other Users’ Activities

- **Steps:**
  - As User B, attempt to save changes to activities on a workout log owned by User A (via direct URL or UI if accessible).
- **Expected:**
  - Operation fails with 403 or redirect.
  - No unauthorized changes are applied.

----

## 6. Inertia Behaviors & UX Details

### 6.1 SPA Navigation (No Full Reloads)

- **Steps:**
  - With dev tools open, navigate between dashboard, programs, program show, workout logs, etc.
- **Expected:**
  - First request returns full HTML.
  - Subsequent navigations are Inertia JSON responses (XHR/Fetch).
  - No full page reloads occur when using Inertia links.

### 6.2 Inertia `<Form>` Flows

- **Flows to test:**
  - Program enrollment form.
  - Start workout from template form.
- **Expected:**
  - Submit buttons disable while processing.
  - Errors (if any) are displayed without full reload.
  - On success, redirect or UI updates happen without glitches.
  - No duplicate submissions when clicking quickly.

### 6.3 Deferred Props UX

- **Pages to test:** Program show and workout log show.
- **Steps:**
  - Load pages normally and under network throttling.
- **Expected:**
  - Immediate, non-broken initial view.
  - Clear loading indication for deferred data.
  - Data appears once loaded without layout jumps beyond what’s expected.

----

## 7. Error & Edge Cases

### 7.1 Global 404 Handling

- **Steps:**
  - Visit a clearly non-existent URL.
- **Expected:**
  - A user-friendly 404 page appears.
  - No JavaScript errors occur in the console.

### 7.2 Form Resubmission on Refresh

- **Steps:**
  - Perform a POST action (e.g. start workout, enroll in program).
  - After redirect, refresh the page.
- **Expected:**
  - No duplicate resources are created.
  - Page still renders correctly after refresh.

---

This checklist can be extended as new features are added. For each new page or flow, add:
- Happy path scenarios.
- Validation/error scenarios.
- Authorization and edge case scenarios where applicable.
