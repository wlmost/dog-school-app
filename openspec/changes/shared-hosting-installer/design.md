## Context

HomoCanis is currently developed using Docker with a specific directory structure:
- `backend/` - Laravel application
- `frontend/` - Vue.js application
- `docker/` - Docker configuration files
- `openspec/` - Specification files

The Docker environment uses Nginx with the document root pointing to `backend/public/`. The frontend is built and served as static files.

**Current State:**
- Development uses Docker Compose with separate containers
- Backend and frontend are in separate directories
- No automated deployment process for shared hosting exists
- Manual deployment requires technical knowledge of Laravel, file permissions, .env configuration

**Constraints:**
- Shared hosting typically has a public web root (e.g., `public_html/`, `htdocs/`)
- Limited shell access on many shared hosts (web-based file manager + FTP)
- Cannot use Docker on shared hosting
- Must work with PHP 8.4+ and MySQL
- Directory structure should remain unchanged to avoid code modifications
- Apache with .htaccess is standard on shared hosting (vs. Nginx in Docker)

**Stakeholders:**
- Providers who will deploy the application to their shared hosting
- End users who will access the deployed application
- Developers maintaining the codebase

## Goals / Non-Goals

**Goals:**
- Create one-command build process that generates deployment-ready tar archive
- Provide web-based installation wizard for non-technical providers
- Maintain 100% directory structure compatibility with Docker (no code changes)
- Automate all setup steps: directories, permissions, database, .env, security
- Integrate with existing server-validation capability
- Ensure security through .htaccess rules and installer lockdown
- Support both web-based and CLI-based installation methods
- Enable rollback on critical failures

**Non-Goals:**
- Automatic updates or continuous deployment
- Multi-server or clustered deployments
- Migration from other systems
- Database backup/restore functionality (separate concern)
- Custom domain SSL certificate installation (provider/host responsibility)
- Performance tuning or optimization (separate concern)

## Decisions

### 1. Directory Structure Strategy

**Decision:** Use subdirectory deployment with symbolic link approach for compatibility.

**Approach:**
```
public_html/                    # Shared hosting web root
├── homocanis/                 # Application root (extracted tar)
│   ├── backend/
│   │   ├── app/
│   │   ├── public/           # Laravel public directory
│   │   ├── storage/
│   │   └── ...
│   ├── frontend/
│   │   └── dist/             # Built frontend assets
│   ├── install.php           # Installation wizard
│   └── .htaccess             # Redirect to backend/public
└── .htaccess                  # Optional: redirect to homocanis/
```

**Rationale:**
- Keeps application files in subdirectory (clean, isolated)
- Root .htaccess redirects all traffic to `backend/public/index.php`
- Maintains exact same structure as Docker environment
- No code changes required in backend or frontend
- Easy to version/replace entire application directory

**Alternatives Considered:**
- Flatten structure (backend/* directly in public_html): Rejected - requires code changes, messy
- Keep backend/public as web root: Rejected - exposes parent directories unless carefully configured
- Move only public directory to web root: Rejected - breaks relative paths in Laravel

### 2. Build Script Implementation

**Decision:** Shell script (`build-deployment.sh`) for maximum flexibility and toolchain integration.

**What it does:**
1. Clean previous build artifacts
2. Run `composer install --no-dev --optimize-autoloader` in backend
3. Run `npm ci && npm run build` in frontend
4. Create deployment temp directory
5. Copy application files (exclude: node_modules, .git, .env, docker/, openspec/, tests/)
6. Copy .env.example as .env.template
7. Generate .htaccess templates for shared hosting
8. Include install.php wizard
9. Create tar.gz archive with timestamp
10. Clean up temp directory

**Rationale:**
- Shell scripts integrate easily with composer, npm, tar
- Can be run in CI/CD or locally
- Easy to version control and modify
- Cross-platform compatible (bash available everywhere)

**Alternatives Considered:**
- PHP script: Rejected - harder to integrate with npm/composer, less idiomatic
- Makefile: Rejected - less familiar to PHP developers
- Manual process: Rejected - error-prone, defeats purpose

### 3. Installation Wizard Approach

**Decision:** Web-based PHP wizard (`install.php`) with optional CLI support.

**Why Web-Based:**
- Many shared hosting providers don't provide SSH access
- Non-technical users can use web browser
- Can show progress, errors visually
- Easy to include server requirement checks
- Can reuse existing requirements-check.php code

**Features:**
- Multi-step form (Requirements → Database → Settings → Security → Complete)
- Validates server requirements using server-validation capability
- Tests database connection before proceeding
- Generates .env from user input
- Runs Laravel migrations
- Sets up storage symlink
- Locks itself after successful completion
- Provides rollback on critical failure

**Security:**
- Checks for existing .env (prevents re-installation over live site)
- Requires confirmation token generated from random value
- Self-destructs by renaming to .install.php.completed
- Sets proper file permissions (644/755/775)

**Alternatives Considered:**
- CLI-only wizard: Rejected - not accessible on shared hosting without SSH
- Config file approach: Rejected - requires manual editing, error-prone
- Artisan command: Rejected - requires composer, may not work pre-installation

### 4. .htaccess Configuration Strategy

**Decision:** Multi-layered .htaccess approach with templates generated during build.

**Structure:**
```
public_html/homocanis/.htaccess  # Root level, redirects to backend/public
backend/.htaccess                 # Deny all
backend/public/.htaccess          # Laravel routing + security headers
frontend/.htaccess                # Deny all (dist/ already in public/)
storage/.htaccess                 # Deny all
```

**Root .htaccess (public_html/homocanis/.htaccess):**
```apache
RewriteEngine On
RewriteRule ^(.*)$ backend/public/$1 [L,QSA]
```

**Backend/public/.htaccess (Laravel standard + security):**
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ index.php [QSA,L]
</IfModule>

# Security headers
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set X-XSS-Protection "1; mode=block"
</IfModule>

# Disable directory browsing
Options -Indexes
```

**Deny .htaccess (storage/, backend/ root, frontend/):**
```apache
Deny from all
```

**Rationale:**
- Prevents direct access to application files outside public/
- Standard Laravel .htaccess enhanced with security headers
- Works with Apache mod_rewrite (standard on shared hosting)
- Easy to debug (can test each redirect step)

**Alternatives Considered:**
- Single root .htaccess only: Rejected - doesn't protect other directories
- Nginx config: Rejected - not available on shared hosting (Apache is standard)
- PHP-based access control: Rejected - .htaccess is more secure (doesn't execute PHP)

### 5. Environment (.env) Generation

**Decision:** Template-based generation with wizard input validation.

**Process:**
1. Installation wizard reads .env.template (which is .env.example from repo)
2. Wizard presents form with sections:
   - Application settings (APP_NAME, APP_URL, APP_ENV=production, APP_DEBUG=false)
   - Database settings (DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD)
   - Mail settings (MAIL_MAILER, MAIL_HOST, MAIL_PORT, MAIL_FROM)
   - Optional: advanced settings (cache, queue, session drivers)
3. Validates each input:
   - APP_URL must be valid URL
   - Database credentials tested via PDO connection
   - Mail settings optional (can configure later)
4. Generates random APP_KEY using PHP (equivalent to `php artisan key:generate`)
5. Writes .env file with validated values
6. Sets .env permissions to 600 (owner read/write only)

**Defaults Provided:**
- APP_ENV=production
- APP_DEBUG=false
- DB_HOST=localhost
- DB_PORT=3306
- CACHE_DRIVER=file
- SESSION_DRIVER=database
- QUEUE_CONNECTION=database

**Rationale:**
- Template approach ensures all required variables present
- Validation prevents common configuration errors
- Database test catches connectivity issues early
- Random APP_KEY generation is cryptographically secure
- Defaults are production-safe

**Alternatives Considered:**
- Manual .env editing: Rejected - error-prone
- Environment variables from web server: Rejected - not available in shared hosting
- Config file approach: Rejected - Laravel requires .env

### 6. Database Migration Strategy

**Decision:** Run migrations automatically during installation with rollback on failure.

**Approach:**
1. After .env is generated and validated, installer runs:
   ```php
   Artisan::call('migrate', ['--force' => true]);
   ```
2. If migration succeeds, continue to next step
3. If migration fails:
   - Display error message
   - Offer rollback option
   - Rollback deletes .env and created directories
   - Allow retry with different database credentials

**Why During Installation:**
- Database schema must exist before application can run
- Better to fail during installation than at first user access
- Wizard provides user-friendly error reporting

**Seeding:**
- Optional checkbox "Install demo data"
- If checked, runs seeders after migrations
- Default: unchecked (production installations typically don't want demo data)

**Rationale:**
- Migrations are required for application to function
- Automatic migration reduces manual steps and errors
- Rollback capability prevents half-configured installations

**Alternatives Considered:**
- Manual migration post-install: Rejected - requires SSH and technical knowledge
- Skip migrations, show instructions: Rejected - defeats purpose of automation
- Pre-migrated database dump: Rejected - not flexible, version-specific

### 7. Installer Self-Destruct Mechanism

**Decision:** Lock file approach with rename and access check.

**Implementation:**
1. After successful installation, create `.install.lock` file
2. Rename `install.php` to `install.php.completed`
3. On subsequent access to install.php, check for lock file
4. If lock exists, show "Installation already completed" and exit
5. Provide emergency override via specific environment variable or file deletion

**Lock File Content:**
```
Installation completed on: 2026-02-15 14:30:00
Installed by IP: 123.45.67.89
Application URL: https://hundeschule.homocanis.de
```

**Rationale:**
- Prevents accidental re-installation over live application
- Renaming prevents execution even if lock is deleted
- Lock file provides installation audit trail
- Emergency override allows legitimate re-installation if needed

**Alternatives Considered:**
- Delete install.php: Rejected - can't reinstall if needed, no audit trail
- Password-protect only: Rejected - password might be forgotten
- Database flag: Rejected - database might not be accessible if reinstalling

### 8. Frontend Asset Integration

**Decision:** Pre-built frontend assets included in deployment package, served via backend.

**Structure:**
```
backend/public/
├── index.php           # Laravel entry point
├── assets/             # Symlink or copy of frontend/dist/assets
└── index.html          # Copy of frontend/dist/index.html (for SPA fallback)
```

**Build Process:**
1. Frontend built during deployment package creation (`npm run build`)
2. Built assets from `frontend/dist/` copied to `backend/public/`
3. Laravel serves frontend via fallback route or direct file serving

**Rationale:**
- Single web root (backend/public) simplifies .htaccess configuration
- No separate static file server needed
- Frontend is pre-built, no npm needed on shared host
- Maintains Docker environment parity (Nginx serves both in Docker, Apache serves both in shared hosting)

**Alternatives Considered:**
- Separate frontend serving: Rejected - requires complex Apache config, multiple virtual hosts
- Build frontend on shared host: Rejected - npm might not be available
- CDN approach: Rejected - requires external dependency, not suitable for private deployments

### 9. Permission Handling

**Decision:** Automated permission setting with validation and repair.

**Setup Permissions:**
```
backend/storage/          → 775 (directories), 664 (files)
backend/bootstrap/cache/  → 775 (directories), 664 (files)
backend/public/storage    → 755 (symlink)
.env                      → 600 (owner read/write only)
install.php               → 755 (executable)
```

**Process:**
1. Installer attempts to set permissions via chmod()
2. If chmod fails (some shared hosts restrict), show warning with manual instructions
3. Verify write access by creating test files
4. If verification fails, show specific error with path and recommended permissions

**Rationale:**
- Correct permissions are critical for Laravel to function
- Automated setting reduces manual steps
- Validation catches permission issues early
- Manual fallback for restricted hosting

**Alternatives Considered:**
- Assume correct permissions: Rejected - common source of deployment failures
- Only validate, don't set: Rejected - misses opportunity to automate
- Proprietary permission detection: Rejected - too complex, varies by host

### 10. Error Handling and Logging

**Decision:** Comprehensive error handling with user-friendly messages and detailed logs.

**Error Levels:**
- **Critical**: Blocks installation, requires user action (e.g., database connection failed)
- **Warning**: Non-blocking, can continue with limitations (e.g., optional extension missing)
- **Info**: Informational messages (e.g., migration completed)

**Logging:**
- All installer actions logged to `install.log`
- Errors include timestamp, type, message, context
- Log file permissions set to 600 (not web-accessible)
- Success completion appends summary to log

**User Display:**
- Critical errors shown in red with actionable steps
- Warnings shown in yellow with explanation
- Progress shown with step indicators
- Success shown with green confirmation

**Rationale:**
- Users need clear guidance on what went wrong
- Logs provide debugging information for support
- Different error levels allow graceful degradation
- Visual feedback improves user experience

**Alternatives Considered:**
- Generic error messages: Rejected - not helpful for troubleshooting
- No logging: Rejected - makes support difficult
- Verbose error display: Rejected - overwhelming for non-technical users

## Risks / Trade-offs

**[Risk]** Shared hosting environment too restrictive (no exec, chmod, etc.)  
→ **Mitigation:** Installer detects restrictions and provides manual fallback instructions; requirements-check.php validates environment before installation

**[Risk]** Partial installation failure leaves application in inconsistent state  
→ **Mitigation:** Rollback mechanism deletes .env and created directories; lock file prevents re-entry until cleanup

**[Risk]** Provider uploads wrong tar file or extracts to wrong location  
→ **Mitigation:** Installer validates directory structure on startup; requirements-check.php catches missing files

**[Risk]** Database credentials change after installation  
→ **Mitigation:** Standard Laravel practice - edit .env manually; document in DEPLOYMENT.md

**[Risk]** .htaccess rules conflict with hosting provider's configuration  
→ **Mitigation:** Use minimal, standard Apache directives; provide troubleshooting guide in docs

**[Risk]** File permissions reset by hosting provider (e.g., during backups)  
→ **Mitigation:** Document required permissions; provide permission repair script

**[Risk]** Installer accessed by unauthorized users before completion  
→ **Mitigation:** Installer generates random token, requires confirmation; document immediate installation best practice

**[Trade-off]** Web-based wizard vs. CLI - chose web for accessibility  
→ **Accepted:** More complex implementation, but critical for shared hosting without SSH

**[Trade-off]** Pre-built frontend vs. build-on-server - chose pre-built  
→ **Accepted:** Larger tar archive size, but eliminates npm dependency on shared host

**[Trade-off]** Single .htaccess vs. multiple - chose multiple for security  
→ **Accepted:** More configuration files, but better security isolation

**[Trade-off]** Automatic migrations vs. manual - chose automatic  
→ **Accepted:** Risk of migration failure during install, but better UX and fewer manual steps

## Migration Plan

**Pre-Deployment (Developer):**
1. Run build script: `./build-deployment.sh`
2. Verify tar archive created: `homocanis-deployment-YYYYMMDD-HHMMSS.tar.gz`
3. Test in staging environment (optional but recommended)

**Deployment (Provider):**
1. Upload tar.gz to shared hosting via FTP/web file manager
2. Extract tar.gz in desired directory (e.g., `public_html/homocanis/`)
3. Access installer: `https://domain.com/homocanis/install.php`
4. Follow wizard steps:
   - Review server requirements
   - Enter database credentials
   - Configure application settings
   - Confirm installation
5. DELETE install.php or verify it's locked
6. Access application: `https://domain.com/homocanis/`

**Rollback Strategy:**
1. If installation fails partway, installer offers automatic rollback
2. Manual rollback: delete extracted directory, re-upload fresh tar
3. If production site already running, rollback = restore previous directory

**Validation:**
1. Access application URL, verify homepage loads
2. Test login functionality
3. Verify database connectivity (create test data)
4. Check file upload functionality (tests storage permissions)
5. Review install.log for any warnings

## Open Questions

1. **Should the build script support multiple environments (staging, production)?**
   - Consideration: Different .env templates, different builds
   - Decision: Initially single production build; can extend later

2. **How to handle future updates to deployed applications?**
   - Consideration: Upload new tar, run update wizard?, database migrations?
   - Decision: Out of scope for initial implementation; document manual update process

3. **Should frontend and backend be separately deployable?**
   - Consideration: Flexibility vs. complexity
   - Decision: Initial implementation deploys as single package; separate deployment is future enhancement

4. **How to handle multiple domain deployments (multi-tenant)?**
   - Consideration: Same codebase, different databases/configs
   - Decision: Out of scope; each deployment is independent installation

5. **Should installer support migrating from manual deployment?**
   - Consideration: Import existing .env, preserve database
   - Decision: Out of scope initially; document migration steps if needed
