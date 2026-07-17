# Announcement Management

**Capability:** announcement-management

## Purpose

Allows admins to publish time-limited announcements (rich-text body, optional
image, configurable display duration) that appear on the public landing page
between the hero section and the "Unsere Leistungen" feature section, and to
manage them (including viewing expired ones) without requiring a scheduler or
cron job.

## Requirements

### Requirement: Admins can create announcements with rich text, an optional image, and a display duration

The system SHALL allow an authenticated user with the `admin` role to create
an announcement consisting of a `title`, a rich-text `body` (HTML,
server-side sanitized), an optional single image, and a `displayDays` value
(integer, 1–365) that determines how long the announcement remains active.

Non-admin users (including unauthenticated requests) SHALL NOT be able to
create, update, or delete announcements.

#### Scenario: Admin creates an announcement with all fields
- **WHEN** an admin submits `POST /api/v1/admin/announcements` with
  `title`, `body`, `displayDays`, and an image file
- **THEN** the system SHALL persist the announcement
- **AND** the response SHALL include `imageUrl` pointing to the stored
  image on the `public` disk

#### Scenario: Admin creates an announcement without an image
- **WHEN** an admin submits `POST /api/v1/admin/announcements` with
  `title`, `body`, and `displayDays`, but no image
- **THEN** the system SHALL create the announcement successfully
- **AND** `imageUrl` SHALL be `null` in the response

#### Scenario: Non-admin is rejected
- **WHEN** a non-admin (or unauthenticated) request attempts
  `POST /api/v1/admin/announcements`, `PUT
  /api/v1/admin/announcements/{id}`, or
  `DELETE /api/v1/admin/announcements/{id}`
- **THEN** the system SHALL respond with HTTP 403 (or 401 if unauthenticated)
- **AND** SHALL NOT persist any change

#### Scenario: Invalid display duration is rejected
- **WHEN** a request to create or update an announcement includes a
  `displayDays` value outside the range 1–365
- **THEN** the system SHALL respond with HTTP 422
- **AND** SHALL NOT persist the invalid value

---

### Requirement: Announcement body HTML is sanitized server-side before storage

The system SHALL strip any HTML tags and attributes not on the project's
established rich-text allowlist (`p, br, strong, em, h2, h3, ul, ol, li,
blockquote, code, pre`, no attributes) from the `body` field before
persisting an announcement, regardless of what the client sends.

#### Scenario: Disallowed tags and event-handler attributes are removed
- **WHEN** an admin submits a `body` value containing `<script>` tags or an
  `onclick` attribute on an otherwise-allowed tag (e.g.
  `<strong onclick="...">`)
- **THEN** the system SHALL store the value with the disallowed tag
  removed and all attributes stripped from the remaining allowed tags
- **AND** SHALL NOT execute or persist the injected script/attribute

#### Scenario: Allowed formatting is preserved
- **WHEN** an admin submits a `body` value using only allowed tags (e.g.
  `<p>`, `<strong>`, `<ul><li>`)
- **THEN** the system SHALL persist the formatting unchanged (aside from
  attribute stripping)

---

### Requirement: Multiple announcements can be active at the same time

The system SHALL support more than one announcement being active
simultaneously. There SHALL be no mechanism that deactivates or hides one
active announcement when another is created or becomes active.

#### Scenario: Two announcements are active at once
- **WHEN** two announcements exist whose display windows both currently
  cover `now()`
- **THEN** the public endpoint SHALL return both in its response list

---

### Requirement: Announcement expiry is computed from the display duration without a scheduler

The system SHALL determine whether an announcement is currently active by
comparing a persisted `expiresAt` timestamp (computed from the
announcement's original creation time plus `displayDays`) against the
current time at read time. No scheduled task, cron job, or queue worker
SHALL be required to deactivate an expired announcement.

#### Scenario: Announcement becomes inactive after its display duration elapses
- **WHEN** an announcement was created with `displayDays = 3` and more
  than 3 days have since passed
- **THEN** the public endpoint (`GET /api/v1/announcements`) SHALL NOT
  include this announcement in its response
- **AND** no scheduled job needs to run for this to take effect

#### Scenario: Editing an announcement's text does not silently extend its display window
- **WHEN** an admin updates only the `title` or `body` of an existing
  announcement, without changing `displayDays`
- **THEN** the announcement's `expiresAt` SHALL remain based on its
  original creation time, not the edit time

#### Scenario: Changing displayDays recalculates the expiry from the original creation time
- **WHEN** an admin updates `displayDays` on an existing, not-yet-expired
  announcement
- **THEN** the system SHALL recompute `expiresAt` as the announcement's
  original `createdAt` plus the new `displayDays` value (not from the
  edit time)

---

### Requirement: Public landing page displays only currently active announcements

The system SHALL expose a public, unauthenticated endpoint
(`GET /api/v1/announcements`) that returns only announcements that are
currently active. The public landing page (`HomeView.vue`) SHALL render an
announcement section between the hero section and the "Unsere
Leistungen" feature section only when at least one active announcement
exists; otherwise the section SHALL NOT be rendered.

#### Scenario: No active announcements exist
- **WHEN** no announcement is currently active
- **THEN** `GET /api/v1/announcements` SHALL return an empty list
- **AND** the landing page SHALL NOT render the announcement section

#### Scenario: At least one active announcement exists
- **WHEN** at least one announcement is currently active
- **THEN** the landing page SHALL render the announcement section between
  the hero section and the "Unsere Leistungen" section
- **AND** each active announcement SHALL be displayed with its
  sanitized rich-text body and image (if present)

---

### Requirement: Admin area lists all announcements including expired ones, with a status indicator

The system SHALL provide an admin-only view that lists **all**
announcements — both currently active and expired — without automatically
deleting expired ones. Each entry SHALL be labeled with its current status
(active or expired).

#### Scenario: Admin views the announcement list
- **WHEN** an admin navigates to the announcement management area
- **THEN** the system SHALL display all announcements, each with a status
  label of "active" or "expired" matching whether `expiresAt` is in the
  future or the past
- **AND** expired announcements SHALL remain visible and editable/deletable,
  not hidden or auto-removed

#### Scenario: Admin deletes an announcement
- **WHEN** an admin deletes an announcement that has an associated image
- **THEN** the system SHALL remove the announcement record
- **AND** SHALL delete the associated image file from storage
