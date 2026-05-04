<?php
/**
 * HomoCanis Installation Wizard
 * 
 * Automated installation wizard for shared hosting deployment.
 * Guides users through server validation, database configuration,
 * environment setup, and application initialization.
 * 
 * @version 1.0.0
 */

// Start session for multi-step progress tracking
session_start();

// Configuration
define('INSTALL_LOCK_FILE', __DIR__ . '/install.lock');
define('INSTALL_LOG_FILE', __DIR__ . '/install.log');
define('BACKEND_DIR', __DIR__ . '/backend');
define('ENV_TEMPLATE', BACKEND_DIR . '/.env.template');
define('ENV_FILE', BACKEND_DIR . '/.env');
define('REQUIREMENTS_CHECK', BACKEND_DIR . '/requirements-check.php');

// Installation step constants
define('STEP_WELCOME', 1);
define('STEP_REQUIREMENTS', 2);
define('STEP_DATABASE', 3);
define('STEP_APPLICATION', 4);
define('STEP_SETUP', 5);
define('STEP_MIGRATE', 6);
define('STEP_COMPLETE', 7);

/**
 * Logging function
 */
function logMessage($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$level] $message\n";
    file_put_contents(INSTALL_LOG_FILE, $logEntry, FILE_APPEND);
}

/**
 * Check if installation is locked
 */
function checkInstallationLock() {
    // Debug mode: Show what we're checking
    if (isset($_GET['debug'])) {
        echo "<!DOCTYPE html><html><head><title>Debug Info</title></head><body>";
        echo "<h1>Installation Lock Debug</h1>";
        echo "<h2>Paths Being Checked:</h2>";
        echo "<p><strong>__DIR__:</strong> <code>" . __DIR__ . "</code></p>";
        echo "<p><strong>INSTALL_LOCK_FILE:</strong> <code>" . INSTALL_LOCK_FILE . "</code></p>";
        echo "<p><strong>ENV_FILE:</strong> <code>" . ENV_FILE . "</code></p>";
        echo "<h2>File Existence:</h2>";
        echo "<p>install.lock exists: " . (file_exists(INSTALL_LOCK_FILE) ? "YES ✓" : "NO ✗") . "</p>";
        echo "<p>backend/.env exists: " . (file_exists(ENV_FILE) ? "YES ✓" : "NO ✗") . "</p>";
        echo "<h2>Session State:</h2>";
        echo "<p>Current step: " . (isset($_SESSION['install_step']) ? $_SESSION['install_step'] : 'none') . "</p>";
        echo "<h2>Directory Contents:</h2>";
        echo "<p><strong>Root directory files:</strong></p><pre>";
        print_r(scandir(__DIR__));
        echo "</pre>";
        if (is_dir(BACKEND_DIR)) {
            echo "<p><strong>Backend directory files (filtered .env*):</strong></p><pre>";
            $files = scandir(BACKEND_DIR);
            foreach ($files as $file) {
                if (strpos($file, '.env') !== false) {
                    echo "$file\n";
                }
            }
            echo "</pre>";
        }
        echo "<p><a href='install.php'>Back to installer</a> | <a href='install.php?reset'>Reset Session</a></p>";
        echo "</body></html>";
        exit;
    }
    
    // Reset parameter: Force session reset
    if (isset($_GET['reset'])) {
        session_destroy();
        session_start();
        logMessage('Session manually reset via ?reset parameter');
        header('Location: install.php');
        exit;
    }
    
    // Check for lock files
    $lockExists = file_exists(INSTALL_LOCK_FILE);
    $envExists = file_exists(ENV_FILE);

    // install.lock always means fully completed → lock
    if ($lockExists) {
        showLockedScreen();
        exit;
    }

    // .env exists: only lock if there is NO active installation session in progress.
    // During a running installation the .env is written at STEP_SETUP (step 5)
    // and the user still needs to proceed to STEP_MIGRATE (step 6) and STEP_COMPLETE (step 7).
    if ($envExists) {
        $activeStep = isset($_SESSION['install_step']) ? (int)$_SESSION['install_step'] : 0;
        // Include STEP_COMPLETE itself: the .env is written at step 5, but the lock file
        // is only created when stepComplete() actually runs. Until then the user must
        // be allowed to reach step 7 without being locked out.
        $installationInProgress = $activeStep > STEP_WELCOME && $activeStep <= STEP_COMPLETE;
        if (!$installationInProgress) {
            showLockedScreen();
            exit;
        }
    }
    
    // Auto-reset session if no lock files exist but session indicates completion
    // This handles the case where user deleted files but browser still has old session
    if (!$lockExists && !$envExists && isset($_SESSION['install_step'])) {
        $currentStep = (int)$_SESSION['install_step'];
        // If session says we're at COMPLETE (step 7) but no lock files exist,
        // the installation was cleaned up - reset session
        if ($currentStep === STEP_COMPLETE) {
            logMessage('Auto-resetting session: No lock files but step was COMPLETE');
            session_destroy();
            session_start();
            // Redirect to fresh start
            header('Location: install.php');
            exit;
        }
    }
}

/**
 * Show locked installation screen
 */
function showLockedScreen() {
    $lockExists = file_exists(INSTALL_LOCK_FILE);
    $envExists = file_exists(ENV_FILE);
    
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Installation Already Complete</title>
        <style><?php echo getStyles(); ?></style>
    </head>
    <body>
        <div class="container">
            <div class="card">
                <div class="header error-header">
                    <h1>⚠ Installation Locked</h1>
                </div>
                <div class="content">
                    <p><strong>The installation appears to be already completed or in progress.</strong></p>
                    <p>The following files were detected:</p>
                    <ul style="list-style: none; padding-left: 0;">
                        <?php if ($lockExists): ?>
                            <li style="margin: 10px 0;">✓ Lock file: <code><?php echo INSTALL_LOCK_FILE; ?></code></li>
                        <?php endif; ?>
                        <?php if ($envExists): ?>
                            <li style="margin: 10px 0;">✓ Configuration file: <code><?php echo ENV_FILE; ?></code></li>
                        <?php endif; ?>
                    </ul>
                    
                    <h3 style="margin-top: 30px;">To reinstall:</h3>
                    <ol style="margin-left: 20px;">
                        <?php if ($lockExists): ?>
                            <li>Delete <code>install.lock</code></li>
                        <?php endif; ?>
                        <?php if ($envExists): ?>
                            <li>Delete <code>backend/.env</code></li>
                        <?php endif; ?>
                        <li>Refresh this page</li>
                    </ol>
                    
                    <div class="alert alert-error" style="margin-top: 20px;">
                        <strong>⚠ Warning:</strong> Reinstalling will overwrite your current configuration!
                    </div>
                    
                    <form method="post" style="margin-top: 30px;">
                        <button type="submit" name="action" value="force_unlock" class="btn btn-danger">Force Unlock (Advanced)</button>
                    </form>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}

/**
 * Get current installation step
 */
function getCurrentStep() {
    return isset($_SESSION['install_step']) ? (int)$_SESSION['install_step'] : STEP_WELCOME;
}

/**
 * Set installation step
 */
function setStep($step) {
    $_SESSION['install_step'] = $step;
    logMessage("Moving to step $step");
}

/**
 * Get session data
 */
function getSessionData($key, $default = null) {
    return isset($_SESSION['install_data'][$key]) ? $_SESSION['install_data'][$key] : $default;
}

/**
 * Set session data
 */
function setSessionData($key, $value) {
    if (!isset($_SESSION['install_data'])) {
        $_SESSION['install_data'] = [];
    }
    $_SESSION['install_data'][$key] = $value;
}

/**
 * Get CSS styles
 */
function getStyles() {
    return <<<CSS
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            line-height: 1.6;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2em;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 1.1em;
        }
        
        .error-header {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .success-header {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        .content {
            padding: 30px;
        }
        
        .progress-bar {
            background: #e0e0e0;
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .progress-fill {
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            height: 100%;
            transition: width 0.3s ease;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 1em;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group small {
            display: block;
            margin-top: 5px;
            color: #666;
            font-size: 0.9em;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 6px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border-left: 4px solid #ffc107;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }
        
        .requirement-item {
            display: flex;
            align-items: center;
            padding: 10px;
            margin-bottom: 8px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        .requirement-status {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            margin-right: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
        }
        
        .status-pass {
            background: #28a745;
            color: white;
        }
        
        .status-fail {
            background: #dc3545;
            color: white;
        }
        
        .status-warn {
            background: #ffc107;
            color: #333;
        }
        
        .requirement-name {
            flex: 1;
            font-weight: 500;
        }
        
        .requirement-value {
            color: #666;
            font-size: 0.9em;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            margin: 15px 0;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin-right: 10px;
        }
        
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
        }
        
        .warning-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
        }
        
        @media (max-width: 600px) {
            .header h1 {
                font-size: 1.5em;
            }
            
            .content {
                padding: 20px;
            }
            
            .btn-group {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }
CSS;
}

/**
 * Render page header
 */
function renderHeader($title, $subtitle = '', $progress = null) {
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($title); ?> - HomoCanis Installation</title>
        <style><?php echo getStyles(); ?></style>
    </head>
    <body>
        <div class="container">
            <div class="card">
                <div class="header">
                    <h1><?php echo htmlspecialchars($title); ?></h1>
                    <?php if ($subtitle): ?>
                        <p><?php echo htmlspecialchars($subtitle); ?></p>
                    <?php endif; ?>
                </div>
                <?php if ($progress !== null): ?>
                    <div style="padding: 0 30px 20px 30px;">
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="content">
    <?php
}

/**
 * Render page footer
 */
function renderFooter() {
    ?>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}

/**
 * Step 1: Welcome Screen
 */
function stepWelcome() {
    renderHeader('Welcome to HomoCanis', 'Shared Hosting Installation Wizard', 0);
    
    ?>
    <h2>🎉 Welcome!</h2>
    <p>This wizard will guide you through the installation process for HomoCanis on your shared hosting server.</p>
    
    <h3 style="margin-top: 30px;">Installation Steps:</h3>
    <ol>
        <li><strong>Server Requirements</strong> - Verify your server meets all requirements</li>
        <li><strong>Database Configuration</strong> - Configure your MySQL database connection</li>
        <li><strong>Application Settings</strong> - Set up basic application configuration</li>
        <li><strong>Environment Setup</strong> - Create directories and set permissions</li>
        <li><strong>Database Migration</strong> - Initialize the database schema</li>
        <li><strong>Complete</strong> - Finalize installation and secure the installer</li>
    </ol>
    
    <div class="alert alert-info" style="margin-top: 30px;">
        <strong>ℹ Note:</strong> Please ensure you have your database credentials ready before proceeding.
    </div>
    
    <form method="post">
        <div class="btn-group">
            <button type="submit" name="action" value="start" class="btn">Start Installation →</button>
        </div>
    </form>
    <?php
    
    renderFooter();
}

// Main execution

// Handle force_unlock BEFORE the lock check, so the POST can always reach it
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'force_unlock') {
    if (file_exists(INSTALL_LOCK_FILE)) {
        @unlink(INSTALL_LOCK_FILE);
        logMessage('Force unlock: Removed install.lock');
    }
    if (file_exists(ENV_FILE)) {
        @unlink(ENV_FILE);
        logMessage('Force unlock: Removed .env');
    }
    session_destroy();
    session_start();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Handle delete_installer BEFORE the lock check: install.lock already exists at this point
// (created by stepComplete), so the lock check would block the POST otherwise.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_installer') {
    $appUrl = getSessionData('app_url', '/');
    $newName = __FILE__ . '.completed';
    if (@rename(__FILE__, $newName)) {
        logMessage('Installer renamed to: ' . $newName);
    } else {
        logMessage('Could not rename installer – deleting instead', 'WARN');
        @unlink(__FILE__);
    }
    header('Location: ' . $appUrl);
    exit;
}

checkInstallationLock();

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'start':
            setStep(STEP_REQUIREMENTS);
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
            
        case 'back_to_welcome':
            setStep(STEP_WELCOME);
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
            
        case 'back_to_requirements':
            setStep(STEP_REQUIREMENTS);
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
            
        case 'proceed_database':
            setStep(STEP_DATABASE);
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
            
        case 'back_to_database':
            setStep(STEP_DATABASE);
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
            
        case 'proceed_application':
            // Save application settings
            setSessionData('app_name', $_POST['app_name'] ?? 'HomoCanis');
            setSessionData('app_url', $_POST['app_url'] ?? '');
            setSessionData('app_env', $_POST['app_env'] ?? 'production');
            setSessionData('app_timezone', $_POST['app_timezone'] ?? 'Europe/Berlin');
            setStep(STEP_APPLICATION);
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
            
        case 'back_to_application':
            setStep(STEP_APPLICATION);
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
            
        case 'proceed_setup':
            // Save application settings from form
            if (isset($_POST['app_name'])) {
                setSessionData('app_name', $_POST['app_name']);
                setSessionData('app_url', $_POST['app_url']);
                setSessionData('app_env', $_POST['app_env']);
                setSessionData('app_timezone', $_POST['app_timezone']);
            }
            setStep(STEP_SETUP);
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
            
        case 'back_to_setup':
            unset($_SESSION['setup_complete']);
            setStep(STEP_SETUP);
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
            
        case 'proceed_migrate':
            setStep(STEP_MIGRATE);
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
            
        case 'run_migration':
            // Stay on STEP_MIGRATE – stepMigrate() will process POST data
            break;

        case 'proceed_complete':
            setStep(STEP_COMPLETE);
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
            
        case 'force_unlock':
            // Force unlock by removing lock files
            if (file_exists(INSTALL_LOCK_FILE)) {
                @unlink(INSTALL_LOCK_FILE);
                logMessage('Force unlock: Removed install.lock');
            }
            if (file_exists(ENV_FILE)) {
                @unlink(ENV_FILE);
                logMessage('Force unlock: Removed .env');
            }
            // Clear session
            session_destroy();
            session_start();
            // Redirect to start fresh
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
            
        case 'rollback':
            performRollback();
            break;
            
        case 'delete_installer':
            // Rename installer
            $newName = __FILE__ . '.completed';
            @rename(__FILE__, $newName);
            logMessage('Installer renamed to: ' . $newName);
            
            // Redirect to application
            header('Location: ' . getSessionData('app_url', '/'));
            exit;
    }
}

// Display current step
$currentStep = getCurrentStep();

switch ($currentStep) {
    case STEP_WELCOME:
        stepWelcome();
        break;
        
    case STEP_REQUIREMENTS:
        stepRequirements();
        break;
        
    case STEP_DATABASE:
        stepDatabase();
        break;
        
    case STEP_APPLICATION:
        stepApplication();
        break;
        
    case STEP_SETUP:
        stepSetup();
        break;
        
    case STEP_MIGRATE:
        stepMigrate();
        break;
        
    case STEP_COMPLETE:
        stepComplete();
        break;
        
    default:
        setStep(STEP_WELCOME);
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
}

/**
 * Step 2: Server Requirements Check
 */
function stepRequirements() {
    renderHeader('Server Requirements', 'Checking server compatibility', 14);
    
    // Run server requirements check
    $requirements = checkServerRequirements();
    $canProceed = $requirements['can_proceed'];
    
    ?>
    <h2>📋 Server Requirements Check</h2>
    
    <?php if ($canProceed): ?>
        <div class="alert alert-success">
            <strong>✓ All critical requirements met!</strong> Your server is compatible with HomoCanis.
        </div>
    <?php else: ?>
        <div class="alert alert-error">
            <strong>✗ Some critical requirements are not met.</strong> Please resolve the issues below before continuing.
        </div>
    <?php endif; ?>
    
    <h3 style="margin-top: 20px;">PHP Version</h3>
    <div class="requirement-item">
        <div class="requirement-status status-<?php echo $requirements['php_version']['status']; ?>">
            <?php echo $requirements['php_version']['status'] === 'pass' ? '✓' : '✗'; ?>
        </div>
        <div class="requirement-name">PHP <?php echo $requirements['php_version']['required']; ?>+</div>
        <div class="requirement-value"><?php echo $requirements['php_version']['current']; ?></div>
    </div>
    
    <h3 style="margin-top: 20px;">Required Extensions</h3>
    <?php foreach ($requirements['extensions']['required'] as $ext): ?>
        <div class="requirement-item">
            <div class="requirement-status status-<?php echo $ext['status']; ?>">
                <?php echo $ext['status'] === 'pass' ? '✓' : '✗'; ?>
            </div>
            <div class="requirement-name"><?php echo $ext['name']; ?></div>
            <div class="requirement-value"><?php echo $ext['loaded'] ? 'Loaded' : 'Missing'; ?></div>
        </div>
    <?php endforeach; ?>
    
    <?php if (!empty($requirements['extensions']['recommended'])): ?>
        <h3 style="margin-top: 20px;">Recommended Extensions</h3>
        <?php foreach ($requirements['extensions']['recommended'] as $ext): ?>
            <div class="requirement-item">
                <div class="requirement-status status-<?php echo $ext['status']; ?>">
                    <?php echo $ext['status'] === 'pass' ? '✓' : '⚠'; ?>
                </div>
                <div class="requirement-name"><?php echo $ext['name']; ?></div>
                <div class="requirement-value"><?php echo $ext['loaded'] ? 'Loaded' : 'Missing'; ?></div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <form method="post">
        <div class="btn-group">
            <button type="submit" name="action" value="back_to_welcome" class="btn btn-secondary">← Back</button>
            <button type="submit" name="action" value="proceed_database" class="btn" <?php echo !$canProceed ? 'disabled' : ''; ?>>
                Next: Database Configuration →
            </button>
        </div>
    </form>
    <?php
    
    renderFooter();
}

/**
 * Check server requirements
 */
function checkServerRequirements() {
    $result = [
        'can_proceed' => true,
        'php_version' => [
            'required' => '8.4',
            'current' => PHP_VERSION,
            'status' => version_compare(PHP_VERSION, '8.4.0', '>=') ? 'pass' : 'fail'
        ],
        'extensions' => [
            'required' => [],
            'recommended' => []
        ]
    ];
    
    // Required extensions
    $requiredExtensions = [
        'pdo', 'pdo_mysql', 'mbstring', 'openssl', 'tokenizer',
        'xml', 'ctype', 'json', 'bcmath', 'fileinfo', 'curl', 'zip'
    ];
    
    foreach ($requiredExtensions as $ext) {
        $loaded = extension_loaded($ext);
        $result['extensions']['required'][] = [
            'name' => $ext,
            'loaded' => $loaded,
            'status' => $loaded ? 'pass' : 'fail'
        ];
        if (!$loaded) {
            $result['can_proceed'] = false;
        }
    }
    
    // Recommended extensions
    $recommendedExtensions = ['gd', 'intl', 'exif'];
    foreach ($recommendedExtensions as $ext) {
        $loaded = extension_loaded($ext);
        $result['extensions']['recommended'][] = [
            'name' => $ext,
            'loaded' => $loaded,
            'status' => $loaded ? 'pass' : 'warn'
        ];
    }
    
    if ($result['php_version']['status'] === 'fail') {
        $result['can_proceed'] = false;
    }
    
    return $result;
}

/**
 * Step 3: Database Configuration
 */
function stepDatabase() {
    $errors = [];
    $success = false;
    
    // Handle database test
    if (isset($_POST['test_connection'])) {
        $dbHost   = $_POST['db_host']   ?? 'localhost';
        $dbPort   = $_POST['db_port']   ?? '3306';
        $dbName   = $_POST['db_name']   ?? '';
        $dbUser   = $_POST['db_user']   ?? '';
        $dbPass   = $_POST['db_pass']   ?? '';
        // Sanitize prefix: only lowercase alphanumeric and underscores
        $dbPrefix = preg_replace('/[^a-z0-9_]/', '', strtolower($_POST['db_prefix'] ?? ''));
        
        $testResult = testDatabaseConnection($dbHost, $dbPort, $dbName, $dbUser, $dbPass);
        
        if ($testResult['success']) {
            $success = true;
            // Save to session
            setSessionData('db_host',   $dbHost);
            setSessionData('db_port',   $dbPort);
            setSessionData('db_name',   $dbName);
            setSessionData('db_user',   $dbUser);
            setSessionData('db_pass',   $dbPass);
            setSessionData('db_prefix', $dbPrefix);
        } else {
            $errors[] = $testResult['error'];
        }
    }
    
    renderHeader('Database Configuration', 'Configure MySQL connection', 28);
    
    ?>
    <h2>🗄 Database Configuration</h2>
    <p>Enter your MySQL database credentials. We'll test the connection before proceeding.</p>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <strong>Connection Failed:</strong><br>
            <?php foreach ($errors as $error): ?>
                <?php echo htmlspecialchars($error); ?><br>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success">
            <strong>✓ Database connection successful!</strong> Database exists and is accessible.
        </div>
    <?php endif; ?>
    
    <form method="post">
        <div class="form-group">
            <label>Database Host</label>
            <input type="text" name="db_host" value="<?php echo htmlspecialchars(getSessionData('db_host', 'localhost')); ?>" required>
            <small>Usually "localhost" on shared hosting</small>
        </div>
        
        <div class="form-group">
            <label>Database Port</label>
            <input type="number" name="db_port" value="<?php echo htmlspecialchars(getSessionData('db_port', '3306')); ?>" required>
            <small>Default MySQL port is 3306</small>
        </div>
        
        <div class="form-group">
            <label>Database Name</label>
            <input type="text" name="db_name" value="<?php echo htmlspecialchars(getSessionData('db_name', '')); ?>" required>
            <small>The name of your MySQL database</small>
        </div>
        
        <div class="form-group">
            <label>Database Username</label>
            <input type="text" name="db_user" value="<?php echo htmlspecialchars(getSessionData('db_user', '')); ?>" required>
        </div>
        
        <div class="form-group">
            <label>Database Password</label>
            <input type="password" name="db_pass" value="<?php echo htmlspecialchars(getSessionData('db_pass', '')); ?>">
        </div>

        <div class="form-group">
            <label>Tabellenpräfix <small>(optional)</small></label>
            <input type="text" name="db_prefix" value="<?php echo htmlspecialchars(getSessionData('db_prefix', '')); ?>"
                   pattern="[a-z0-9_]*" placeholder="z.B. ds_ (leer lassen für kein Präfix)">
            <small>Präfix für alle Tabellennamen. Nützlich wenn mehrere Anwendungen dieselbe Datenbank teilen. Beispiel: "ds_" → ds_users, ds_dogs, ...</small>
        </div>
        
        <div class="btn-group">
            <button type="submit" name="action" value="back_to_requirements" class="btn btn-secondary">← Back</button>
            <button type="submit" name="test_connection" value="1" class="btn btn-secondary">Test Connection</button>
            <button type="submit" name="action" value="proceed_application" class="btn" <?php echo !$success ? 'disabled' : ''; ?>>
                Next: Application Settings →
            </button>
        </div>
    </form>
    <?php
    
    renderFooter();
}

/**
 * Test database connection
 */
function testDatabaseConnection($host, $port, $dbName, $user, $pass) {
    try {
        $dsn = "mysql:host=$host;port=$port;dbname=$dbName;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        
        // Test a simple query
        $pdo->query('SELECT 1');
        
        return ['success' => true];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Step 4: Application Configuration
 */
function stepApplication() {
    renderHeader('Application Settings', 'Configure your application', 42);
    
    // Auto-detect URL
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    $autoUrl = $protocol . '://' . $host . $scriptDir;
    
    ?>
    <h2>⚙ Application Settings</h2>
    <p>Configure basic application settings.</p>
    
    <form method="post">
        <div class="form-group">
            <label>Application Name</label>
            <input type="text" name="app_name" value="<?php echo htmlspecialchars(getSessionData('app_name', 'HomoCanis')); ?>" required>
            <small>The name of your application</small>
        </div>
        
        <div class="form-group">
            <label>Application URL</label>
            <input type="url" name="app_url" value="<?php echo htmlspecialchars(getSessionData('app_url', $autoUrl)); ?>" required>
            <small>The full URL where your application will be accessible</small>
        </div>
        
        <div class="form-group">
            <label>Environment</label>
            <select name="app_env" required>
                <option value="production" <?php echo getSessionData('app_env', 'production') === 'production' ? 'selected' : ''; ?>>Production</option>
                <option value="local" <?php echo getSessionData('app_env') === 'local' ? 'selected' : ''; ?>>Local/Development</option>
            </select>
            <small>Use "production" for live servers</small>
        </div>
        
        <div class="form-group">
            <label>Timezone</label>
            <select name="app_timezone" required>
                <?php
                $timezones = timezone_identifiers_list();
                $selected = getSessionData('app_timezone', 'Europe/Berlin');
                foreach ($timezones as $tz) {
                    $sel = $tz === $selected ? 'selected' : '';
                    echo "<option value=\"$tz\" $sel>$tz</option>";
                }
                ?>
            </select>
        </div>
        
        <div class="btn-group">
            <button type="submit" name="action" value="back_to_database" class="btn btn-secondary">← Back</button>
            <button type="submit" name="action" value="proceed_setup" class="btn">Next: Environment Setup →</button>
        </div>
    </form>
    <?php
    
    renderFooter();
}

/**
 * Step 5: Environment Setup
 */
function stepSetup() {
    renderHeader('Environment Setup', 'Creating directories and configuring environment', 56);
    
    $setupResults = [];
    $hasErrors = false;
    
    // Perform setup actions
    if (!isset($_SESSION['setup_complete'])) {
        // Create .env file
        $envResult = createEnvFile();
        $setupResults[] = $envResult;
        if (!$envResult['success']) $hasErrors = true;
        
        // Configure .htaccess files based on APP_URL
        $htaccessResult = configureHtaccess();
        $setupResults[] = $htaccessResult;
        if (!$htaccessResult['success'] && isset($htaccessResult['critical']) && $htaccessResult['critical']) {
            $hasErrors = true;
        }
        
        // Create directories
        $dirResult = createDirectories();
        $setupResults[] = $dirResult;
        if (!$dirResult['success']) $hasErrors = true;
        
        // Set permissions
        $permResult = setPermissions();
        $setupResults[] = $permResult;
        
        $_SESSION['setup_complete'] = true;
    }
    
    ?>
    <h2>🔧 Environment Setup</h2>
    <p>Setting up directories, permissions, and configuration files...</p>
    
    <?php foreach ($setupResults as $result): ?>
        <div class="alert alert-<?php echo $result['success'] ? 'success' : ($result['critical'] ?? true ? 'error' : 'warning'); ?>">
            <strong><?php echo $result['success'] ? '✓' : '✗'; ?> <?php echo htmlspecialchars($result['message']); ?></strong>
            <?php if (isset($result['details'])): ?>
                <br><small><?php echo htmlspecialchars($result['details']); ?></small>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    
    <form method="post">
        <div class="btn-group">
            <button type="submit" name="action" value="back_to_application" class="btn btn-secondary">← Back</button>
            <?php if ($hasErrors): ?>
                <button type="submit" name="action" value="rollback" class="btn btn-danger">Rollback Installation</button>
            <?php else: ?>
                <button type="submit" name="action" value="proceed_migrate" class="btn">Next: Database Migration →</button>
            <?php endif; ?>
        </div>
    </form>
    <?php
    
    renderFooter();
}

/**
 * Create .env file from template
 */
function createEnvFile() {
    try {
        if (!file_exists(ENV_TEMPLATE)) {
            return [
                'success' => false,
                'critical' => true,
                'message' => '.env template file not found',
                'details' => ENV_TEMPLATE
            ];
        }
        
        $template = file_get_contents(ENV_TEMPLATE);
        
        // Generate APP_KEY
        $appKey = 'base64:' . base64_encode(random_bytes(32));
        
        // Simple key=value replacements for lines that exist uncommented
        $replacements = [
            'APP_NAME=Laravel' => 'APP_NAME="' . getSessionData('app_name', 'HomoCanis') . '"',
            'APP_ENV=local'    => 'APP_ENV=' . getSessionData('app_env', 'production'),
            'APP_DEBUG=true'   => 'APP_DEBUG=' . (getSessionData('app_env') === 'production' ? 'false' : 'true'),
            'APP_URL=http://localhost' => 'APP_URL=' . getSessionData('app_url', 'http://localhost'),
            'APP_KEY='         => 'APP_KEY=' . $appKey,
            'APP_TIMEZONE=UTC' => 'APP_TIMEZONE=' . getSessionData('app_timezone', 'Europe/Berlin'),
        ];
        
        foreach ($replacements as $search => $replace) {
            $template = str_replace($search, $replace, $template);
        }
        
        // Force-set DB settings via regex, handling both commented (#) and uncommented variants,
        // and also covering sqlite/pgsql/mysql defaults in DB_CONNECTION.
        $dbConnection = 'mysql';
        $dbHost       = getSessionData('db_host', 'localhost');
        $dbPort       = getSessionData('db_port', '3306');
        $dbName       = getSessionData('db_name', '');
        $dbUser       = getSessionData('db_user', '');
        $dbPass       = getSessionData('db_pass', '');
        $dbPrefix     = getSessionData('db_prefix', '');

        $dbReplacements = [
            '/^#?\s*DB_CONNECTION\s*=.*$/m' => 'DB_CONNECTION=' . $dbConnection,
            '/^#?\s*DB_HOST\s*=.*$/m'       => 'DB_HOST=' . $dbHost,
            '/^#?\s*DB_PORT\s*=.*$/m'       => 'DB_PORT=' . $dbPort,
            '/^#?\s*DB_DATABASE\s*=.*$/m'   => 'DB_DATABASE=' . $dbName,
            '/^#?\s*DB_USERNAME\s*=.*$/m'   => 'DB_USERNAME=' . $dbUser,
            '/^#?\s*DB_PASSWORD\s*=.*$/m'   => 'DB_PASSWORD=' . $dbPass,
            '/^#?\s*DB_PREFIX\s*=.*$/m'     => 'DB_PREFIX=' . $dbPrefix,
        ];
        
        foreach ($dbReplacements as $pattern => $replace) {
            $new = preg_replace($pattern, $replace, $template);
            // If the key didn't exist at all, append it
            if ($new === $template && strpos($template, ltrim(explode('=', $replace)[0])) === false) {
                $template .= "\n" . $replace;
            } else {
                $template = $new;
            }
        }
        
        // Write .env file
        file_put_contents(ENV_FILE, $template);
        chmod(ENV_FILE, 0644);
        
        logMessage('.env file created successfully');
        logMessage("DB settings: connection=$dbConnection host=$dbHost port=$dbPort db=$dbName user=$dbUser prefix=$dbPrefix");
        
        return [
            'success' => true,
            'message' => '.env file created',
            'details' => ENV_FILE
        ];
    } catch (Exception $e) {
        logMessage('Failed to create .env file: ' . $e->getMessage(), 'ERROR');
        return [
            'success' => false,
            'critical' => true,
            'message' => 'Failed to create .env file',
            'details' => $e->getMessage()
        ];
    }
}

/**
 * Configure .htaccess files based on APP_URL
 *
 * Replaces the install-mode root .htaccess (which routes everything to
 * backend/public/) with a production .htaccess that:
 *  - Routes /api/*, /sanctum/*, /broadcasting/* to Laravel
 *  - Routes /storage/* to the backend public storage symlink
 *  - Serves static assets directly from frontend/dist/
 *  - Falls back to frontend/dist/index.html for Vue SPA routing
 */
function configureHtaccess() {
    try {
        $appUrl = getSessionData('app_url', '');

        // Parse URL to get the base path (empty for root installs)
        $parsedUrl = parse_url($appUrl);
        $path = isset($parsedUrl['path']) ? rtrim($parsedUrl['path'], '/') : '';
        $rewriteBase = $path ?: '/';

        logMessage("Configuring .htaccess with RewriteBase: $rewriteBase");

        // Write production root .htaccess (replaces the install-mode routing)
        $rootHtaccess = dirname(__FILE__) . '/.htaccess';
        $rootContent = '<IfModule mod_rewrite.c>' . "\n"
            . '    RewriteEngine On' . "\n"
            . '    RewriteBase ' . $rewriteBase . "\n"
            . "\n"
            . '    # Allow direct access to install.php (shows locked screen post-install)' . "\n"
            . '    RewriteRule ^install\.php$ - [L]' . "\n"
            . "\n"
            . '    # Route storage requests to backend public storage symlink' . "\n"
            . '    RewriteRule ^storage/(.*)$ backend/public/storage/$1 [L]' . "\n"
            . "\n"
            . '    # Route API and backend-specific paths to Laravel' . "\n"
            . '    RewriteRule ^api/(.*)$ backend/public/index.php [L,QSA]' . "\n"
            . '    RewriteRule ^sanctum/(.*)$ backend/public/index.php [L,QSA]' . "\n"
            . '    RewriteRule ^broadcasting/(.*)$ backend/public/index.php [L,QSA]' . "\n"
            . "\n"
            . '    # Block direct access to backend source files (allow public/ only)' . "\n"
            . '    RewriteRule ^backend/(?!public/) - [F,L]' . "\n"
            . "\n"
            . '    # Block direct access to frontend source files (allow dist/ only)' . "\n"
            . '    RewriteRule ^frontend/(?!dist/) - [F,L]' . "\n"
            . "\n"
            . '    # Serve real files directly (covers rewritten frontend/dist/ paths on second pass)' . "\n"
            . '    RewriteCond %{REQUEST_FILENAME} -f' . "\n"
            . '    RewriteRule ^ - [L]' . "\n"
            . "\n"
            . '    # Map /assets/* to frontend/dist/assets/* (Vite build output)' . "\n"
            . '    RewriteRule ^assets/(.+)$ frontend/dist/assets/$1 [L]' . "\n"
            . "\n"
            . '    # Everything else -> Vue SPA (handles client-side routing)' . "\n"
            . '    RewriteRule ^ frontend/dist/index.html [L]' . "\n"
            . '</IfModule>' . "\n"
            . "\n"
            . '# Disable directory browsing' . "\n"
            . 'Options -Indexes' . "\n";

        file_put_contents($rootHtaccess, $rootContent);
        logMessage("Wrote production root .htaccess");

        // Update RewriteBase in backend/public/.htaccess
        $backendHtaccess = BACKEND_DIR . '/public/.htaccess';
        if (file_exists($backendHtaccess)) {
            $content = file_get_contents($backendHtaccess);
            $content = preg_replace(
                '/RewriteBase\s+\/.*$/m',
                'RewriteBase ' . $rewriteBase,
                $content
            );
            file_put_contents($backendHtaccess, $content);
            logMessage("Updated backend/public/.htaccess");
        }

        return [
            'success' => true,
            'message' => '.htaccess files configured for production',
            'details' => 'RewriteBase: ' . $rewriteBase . ' | Frontend SPA routing active',
        ];
    } catch (Exception $e) {
        logMessage('Failed to configure .htaccess: ' . $e->getMessage(), 'ERROR');
        return [
            'success' => false,
            'critical' => false,
            'message' => 'Failed to configure .htaccess files',
            'details' => $e->getMessage()
        ];
    }
}

/**
 * Create required directories
 */
function createDirectories() {
    $directories = [
        BACKEND_DIR . '/storage/app/public',
        BACKEND_DIR . '/storage/framework/cache',
        BACKEND_DIR . '/storage/framework/sessions',
        BACKEND_DIR . '/storage/framework/views',
        BACKEND_DIR . '/storage/logs',
        BACKEND_DIR . '/bootstrap/cache'
    ];
    
    $created = [];
    $failed = [];
    
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            if (@mkdir($dir, 0775, true)) {
                $created[] = $dir;
                logMessage("Created directory: $dir");
            } else {
                $failed[] = $dir;
                logMessage("Failed to create directory: $dir", 'ERROR');
            }
        }
    }
    
    if (empty($failed)) {
        return [
            'success' => true,
            'message' => 'All directories created successfully',
            'details' => count($created) . ' directories created'
        ];
    } else {
        return [
            'success' => false,
            'critical' => true,
            'message' => 'Failed to create some directories',
            'details' => 'Failed: ' . implode(', ', $failed)
        ];
    }
}

/**
 * Set directory permissions
 */
function setPermissions() {
    $paths = [
        BACKEND_DIR . '/storage' => 0775,
        BACKEND_DIR . '/bootstrap/cache' => 0775,
    ];
    
    $success = true;
    $warnings = [];
    
    foreach ($paths as $path => $permission) {
        if (is_dir($path)) {
            if (!@chmod($path, $permission)) {
                $warnings[] = "Could not set permissions on $path";
                $success = false;
            } else {
                // Test if writable
                $testFile = $path . '/.test_write';
                if (@file_put_contents($testFile, 'test') === false) {
                    $warnings[] = "$path is not writable";
                    $success = false;
                } else {
                    @unlink($testFile);
                }
            }
        }
    }
    
    if ($success) {
        return [
            'success' => true,
            'message' => 'Permissions set successfully',
            'details' => 'All directories are writable'
        ];
    } else {
        return [
            'success' => false,
            'critical' => false,
            'message' => 'Permission warnings',
            'details' => implode('; ', $warnings) . '. You may need to set permissions manually via FTP.'
        ];
    }
}

/**
 * Render the admin account + demo data form fields inside stepMigrate.
 */
function renderMigrateForm(): void
{
    ?>
    <h3 style="margin-top:1.5rem;">Administrator Account</h3>
    <p>This account will be used to log in and manage the application.</p>

    <div class="form-group">
        <label for="admin_first_name">First Name <span style="color:red">*</span></label>
        <input type="text" id="admin_first_name" name="admin_first_name"
               value="<?php echo htmlspecialchars($_POST['admin_first_name'] ?? ''); ?>"
               required autocomplete="given-name">
    </div>
    <div class="form-group">
        <label for="admin_last_name">Last Name <span style="color:red">*</span></label>
        <input type="text" id="admin_last_name" name="admin_last_name"
               value="<?php echo htmlspecialchars($_POST['admin_last_name'] ?? ''); ?>"
               required autocomplete="family-name">
    </div>
    <div class="form-group">
        <label for="admin_email">E-Mail Address <span style="color:red">*</span></label>
        <input type="email" id="admin_email" name="admin_email"
               value="<?php echo htmlspecialchars($_POST['admin_email'] ?? ''); ?>"
               required autocomplete="email">
    </div>
    <div class="form-group">
        <label for="admin_password">Password <span style="color:red">*</span> (min. 8 characters)</label>
        <input type="password" id="admin_password" name="admin_password"
               required autocomplete="new-password" minlength="8">
    </div>
    <div class="form-group">
        <label for="admin_password_confirm">Confirm Password <span style="color:red">*</span></label>
        <input type="password" id="admin_password_confirm" name="admin_password_confirm"
               required autocomplete="new-password" minlength="8">
    </div>

    <hr style="margin: 1.5rem 0;">

    <div class="checkbox-group">
        <input type="checkbox" name="run_seeders" id="run_seeders">
        <label for="run_seeders">Install demo data — creates a test trainer and test customer (optional)</label>
    </div>
    <?php
}

/**
 * Create the initial admin user via a small inline PHP script run through artisan tinker.
 *
 * Uses a direct PDO connection (no Laravel bootstrap) so it works regardless of whether
 * artisan/tinker is available and avoids double-bootstrapping issues.
 *
 * @return array{success: bool, message: string}
 */
function createInitialAdmin(string $firstName, string $lastName, string $email, string $password): array
{
    try {
        $envContent = file_get_contents(ENV_FILE);
        if ($envContent === false) {
            return ['success' => false, 'message' => '.env nicht lesbar'];
        }

        $db = parseEnvForDb($envContent);
        logMessage("createInitialAdmin: connecting to {$db['connection']}://{$db['host']}:{$db['port']}/{$db['database']}");

        $dsn = match ($db['connection']) {
            'pgsql'  => "pgsql:host={$db['host']};port={$db['port']};dbname={$db['database']}",
            default  => "mysql:host={$db['host']};port={$db['port']};dbname={$db['database']};charset=utf8mb4",
        };

        $pdo = new PDO($dsn, $db['username'], $db['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        // Check if user already exists
        $stmt = $pdo->prepare('SELECT id FROM ' . getTableName('users') . ' WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            logMessage("createInitialAdmin: user already exists ($email)");
            return ['success' => true, 'message' => 'Administrator-Konto existiert bereits'];
        }

        // password_hash with BCRYPT cost 12 produces the same format as Laravel's Hash::make()
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $now            = date('Y-m-d H:i:s');

        $stmt = $pdo->prepare(
            'INSERT INTO ' . getTableName('users') . ' (email, role, first_name, last_name, password, email_verified_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$email, 'admin', $firstName, $lastName, $hashedPassword, $now, $now, $now]);

        logMessage("createInitialAdmin: admin created ($email)");
        return ['success' => true, 'message' => "Administrator-Konto erstellt ($email)"];
    } catch (PDOException $e) {
        logMessage('createInitialAdmin PDO error: ' . $e->getMessage(), 'ERROR');
        return ['success' => false, 'message' => 'Datenbankfehler: ' . $e->getMessage()];
    } catch (Exception $e) {
        logMessage('createInitialAdmin error: ' . $e->getMessage(), 'ERROR');
        return ['success' => false, 'message' => 'Fehler: ' . $e->getMessage()];
    }
}

/**
 * Parse DB connection parameters from a .env file string.
 *
 * @return array{connection: string, host: string, port: string, database: string, username: string, password: string}
 */
function parseEnvForDb(string $envContent): array
{
    $result = [
        'connection' => 'mysql',
        'host'       => '127.0.0.1',
        'port'       => '3306',
        'database'   => '',
        'username'   => '',
        'password'   => '',
    ];

    $map = [
        'DB_CONNECTION' => 'connection',
        'DB_HOST'       => 'host',
        'DB_PORT'       => 'port',
        'DB_DATABASE'   => 'database',
        'DB_USERNAME'   => 'username',
        'DB_PASSWORD'   => 'password',
    ];

    foreach ($map as $envKey => $resultKey) {
        if (preg_match('/^' . $envKey . '=(.*)$/m', $envContent, $m)) {
            // Strip surrounding quotes if present
            $result[$resultKey] = trim($m[1], " \t\r\n\"'");
        }
    }

    return $result;
}

/**
 * Return the prefixed table name by reading DB_PREFIX from the .env file.
 *
 * Uses static caching so the .env file is only read once per request.
 *
 * @param  string $tableName  Raw table name without prefix.
 * @return string             Prefixed table name.
 */
function getTableName(string $tableName): string
{
    static $prefix = null;

    if ($prefix === null) {
        $envPath = defined('ENV_FILE') ? ENV_FILE : __DIR__ . '/backend/.env';
        $prefix  = '';

        if (file_exists($envPath)) {
            $content = (string) file_get_contents($envPath);
            if (preg_match('/^DB_PREFIX=(.*)$/m', $content, $m)) {
                $raw = trim($m[1], " \t\r\n\"'");
                // Only allow lowercase alphanumeric characters and underscores
                if (preg_match('/^[a-z0-9_]*$/', $raw)) {
                    $prefix = $raw;
                }
            }
        }
    }

    return $prefix . $tableName;
}

/**
 * Step 6: Database Migration
 */
function stepMigrate() {
    renderHeader('Database Migration', 'Initializing database schema', 70);

    $migrationResult = null;
    $adminResult     = null;
    $seedResult      = null;
    $formErrors      = [];

    // Only validate and run when the user explicitly submitted the migration form
    if (($_POST['action'] ?? '') === 'run_migration' && !isset($_SESSION['migration_complete'])) {

        $adminFirstName       = trim($_POST['admin_first_name']      ?? '');
        $adminLastName        = trim($_POST['admin_last_name']       ?? '');
        $adminEmail           = trim($_POST['admin_email']           ?? '');
        $adminPassword        =      $_POST['admin_password']        ?? '';
        $adminPasswordConfirm =      $_POST['admin_password_confirm'] ?? '';

        if (empty($adminFirstName) || empty($adminLastName) || empty($adminEmail) || empty($adminPassword) || empty($adminPasswordConfirm)) {
            $formErrors[] = 'Please fill in all administrator account fields.';
        } elseif (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $formErrors[] = 'Please enter a valid administrator e-mail address.';
        } elseif (strlen($adminPassword) < 8) {
            $formErrors[] = 'Administrator password must be at least 8 characters.';
        } elseif ($adminPassword !== $adminPasswordConfirm) {
            $formErrors[] = 'Passwords do not match.';
        }

        if (empty($formErrors)) {
            $migrationResult = runMigrations();
            $_SESSION['migration_complete'] = $migrationResult['success'];

            if ($migrationResult['success']) {
                $adminResult = createInitialAdmin($adminFirstName, $adminLastName, $adminEmail, $adminPassword);

                if (isset($_POST['run_seeders'])) {
                    $seedResult = runSeeders();
                }
            }
        }
    }

    ?>
    <h2>📊 Database Migration</h2>
    <p>Running database migrations to create the application schema...</p>

    <?php foreach ($formErrors as $error): ?>
        <div class="alert alert-error">
            <strong>✗ <?php echo htmlspecialchars($error); ?></strong>
        </div>
    <?php endforeach; ?>

    <?php if ($migrationResult): ?>
        <div class="alert alert-<?php echo $migrationResult['success'] ? 'success' : 'error'; ?>">
            <strong><?php echo $migrationResult['success'] ? '✓' : '✗'; ?> <?php echo htmlspecialchars($migrationResult['message']); ?></strong>
            <?php if (isset($migrationResult['output'])): ?>
                <details style="margin-top: 10px;">
                    <summary>Show details</summary>
                    <pre style="background: #f4f4f4; padding: 10px; border-radius: 4px; overflow-x: auto; font-size: 0.85em;"><?php echo htmlspecialchars($migrationResult['output']); ?></pre>
                </details>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($adminResult): ?>
        <div class="alert alert-<?php echo $adminResult['success'] ? 'success' : 'error'; ?>">
            <strong><?php echo $adminResult['success'] ? '✓' : '✗'; ?> <?php echo htmlspecialchars($adminResult['message']); ?></strong>
        </div>
    <?php endif; ?>

    <?php if ($seedResult): ?>
        <div class="alert alert-<?php echo $seedResult['success'] ? 'success' : 'warning'; ?>">
            <strong><?php echo $seedResult['success'] ? '✓' : '⚠'; ?> <?php echo htmlspecialchars($seedResult['message']); ?></strong>
        </div>
    <?php endif; ?>

    <form method="post">
        <?php if (!isset($_SESSION['migration_complete'])): ?>
            <?php renderMigrateForm(); ?>
            <div class="btn-group">
                <button type="submit" name="action" value="back_to_setup" class="btn btn-secondary">← Zurück</button>
                <button type="submit" name="action" value="run_migration" class="btn">Migration starten &amp; Admin anlegen →</button>
            </div>
        <?php elseif (!$_SESSION['migration_complete']): ?>
            <div class="btn-group">
                <button type="submit" name="action" value="rollback" class="btn btn-danger">Rollback Installation</button>
            </div>
        <?php else: ?>
            <div class="btn-group">
                <button type="submit" name="action" value="proceed_complete" class="btn">Installation abschließen →</button>
            </div>
        <?php endif; ?>
    </form>
    <?php
    
    renderFooter();
}

/**
 * Find a PHP CLI binary that matches the version currently running this script.
 *
 * On shared hosting, the generic `php` command often points to an old PHP 7.x
 * while the web server (and this script) runs PHP 8.x. Providers usually offer
 * versioned binaries such as `php8.3` or `php8`. We probe those first.
 *
 * @return string|null  Full path to a working PHP binary, or null if none found.
 */
function findPhpBinary(): ?string
{
    $major = (int) PHP_MAJOR_VERSION;
    $minor = (int) PHP_MINOR_VERSION;

    // Candidates ordered from most-specific to least-specific
    $candidates = [
        "php{$major}.{$minor}",   // e.g. php8.3
        "php{$major}",            // e.g. php8
        'php',                    // generic fallback
    ];

    foreach ($candidates as $bin) {
        // Resolve to full path via 'which'
        $path = trim((string) @shell_exec('which ' . escapeshellarg($bin) . ' 2>/dev/null'));
        if (empty($path)) {
            continue;
        }

        // Check that the file is actually executable by the current process
        if (!is_executable($path)) {
            logMessage("PHP binary found but not executable: $path", 'WARNING');
            continue;
        }

        // Verify the binary reports a compatible PHP version by running it
        $ver = trim((string) @shell_exec(escapeshellarg($path) . ' -r "echo PHP_MAJOR_VERSION.\".\".PHP_MINOR_VERSION;" 2>/dev/null'));
        if (!preg_match('/^\d+\.\d+$/', $ver)) {
            // Binary exists and is executable but produced no valid version output
            logMessage("PHP binary produced no valid version output: $path (got: " . substr($ver, 0, 50) . ")", 'WARNING');
            continue;
        }

        $parts    = explode('.', $ver);
        $binMajor = (int) $parts[0];
        $binMinor = (int) $parts[1];
        if ($binMajor > $major || ($binMajor === $major && $binMinor >= $minor)) {
            logMessage("Using PHP binary: $path ($ver)");
            return $path;
        }
    }

    logMessage('No suitable PHP CLI binary found – artisan commands will be skipped.', 'WARNING');
    return null;
}

/**
 * Run database migrations via shell_exec (avoids loading Laravel in the same PHP-FPM process,
 * which would exhaust memory/timeout and cause "Primary script unknown" in Apache error logs).
 */
function runMigrations(): array
{
    logMessage("=== Starting Migration Process ===");

    set_time_limit(300);

    $artisan = BACKEND_DIR . '/artisan';

    if (!file_exists($artisan)) {
        logMessage('Artisan not found at: ' . $artisan, 'ERROR');
        return [
            'success' => false,
            'message' => 'Artisan command not found',
            'output'  => 'File not found: ' . $artisan,
        ];
    }

    if (!is_readable(ENV_FILE)) {
        return [
            'success' => false,
            'message' => '.env file is not readable (permission denied)',
            'output'  => 'File: ' . ENV_FILE,
        ];
    }

    // Primary: run via shell so Laravel is loaded in a separate process.
    // Avoids memory/crash issues inside the PHP-FPM worker serving install.php.
    $phpBin = function_exists('shell_exec') ? findPhpBinary() : null;
    if ($phpBin !== null) {
        $command = 'cd ' . escapeshellarg(BACKEND_DIR)
            . ' && ' . escapeshellarg($phpBin) . ' artisan migrate:fresh --force 2>&1';
        logMessage("Running: $command");
        $output = shell_exec($command);
        logMessage("Migration output: " . substr((string) $output, 0, 1000));

        if ($output === null) {
            logMessage('shell_exec returned null – likely disabled on this host. Trying fallback.', 'WARNING');
        } else {
            $lower   = strtolower($output);
            $success = !str_contains($lower, 'error')
                && !str_contains($lower, 'exception')
                && !str_contains($lower, 'fatal');
            return [
                'success' => $success,
                'message' => $success
                    ? 'Datenbank-Migrationen erfolgreich ausgeführt'
                    : 'Migration fehlgeschlagen – Ausgabe: ' . $output,
                'output'  => $output,
            ];
        }
    }

    // Fallback: bootstrap Laravel directly (no working PHP CLI binary found or shell_exec unavailable).
    // NOTE: this must be the ONLY Laravel bootstrap in the request; do NOT call
    // createInitialAdmin() with a second bootstrap afterwards.
    try {
        require_once BACKEND_DIR . '/vendor/autoload.php';
        $app    = require_once BACKEND_DIR . '/bootstrap/app.php';
        $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();
        $status = $kernel->call('migrate:fresh', ['--force' => true]);
        $output = $kernel->output();
        logMessage("Direct migration status: $status output: " . substr($output, 0, 500));

        if ($status === 0) {
            return ['success' => true, 'message' => 'Datenbank-Migrationen erfolgreich ausgeführt', 'output' => $output];
        }
        return ['success' => false, 'message' => 'Migration fehlgeschlagen', 'output' => $output ?: '(no output)'];
    } catch (Throwable $e) {
        logMessage('Direct migration error: ' . $e->getMessage(), 'ERROR');
        return ['success' => false, 'message' => 'Migration error: ' . $e->getMessage(), 'output' => ''];
    }
}

/**
 * Run database seeders
 */
function runSeeders() {
    try {
        $phpBinary = function_exists('shell_exec') ? findPhpBinary() : null;
        if ($phpBinary === null) {
            logMessage('No executable PHP CLI binary found – skipping demo seeder.', 'WARNING');
            return ['success' => false, 'message' => 'Kein ausführbares PHP CLI Binary gefunden', 'output' => ''];
        }
        $command = 'cd ' . escapeshellarg(BACKEND_DIR)
            . ' && ' . escapeshellarg($phpBinary) . ' artisan db:seed --class=DemoDataSeeder --force 2>&1';
        $output = shell_exec($command);

        logMessage("Demo seeder output: $output");

        // artisan exits with output containing 'INFO' or 'Seeding' on success
        $success = $output !== null && !str_contains(strtolower($output), 'error');

        return [
            'success' => $success,
            'message' => $success
                ? 'Demo-Daten installiert (Trainer: trainer@example.com / demo1234, Kunde: customer@example.com / demo1234)'
                : 'Demo-Seeder fehlgeschlagen',
            'output'  => $output ?: '(no output)'
        ];
    } catch (Exception $e) {
        logMessage('Seeder error: ' . $e->getMessage(), 'ERROR');
        return [
            'success' => false,
            'message' => 'Seeder error: ' . $e->getMessage(),
            'output'  => ''
        ];
    }
}

/**
 * Step 7: Installation Complete
 */
function stepComplete() {
    // Create storage symlink
    createStorageSymlink();
    
    // Create lock file
    $lockContent = "Installation completed on: " . date('Y-m-d H:i:s') . "\n";
    $lockContent .= "Installed by IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n";
    $lockContent .= "Application URL: " . getSessionData('app_url', '') . "\n";
    file_put_contents(INSTALL_LOCK_FILE, $lockContent);
    chmod(INSTALL_LOCK_FILE, 0600);
    
    logMessage('Installation completed successfully');
    
    // Set log file permissions
    if (file_exists(INSTALL_LOG_FILE)) {
        chmod(INSTALL_LOG_FILE, 0600);
    }
    
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Installation Complete - HomoCanis</title>
        <style><?php echo getStyles(); ?></style>
    </head>
    <body>
        <div class="container">
            <div class="card">
                <div class="header success-header">
                    <h1>🎉 Installation Complete!</h1>
                    <p>HomoCanis has been successfully installed</p>
                </div>
                <div style="padding: 0 30px 20px 30px;">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 100%"></div>
                    </div>
                </div>
                <div class="content">
                    <div class="alert alert-success">
                        <strong>✓ Installation completed successfully!</strong>
                    </div>
                    
                    <h3>Next Steps:</h3>
                    <ol>
                        <li><strong>Security:</strong> Delete this installation file for security</li>
                        <li><strong>Access:</strong> Visit your application at: 
                            <a href="<?php echo htmlspecialchars(getSessionData('app_url', '')); ?>" target="_blank">
                                <?php echo htmlspecialchars(getSessionData('app_url', '')); ?>
                            </a>
                        </li>
                        <li><strong>Login:</strong> Use your admin credentials to log in</li>
                    </ol>
                    
                    <div class="warning-box" style="margin-top: 30px;">
                        <strong>⚠ Important Security Notice</strong><br>
                        For security reasons, you should delete or rename this installation file immediately.
                    </div>
                    
                    <form method="post" style="margin-top: 20px;">
                        <div class="btn-group">
                            <button type="submit" name="action" value="delete_installer" class="btn btn-danger">
                                🗑 Delete Installer Now
                            </button>
                            <a href="<?php echo htmlspecialchars(getSessionData('app_url', '')); ?>" class="btn">
                                Go to Application →
                            </a>
                        </div>
                    </form>
                    
                    <details style="margin-top: 30px;">
                        <summary>Installation Details</summary>
                        <ul style="margin-top: 10px;">
                            <li><strong>App Name:</strong> <?php echo htmlspecialchars(getSessionData('app_name', '')); ?></li>
                            <li><strong>Environment:</strong> <?php echo htmlspecialchars(getSessionData('app_env', '')); ?></li>
                            <li><strong>Database:</strong> <?php echo htmlspecialchars(getSessionData('db_name', '')); ?>@<?php echo htmlspecialchars(getSessionData('db_host', '')); ?></li>
                            <li><strong>Lock File:</strong> <?php echo INSTALL_LOCK_FILE; ?></li>
                            <li><strong>Log File:</strong> <?php echo INSTALL_LOG_FILE; ?></li>
                        </ul>
                    </details>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}

/**
 * Create storage symlink via shell_exec to avoid loading Laravel in this process.
 */
function createStorageSymlink(): bool
{
    try {
        if (function_exists('shell_exec')) {
            $command = 'cd ' . escapeshellarg(BACKEND_DIR) . ' && php artisan storage:link 2>&1';
            $output  = shell_exec($command);
            logMessage("Storage link output: $output");
            return true;
        }

        // Fallback: direct Laravel bootstrap (only when shell_exec is disabled)
        require_once BACKEND_DIR . '/vendor/autoload.php';
        $app    = require_once BACKEND_DIR . '/bootstrap/app.php';
        $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
        $status = $kernel->call('storage:link');
        logMessage("Storage link status: $status");
        return $status === 0;
    } catch (Throwable $e) {
        logMessage("Storage link error: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Perform rollback
 */
function performRollback() {
    logMessage('Starting rollback');
    
    // Delete .env file
    if (file_exists(ENV_FILE)) {
        @unlink(ENV_FILE);
        logMessage('Deleted .env file');
    }
    
    // Clear session
    session_destroy();
    
    // Redirect to start
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}
