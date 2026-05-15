## MODIFIED Requirements

### Requirement: Dog profile image upload accepts files up to 5 MB
The system SHALL accept dog profile image uploads up to the Laravel validation limit
of 5 MB (5120 KB) without rejecting them at the reverse-proxy layer.

The nginx reverse proxy MUST be configured with `client_max_body_size 10M` (2× the
Laravel validation limit) so that multipart overhead does not cause nginx to reject
valid uploads before the request reaches Laravel.

When a file exceeds the Laravel validation limit (> 5 MB), Laravel SHALL return
HTTP 422 with a structured validation error. The CORS headers SHALL be present in
the response in all cases (nginx does not intercept).

#### Scenario: Upload of a valid image (≤ 5 MB) succeeds
- **WHEN** a user submits a dog profile image ≤ 5 MB via `DogFormModal`
- **THEN** the system returns HTTP 200 and the dog's `profile_image` field is updated

#### Scenario: Upload rejected by Laravel validation (> 5 MB)
- **WHEN** a user submits a dog profile image > 5 MB
- **THEN** Laravel returns HTTP 422 with a `errors.image` validation message
- **THEN** CORS headers (`Access-Control-Allow-Origin`) are present in the response

#### Scenario: Upload blocked by nginx (> 10 MB)
- **WHEN** a user submits a file > 10 MB
- **THEN** nginx returns HTTP 413
- **THEN** the browser does not report a spurious CORS error for a < 10 MB file

#### Scenario: False CORS error no longer occurs for images between 1 MB and 10 MB
- **WHEN** a user uploads a file between 1 MB and 10 MB
- **THEN** the request reaches Laravel (nginx does not intercept with HTTP 413)
- **THEN** no `Access-Control-Allow-Origin` error is raised in the browser console
