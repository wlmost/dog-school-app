## ADDED Requirements

### Requirement: Web-Based Interface

The installation wizard SHALL provide a web-based interface accessible through a browser.

#### Scenario: Wizard accessible via browser
- **WHEN** user navigates to install.php in their browser
- **THEN** the wizard SHALL display a responsive HTML interface

#### Scenario: Mobile-friendly layout
- **WHEN** the wizard is accessed on mobile devices
- **THEN** the interface SHALL be responsive and usable on small screens

#### Scenario: Wizard not accessible after installation
- **WHEN** installation is complete or install.lock exists
- **THEN** the wizard SHALL display a locked message and refuse to run

### Requirement: Multi-Step Installation Process

The wizard SHALL guide users through installation in clearly defined steps.

#### Scenario: Welcome screen
- **WHEN** the wizard starts
- **THEN** it SHALL display a welcome screen with overview of the installation process

#### Scenario: Step progression
- **WHEN** users complete each step successfully
- **THEN** the wizard SHALL automatically advance to the next step

#### Scenario: Step validation
- **WHEN** users attempt to proceed without completing required fields
- **THEN** the wizard SHALL display validation errors and prevent progression

### Requirement: Server Requirements Validation

The wizard SHALL integrate the existing server-validation capability to verify hosting compatibility.

#### Scenario: Requirements check execution
- **WHEN** the wizard runs the server validation step
- **THEN** it SHALL execute all checks from the server-validation capability

#### Scenario: Requirements check passes
- **WHEN** all server requirements are met
- **THEN** the wizard SHALL allow proceeding to the next step

#### Scenario: Requirements check fails
- **WHEN** critical server requirements are not met
- **THEN** the wizard SHALL display failing checks with explanations and block installation

#### Scenario: Optional requirements missing
- **WHEN** recommended but non-critical requirements are missing
- **THEN** the wizard SHALL display warnings but allow proceeding

### Requirement: Database Configuration

The wizard SHALL collect database connection details and validate connectivity.

#### Scenario: Database form display
- **WHEN** the database configuration step is reached
- **THEN** the wizard SHALL display a form for host, port, database name, username, and password

#### Scenario: Default values provided
- **WHEN** the database form is displayed
- **THEN** it SHALL pre-fill default values (host: localhost, port: 3306)

#### Scenario: Database connection test
- **WHEN** users submit database credentials
- **THEN** the wizard SHALL attempt to connect and report success or failure

#### Scenario: Database connection failure
- **WHEN** database connection fails
- **THEN** the wizard SHALL display the PDO error message and allow retrying with different credentials

#### Scenario: Database exists check
- **WHEN** connection succeeds
- **THEN** the wizard SHALL verify the database exists or offer to create it

### Requirement: Application Configuration

The wizard SHALL collect essential application settings.

#### Scenario: Application name input
- **WHEN** the configuration step is reached
- **THEN** the wizard SHALL prompt for application name with default "HomoCanis"

#### Scenario: Application URL input
- **WHEN** the configuration step is reached
- **THEN** the wizard SHALL prompt for application URL with auto-detected default

#### Scenario: Application environment selection
- **WHEN** the configuration step is reached
- **THEN** the wizard SHALL allow selecting environment (production/local) with production as default

#### Scenario: Timezone selection
- **WHEN** the configuration step is reached
- **THEN** the wizard SHALL provide a dropdown of valid PHP timezones

### Requirement: Environment File Generation

The wizard SHALL generate a .env file from .env.template with user-provided values.

#### Scenario: .env file creation
- **WHEN** all configuration is collected
- **THEN** the wizard SHALL create .env file from .env.template with substituted values

#### Scenario: Application key generation
- **WHEN** generating .env file
- **THEN** the wizard SHALL execute `php artisan key:generate --force` to set APP_KEY

#### Scenario: Database credentials written
- **WHEN** generating .env file
- **THEN** the wizard SHALL write DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD

#### Scenario: .env file permissions
- **WHEN** .env file is created
- **THEN** the wizard SHALL set permissions to 600 (owner read/write only)

### Requirement: Database Migration Execution

The wizard SHALL run Laravel migrations to initialize the database schema.

#### Scenario: Migration execution
- **WHEN** the migration step is reached
- **THEN** the wizard SHALL execute `php artisan migrate --force` and capture output

#### Scenario: Migration success
- **WHEN** migrations complete successfully
- **THEN** the wizard SHALL display success message and proceed

#### Scenario: Migration failure
- **WHEN** migrations fail
- **THEN** the wizard SHALL display error output and offer rollback option

#### Scenario: Migration progress display
- **WHEN** migrations are running
- **THEN** the wizard SHALL display progress or a loading indicator

### Requirement: Storage Link Creation

The wizard SHALL create the storage symlink required by Laravel.

#### Scenario: Symlink creation
- **WHEN** the storage link step is reached
- **THEN** the wizard SHALL execute `php artisan storage:link` and capture output

#### Scenario: Symlink already exists
- **WHEN** the storage link already exists
- **THEN** the wizard SHALL skip creation and display a notice

#### Scenario: Symlink creation failure
- **WHEN** symlink creation fails due to permissions
- **THEN** the wizard SHALL display error and provide manual instructions

### Requirement: Directory and Permission Setup

The wizard SHALL create required directories and set appropriate permissions.

#### Scenario: Storage directories created
- **WHEN** the setup step is reached
- **THEN** the wizard SHALL create storage/app, storage/framework, storage/logs with permissions 775

#### Scenario: Cache directories created
- **WHEN** the setup step is reached
- **THEN** the wizard SHALL create bootstrap/cache with permissions 775

#### Scenario: Permission verification
- **WHEN** directories are created
- **THEN** the wizard SHALL verify they are writable by the web server

#### Scenario: Permission setting failure
- **WHEN** permissions cannot be set
- **THEN** the wizard SHALL display warning and manual chmod instructions

### Requirement: Installation Completion and Security

The wizard SHALL finalize installation and prevent re-execution.

#### Scenario: Lock file creation
- **WHEN** installation completes successfully
- **THEN** the wizard SHALL create install.lock file to prevent re-running

#### Scenario: Success screen display
- **WHEN** installation is complete
- **THEN** the wizard SHALL display success message with next steps (login URL, default credentials if applicable)

#### Scenario: Post-installation security notice
- **WHEN** the success screen is displayed
- **THEN** the wizard SHALL recommend deleting install.php for security

#### Scenario: Automatic installer removal option
- **WHEN** the success screen is displayed
- **THEN** the wizard SHALL offer a button to delete install.php automatically

### Requirement: Rollback on Failure

The wizard SHALL support rollback of partial installations.

#### Scenario: Rollback offer on failure
- **WHEN** any critical step fails
- **THEN** the wizard SHALL offer to rollback changes

#### Scenario: Rollback execution
- **WHEN** user chooses to rollback
- **THEN** the wizard SHALL delete .env, drop database tables if created, and clean up directories

#### Scenario: Rollback confirmation
- **WHEN** rollback completes
- **THEN** the wizard SHALL display confirmation and allow starting over

### Requirement: Error Handling and User Guidance

The wizard SHALL provide clear error messages and recovery guidance.

#### Scenario: Detailed error messages
- **WHEN** any step fails
- **THEN** the wizard SHALL display a specific error message with the failure reason

#### Scenario: Recovery suggestions
- **WHEN** an error is displayed
- **THEN** the wizard SHALL provide actionable suggestions for resolution

#### Scenario: Log file reference
- **WHEN** an error occurs
- **THEN** the wizard SHALL reference relevant log files for debugging

### Requirement: Progress Persistence

The wizard SHALL preserve progress across page refreshes using sessions.

#### Scenario: Progress saved
- **WHEN** each step is completed
- **THEN** the wizard SHALL save the step number and collected data to session

#### Scenario: Progress restored
- **WHEN** user refreshes the page
- **THEN** the wizard SHALL restore to the current step without data loss

#### Scenario: Session expiry handling
- **WHEN** the session expires during installation
- **THEN** the wizard SHALL detect this and allow resuming from a safe checkpoint
