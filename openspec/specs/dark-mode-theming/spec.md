# Dark-Mode Theming

## Purpose

Definiert, dass die authentifizierte Anwendung (Layout, Formular-Modals und Listen-/Tabellen-Ansichten) im Dark-Mode durchgängig mit dunklen Hintergründen und ausreichend kontrastierendem, hellem Text dargestellt wird, ohne die Light-Mode-Darstellung zu verändern.

## Requirements

### Requirement: The authenticated application layout background is theme-aware

The system SHALL render the background of the authenticated (post-login)
application layout using a dark-toned overlay when dark mode is active,
and SHALL NOT render a light-toned overlay while dark mode is active,
regardless of how the background is implemented (CSS class or computed
inline style).

#### Scenario: Dark mode is active after login
- **WHEN** an authenticated user has dark mode enabled (`useThemeStore`
  `isDark === true`, `dark` class present on `document.documentElement`)
- **AND** navigates to any authenticated view rendered inside the main
  application layout
- **THEN** the layout's background SHALL use a dark-toned overlay
- **AND** SHALL NOT use the light-toned overlay that is used in light
  mode

#### Scenario: Light mode is active after login
- **WHEN** an authenticated user has dark mode disabled
- **THEN** the layout's background SHALL render exactly as before this
  change (light-toned overlay), with no visual regression

### Requirement: Form modals render with dark-mode-appropriate colors

The system SHALL render every form and detail modal reachable from an
authenticated view (dog, customer, course, booking, trainer, invoice, and
anamnesis modals) with a dark-toned panel background, dark-mode-
appropriate border colors, and light-colored text when dark mode is
active. Modals SHALL NOT render with a light (light-mode) panel
background while dark mode is active.

#### Scenario: A form modal is opened while dark mode is active
- **WHEN** an authenticated user with dark mode enabled opens a form or
  detail modal (e.g. to create or edit a dog, customer, course, booking,
  trainer, invoice, or anamnesis record)
- **THEN** the modal panel SHALL render with a dark background (not the
  light-mode default)
- **AND** all text within the modal (labels, field values, headings)
  SHALL render in a color with sufficient contrast against that dark
  background

#### Scenario: A form modal is opened while light mode is active
- **WHEN** an authenticated user with dark mode disabled opens the same
  modal
- **THEN** the modal SHALL render exactly as before this change (light
  panel background, dark text), with no visual regression

### Requirement: Text elements match the dark-mode state of their container

The system SHALL NOT render text using a color intended for a light
background (e.g. a dark gray text tone with no dark-mode counterpart)
inside a container whose background switches to a dark tone in dark
mode. Every text color utility applied within a dark-mode-aware container
SHALL have a corresponding dark-mode counterpart that keeps the text
legible against that container's dark-mode background.

#### Scenario: A list/table view's container background switches to dark mode
- **WHEN** a post-login list or table view (e.g. customers, trainers,
  invoices, anamnesis, dogs, bookings) has a container whose background
  switches to a dark tone in dark mode
- **THEN** every text element rendered inside that container (headings,
  cell values, secondary/meta text) SHALL also switch to a light-enough
  color to remain legible
- **AND** no text element SHALL remain on its light-mode-only (dark
  gray/black) color while its container background is dark

#### Scenario: Nested/child elements inherit dark-mode text handling
- **WHEN** a component that already has dark-mode support for its outer
  container renders nested child elements with their own text color
  utilities (e.g. table cells inside a table with a dark-mode
  background)
- **THEN** those nested child elements SHALL also carry a dark-mode text
  color counterpart, not rely on inheriting one from an ancestor that
  does not set `color`
