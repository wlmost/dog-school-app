## 1. Initial Setup

- [x] 1.1 Create `backend/requirements-check.php` file with basic PHP structure
- [x] 1.2 Add HTML document structure with DOCTYPE, head, and body
- [x] 1.3 Add CSS styles for color-coded status indicators (green/pass, red/fail, yellow/warning)
- [x] 1.4 Add responsive CSS for mobile, tablet, and desktop layouts
- [x] 1.5 Add meta tags for charset and viewport

## 2. PHP Version Validation

- [x] 2.1 Implement PHP version detection using `PHP_VERSION` constant
- [x] 2.2 Parse version into major, minor, patch components
- [x] 2.3 Add logic to validate PHP >= 8.4.0
- [x] 2.4 Add warning logic for PHP > 8.4.x (untested versions)
- [x] 2.5 Create result array structure for version check
- [x] 2.6 Add version check to HTML output with status indicator

## 3. PHP Extension Validation

- [x] 3.1 Define array of required extensions with descriptions
- [x] 3.2 Define array of recommended extensions with descriptions
- [x] 3.3 Implement extension checking using `extension_loaded()` function
- [x] 3.4 Check all required extensions: json, mbstring, openssl, pdo, pdo_mysql, tokenizer, xml, ctype, fileinfo, filter, hash, curl, zip
- [x] 3.5 Check recommended extensions: bcmath, gd/imagick
- [x] 3.6 Build results array with pass/fail/warning status for each extension
- [x] 3.7 Add extension results to HTML output with individual status indicators
- [x] 3.8 Display missing extensions prominently in failed section

## 4. File Permission Validation

- [x] 4.1 Define array of required writable directories: storage/, bootstrap/cache/, public/storage
- [x] 4.2 Implement directory existence check
- [x] 4.3 Implement write permission test using temporary file creation
- [x] 4.4 Create temporary test file in each directory
- [x] 4.5 Verify test file was created successfully
- [x] 4.6 Delete test file and verify deletion succeeded
- [x] 4.7 Get current directory permissions using `fileperms()` and format as octal
- [x] 4.8 Add permission results to HTML output with current and recommended permissions
- [x] 4.9 Handle case where directory doesn't exist

## 5. Database Connectivity (Optional)

- [x] 5.1 Check if `.env` file exists and is readable
- [x] 5.2 Parse `.env` file for DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD
- [x] 5.3 Create HTML form for manual database credential entry
- [x] 5.4 Handle form submission and sanitize input
- [x] 5.5 Implement PDO connection attempt with try-catch error handling
- [x] 5.6 Test database connection using provided credentials
- [x] 5.7 Query MySQL version using `SELECT VERSION()`
- [x] 5.8 Parse MySQL version and validate (8.0+ pass, 5.7 warning, <5.7 fail)
- [x] 5.9 Add database results to HTML output
- [x] 5.10 Handle connection failures with error message display
- [x] 5.11 Add option to skip database test if no credentials provided

## 6. HTML Output and UI

- [x] 6.1 Create summary section showing overall pass/fail status
- [x] 6.2 Implement visual status indicators: ✓ (pass), ✗ (fail), ⚠ (warning)
- [x] 6.3 Add color coding to summary section
- [x] 6.4 Create detailed sections: PHP Version, Extensions, Permissions, Database
- [x] 6.5 Add collapsible/expandable sections for detailed results
- [x] 6.6 Display all results in organized table or card layout
- [x] 6.7 Add timestamp showing when check was performed
- [x] 6.8 Add script version and date stamp to footer

## 7. Remediation Guidance

- [x] 7.1 Add remediation text for PHP version mismatch
- [x] 7.2 Add remediation text for each missing required extension
- [x] 7.3 Add remediation text for missing recommended extensions
- [x] 7.4 Add remediation text for permission issues with specific chmod commands
- [x] 7.5 Add remediation text for missing directories
- [x] 7.6 Add remediation text for database connection failures
- [x] 7.7 Add remediation text for MySQL version issues
- [x] 7.8 Include links to PHP documentation where applicable
- [x] 7.9 Add provider-specific notes for common shared hosting providers

## 8. Security and Production Considerations

- [x] 8.1 Add prominent warning banner to delete script after installation
- [x] 8.2 Ensure database credentials are not logged or displayed in plain text
- [x] 8.3 Sanitize all user input from database credential form
- [x] 8.4 Limit system information disclosure to necessary data only
- [x] 8.5 Add option to restrict access by IP address (commented out by default)
- [x] 8.6 Add comment explaining security implications at top of file

## 9. Testing and Documentation

- [x] 9.1 Test script on PHP 8.4.x environment
- [x] 9.2 Test script with all required extensions present
- [x] 9.3 Test script with missing extensions to verify error display
- [x] 9.4 Test permission checks on writable and non-writable directories
- [x] 9.5 Test database connectivity with valid and invalid credentials
- [x] 9.6 Test on mobile device to verify responsive layout
- [x] 9.7 Add inline code comments explaining key logic
- [x] 9.8 Add usage instructions in file header comment
- [x] 9.9 Update DEPLOYMENT.md or README with instructions for using the script
- [x] 9.10 Add script to .gitignore if it should not be version controlled
