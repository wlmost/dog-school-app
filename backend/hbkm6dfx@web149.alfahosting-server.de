<?php
/**
 * HomoCanis Server Requirements Check
 * 
 * USAGE:
 * 1. Upload this file to your server's backend/ directory
 * 2. Access via browser: https://your-domain.com/requirements-check.php
 * 3. Review all validation results
 * 4. Fix any issues reported
 * 5. DELETE THIS FILE after successful installation
 * 
 * SECURITY WARNING:
 * This script reveals system information. DELETE or RESTRICT ACCESS after use.
 * Consider IP whitelist for production servers.
 * 
 * Version: 1.0.0
 * Date: 2026-02-15
 * Required PHP: 8.4.x
 */

// Optional IP whitelist (uncomment to enable)
// $allowed_ips = ['127.0.0.1', '::1', 'YOUR_IP_HERE'];
// if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', $allowed_ips)) {
//     http_response_code(403);
//     die('Access denied');
// }

// Initialize results array
$results = [
    'php_version' => [],
    'extensions' => [],
    'permissions' => [],
    'database' => []
];

$overall_status = 'pass'; // Can be: pass, warning, fail

// =============================================================================
// PHP VERSION CHECK
// =============================================================================

function checkPhpVersion() {
    global $results, $overall_status;
    
    $version = PHP_VERSION;
    $parts = explode('.', $version);
    $major = (int)$parts[0];
    $minor = (int)$parts[1];
    $patch = isset($parts[2]) ? (int)$parts[2] : 0;
    
    $results['php_version'] = [
        'version' => $version,
        'major' => $major,
        'minor' => $minor,
        'patch' => $patch
    ];
    
    if ($major < 8 || ($major == 8 && $minor < 4)) {
        $results['php_version']['status'] = 'fail';
        $results['php_version']['message'] = 'PHP 8.4.0 or higher is required';
        $overall_status = 'fail';
    } elseif ($major > 8 || ($major == 8 && $minor > 4)) {
        $results['php_version']['status'] = 'warning';
        $results['php_version']['message'] = 'PHP version is untested (8.4.x recommended)';
        if ($overall_status === 'pass') $overall_status = 'warning';
    } else {
        $results['php_version']['status'] = 'pass';
        $results['php_version']['message'] = 'PHP version is compatible';
    }
}

// =============================================================================
// PHP EXTENSIONS CHECK
// =============================================================================

function checkExtensions() {
    global $results, $overall_status;
    
    // Define required extensions
    $required = [
        'json' => 'JSON parsing',
        'mbstring' => 'Multi-byte string handling',
        'openssl' => 'Encryption and secure connections',
        'pdo' => 'Database abstraction layer',
        'pdo_mysql' => 'MySQL database driver',
        'tokenizer' => 'PHP tokenization',
        'xml' => 'XML processing',
        'ctype' => 'Character type checking',
        'fileinfo' => 'File type detection',
        'filter' => 'Input filtering',
        'hash' => 'Hash functions',
        'curl' => 'HTTP requests (PayPal SDK)',
        'zip' => 'Archive handling (dompdf)'
    ];
    
    // Define recommended extensions
    $recommended = [
        'bcmath' => 'Arbitrary precision mathematics',
        'gd' => 'Image processing (alternative: imagick)',
        'imagick' => 'Image processing (alternative: gd)'
    ];
    
    $results['extensions']['required'] = [];
    $results['extensions']['recommended'] = [];
    $results['extensions']['missing_required'] = [];
    $results['extensions']['missing_recommended'] = [];
    
    // Check required extensions
    foreach ($required as $ext => $description) {
        $loaded = extension_loaded($ext);
        $results['extensions']['required'][$ext] = [
            'loaded' => $loaded,
            'description' => $description
        ];
        
        if (!$loaded) {
            $results['extensions']['missing_required'][] = $ext;
            $overall_status = 'fail';
        }
    }
    
    // Check recommended extensions
    foreach ($recommended as $ext => $description) {
        $loaded = extension_loaded($ext);
        $results['extensions']['recommended'][$ext] = [
            'loaded' => $loaded,
            'description' => $description
        ];
        
        if (!$loaded) {
            $results['extensions']['missing_recommended'][] = $ext;
        }
    }
    
    // Special handling for gd/imagick - at least one should be present
    if (!extension_loaded('gd') && !extension_loaded('imagick')) {
        if ($overall_status === 'pass') $overall_status = 'warning';
    }
    
    $results['extensions']['status'] = empty($results['extensions']['missing_required']) ? 'pass' : 'fail';
}

// =============================================================================
// FILE PERMISSIONS CHECK
// =============================================================================

function checkPermissions() {
    global $results, $overall_status;
    
    $base_dir = __DIR__;
    
    // Define directories that need to be writable
    $directories = [
        'storage' => $base_dir . '/storage',
        'bootstrap/cache' => $base_dir . '/bootstrap/cache',
        'public/storage' => $base_dir . '/public/storage'
    ];
    
    $results['permissions']['checks'] = [];
    
    foreach ($directories as $name => $path) {
        $check = [
            'path' => $path,
            'name' => $name
        ];
        
        if (!file_exists($path)) {
            $check['status'] = 'fail';
            $check['message'] = 'Directory does not exist';
            $check['exists'] = false;
            $overall_status = 'fail';
        } else {
            $check['exists'] = true;
            
            // Get current permissions
            $perms = fileperms($path);
            $check['current_perms'] = substr(sprintf('%o', $perms), -4);
            
            // Test write permission
            $test_file = $path . '/.requirements-test-' . uniqid();
            $writable = @file_put_contents($test_file, 'test');
            
            if ($writable !== false) {
                @unlink($test_file);
                $check['status'] = 'pass';
                $check['writable'] = true;
                $check['message'] = 'Directory is writable';
            } else {
                $check['status'] = 'fail';
                $check['writable'] = false;
                $check['message'] = 'Directory is not writable';
                $check['recommended_perms'] = '0775';
                $overall_status = 'fail';
            }
        }
        
        $results['permissions']['checks'][$name] = $check;
    }
    
    $results['permissions']['status'] = ($overall_status === 'fail') ? 'fail' : 'pass';
}

// =============================================================================
// DATABASE CHECK
// =============================================================================

function checkDatabase() {
    global $results, $overall_status;
    
    $db_host = null;
    $db_port = '3306';
    $db_name = null;
    $db_user = null;
    $db_pass = null;
    $test_requested = false;
    
    // Check for form submission
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_database'])) {
        $test_requested = true;
        $db_host = $_POST['db_host'] ?? '';
        $db_port = $_POST['db_port'] ?? '3306';
        $db_name = $_POST['db_name'] ?? '';
        $db_user = $_POST['db_user'] ?? '';
        $db_pass = $_POST['db_pass'] ?? '';
    } else {
        // Try to read from .env file
        $env_file = __DIR__ . '/.env';
        if (file_exists($env_file) && is_readable($env_file)) {
            $env_content = file_get_contents($env_file);
            $env_lines = explode("\n", $env_content);
            
            foreach ($env_lines as $line) {
                $line = trim($line);
                if (empty($line) || strpos($line, '#') === 0) continue;
                
                if (preg_match('/^DB_HOST=(.*)$/', $line, $matches)) {
                    $db_host = trim($matches[1], '"\'');
                } elseif (preg_match('/^DB_PORT=(.*)$/', $line, $matches)) {
                    $db_port = trim($matches[1], '"\'');
                } elseif (preg_match('/^DB_DATABASE=(.*)$/', $line, $matches)) {
                    $db_name = trim($matches[1], '"\'');
                } elseif (preg_match('/^DB_USERNAME=(.*)$/', $line, $matches)) {
                    $db_user = trim($matches[1], '"\'');
                } elseif (preg_match('/^DB_PASSWORD=(.*)$/', $line, $matches)) {
                    $db_pass = trim($matches[1], '"\'');
                }
            }
            
            if ($db_host && $db_name && $db_user) {
                $test_requested = true;
            }
        }
    }
    
    $results['database']['tested'] = $test_requested;
    
    if (!$test_requested) {
        $results['database']['status'] = 'skipped';
        $results['database']['message'] = 'Database test not performed (no credentials provided)';
        return;
    }
    
    // Sanitize inputs
    $db_host = htmlspecialchars(strip_tags($db_host));
    $db_port = htmlspecialchars(strip_tags($db_port));
    $db_name = htmlspecialchars(strip_tags($db_name));
    $db_user = htmlspecialchars(strip_tags($db_user));
    
    try {
        $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";
        $pdo = new PDO($dsn, $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5
        ]);
        
        // Get MySQL version
        $version_query = $pdo->query('SELECT VERSION()');
        $mysql_version = $version_query->fetchColumn();
        
        $results['database']['connected'] = true;
        $results['database']['version'] = $mysql_version;
        
        // Parse version
        preg_match('/^(\d+)\.(\d+)\.(\d+)/', $mysql_version, $version_parts);
        $major = isset($version_parts[1]) ? (int)$version_parts[1] : 0;
        $minor = isset($version_parts[2]) ? (int)$version_parts[2] : 0;
        
        if ($major >= 8) {
            $results['database']['status'] = 'pass';
            $results['database']['message'] = 'MySQL version is compatible';
        } elseif ($major == 5 && $minor >= 7) {
            $results['database']['status'] = 'warning';
            $results['database']['message'] = 'MySQL 8.0+ is recommended (5.7 is acceptable)';
            if ($overall_status === 'pass') $overall_status = 'warning';
        } else {
            $results['database']['status'] = 'fail';
            $results['database']['message'] = 'MySQL version is too old (minimum 5.7 required)';
            $overall_status = 'fail';
        }
        
    } catch (PDOException $e) {
        $results['database']['connected'] = false;
        $results['database']['status'] = 'fail';
        $results['database']['error'] = $e->getMessage();
        $results['database']['message'] = 'Database connection failed';
        $overall_status = 'fail';
    }
}

// Run all checks
checkPhpVersion();
checkExtensions();
checkPermissions();
checkDatabase();

$results['overall_status'] = $overall_status;
$results['timestamp'] = date('Y-m-d H:i:s');

// =============================================================================
// HTML OUTPUT
// =============================================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HomoCanis Server Requirements Check</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f7fa;
            color: #2d3748;
            line-height: 1.6;
            padding: 20px;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .header h1 {
            color: #1a202c;
            margin-bottom: 10px;
        }
        
        .warning-banner {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            color: #856404;
        }
        
        .warning-banner strong {
            display: block;
            font-size: 1.1em;
            margin-bottom: 10px;
        }
        
        .summary {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            text-align: center;
        }
        
        .summary h2 {
            margin-bottom: 20px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 1.3em;
            font-weight: bold;
        }
        
        .status-pass {
            background: #d4edda;
            color: #155724;
        }
        
        .status-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-fail {
            background: #f8d7da;
            color: #721c24;
        }
        
        .section {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .section h3 {
            margin-bottom: 20px;
            color: #1a202c;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 10px;
        }
        
        .check-item {
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 6px;
            background: #f7fafc;
            border-left: 4px solid #cbd5e0;
        }
        
        .check-item.pass {
            background: #f0fff4;
            border-left-color: #48bb78;
        }
        
        .check-item.warning {
            background: #fffef0;
            border-left-color: #f6ad55;
        }
        
        .check-item.fail {
            background: #fff5f5;
            border-left-color: #f56565;
        }
        
        .check-item-header {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .status-icon {
            font-size: 1.4em;
            margin-right: 12px;
            font-weight: bold;
        }
        
        .status-icon.pass { color: #48bb78; }
        .status-icon.warning { color: #f6ad55; }
        .status-icon.fail { color: #f56565; }
        
        .check-item-title {
            font-weight: 600;
            font-size: 1.05em;
        }
        
        .check-item-description {
            color: #4a5568;
            font-size: 0.95em;
            margin-left: 32px;
        }
        
        .remediation {
            background: #edf2f7;
            padding: 15px;
            border-radius: 6px;
            margin-top: 10px;
            margin-left: 32px;
        }
        
        .remediation strong {
            display: block;
            margin-bottom: 8px;
            color: #2d3748;
        }
        
        .remediation code {
            background: #2d3748;
            color: #68d391;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
        }
        
        .extension-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }
        
        .extension-item {
            padding: 12px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            font-size: 0.95em;
        }
        
        .db-form {
            background: #f7fafc;
            padding: 20px;
            border-radius: 8px;
            margin-top: 15px;
        }
        
        .db-form h4 {
            margin-bottom: 15px;
            color: #2d3748;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            margin-bottom: 5px;
            font-weight: 600;
            color: #4a5568;
        }
        
        .form-group input {
            padding: 10px;
            border: 1px solid #cbd5e0;
            border-radius: 4px;
            font-size: 1em;
        }
        
        .btn {
            background: #4299e1;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 1em;
            cursor: pointer;
            font-weight: 600;
        }
        
        .btn:hover {
            background: #3182ce;
        }
        
        .footer {
            text-align: center;
            padding: 20px;
            color: #718096;
            font-size: 0.9em;
        }
        
        .timestamp {
            color: #718096;
            font-size: 0.9em;
            margin-top: 10px;
        }
        
        details {
            margin-top: 10px;
        }
        
        summary {
            cursor: pointer;
            font-weight: 600;
            padding: 10px;
            background: #edf2f7;
            border-radius: 4px;
            user-select: none;
        }
        
        summary:hover {
            background: #e2e8f0;
        }
        
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            
            .header, .summary, .section {
                padding: 20px;
            }
            
            .extension-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🐕 HomoCanis Server Requirements Check</h1>
            <p>Validation performed on <?php echo $results['timestamp']; ?></p>
        </div>
        
        <div class="warning-banner">
            <strong>⚠️ SECURITY WARNING</strong>
            DELETE THIS FILE after completing your installation. This script reveals system information and should not be accessible in production.
        </div>
        
        <div class="summary">
            <h2>Overall Status</h2>
            <div class="status-badge status-<?php echo $overall_status; ?>">
                <?php 
                if ($overall_status === 'pass') {
                    echo '✓ ALL CHECKS PASSED';
                } elseif ($overall_status === 'warning') {
                    echo '⚠ PASSED WITH WARNINGS';
                } else {
                    echo '✗ CHECKS FAILED';
                }
                ?>
            </div>
        </div>
        
        <!-- PHP VERSION -->
        <div class="section">
            <h3>PHP Version</h3>
            <div class="check-item <?php echo $results['php_version']['status']; ?>">
                <div class="check-item-header">
                    <span class="status-icon <?php echo $results['php_version']['status']; ?>">
                        <?php echo $results['php_version']['status'] === 'pass' ? '✓' : ($results['php_version']['status'] === 'warning' ? '⚠' : '✗'); ?>
                    </span>
                    <span class="check-item-title">PHP <?php echo $results['php_version']['version']; ?></span>
                </div>
                <div class="check-item-description">
                    <?php echo $results['php_version']['message']; ?>
                </div>
                
                <?php if ($results['php_version']['status'] !== 'pass'): ?>
                <div class="remediation">
                    <strong>How to fix:</strong>
                    <?php if ($results['php_version']['status'] === 'fail'): ?>
                        <p>Contact your hosting provider to upgrade to PHP 8.4.x. Most control panels (cPanel, Plesk) allow PHP version selection.</p>
                        <p>Required: PHP 8.4.0 or higher</p>
                    <?php else: ?>
                        <p>Your PHP version (<?php echo $results['php_version']['version']; ?>) has not been tested with this application. PHP 8.4.x is recommended. The application may work, but proceed with caution.</p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- PHP EXTENSIONS -->
        <div class="section">
            <h3>PHP Extensions</h3>
            
            <h4 style="margin-top: 0;">Required Extensions</h4>
            <div class="extension-grid">
                <?php foreach ($results['extensions']['required'] as $ext => $info): ?>
                <div class="extension-item check-item <?php echo $info['loaded'] ? 'pass' : 'fail'; ?>">
                    <span class="status-icon <?php echo $info['loaded'] ? 'pass' : 'fail'; ?>">
                        <?php echo $info['loaded'] ? '✓' : '✗'; ?>
                    </span>
                    <div>
                        <strong><?php echo $ext; ?></strong><br>
                        <small><?php echo $info['description']; ?></small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (!empty($results['extensions']['missing_required'])): ?>
            <div class="remediation" style="margin-left: 0; margin-top: 20px;">
                <strong>Missing Required Extensions:</strong>
                <p>The following extensions must be installed:</p>
                <ul style="margin-left: 20px; margin-top: 10px;">
                    <?php foreach ($results['extensions']['missing_required'] as $ext): ?>
                    <li><code><?php echo $ext; ?></code></li>
                    <?php endforeach; ?>
                </ul>
                <p style="margin-top: 15px;"><strong>How to fix:</strong></p>
                <ul style="margin-left: 20px;">
                    <li>Contact your hosting provider to enable these extensions</li>
                    <li>In cPanel/Plesk, look for "PHP Extensions" or "Select PHP Version"</li>
                    <li>On VPS/dedicated servers, use package manager (e.g., <code>apt install php8.4-{extension}</code>)</li>
                </ul>
            </div>
            <?php endif; ?>
            
            <h4 style="margin-top: 25px;">Recommended Extensions</h4>
            <div class="extension-grid">
                <?php foreach ($results['extensions']['recommended'] as $ext => $info): ?>
                <div class="extension-item check-item <?php echo $info['loaded'] ? 'pass' : 'warning'; ?>">
                    <span class="status-icon <?php echo $info['loaded'] ? 'pass' : 'warning'; ?>">
                        <?php echo $info['loaded'] ? '✓' : '⚠'; ?>
                    </span>
                    <div>
                        <strong><?php echo $ext; ?></strong><br>
                        <small><?php echo $info['description']; ?></small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (!empty($results['extensions']['missing_recommended'])): ?>
            <div class="remediation" style="margin-left: 0; margin-top: 15px; background: #fffef0;">
                <strong>Note:</strong>
                <p>Recommended extensions are optional but enhance functionality. At least one image processing extension (GD or Imagick) is recommended.</p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- FILE PERMISSIONS -->
        <div class="section">
            <h3>File Permissions</h3>
            
            <?php foreach ($results['permissions']['checks'] as $check): ?>
            <div class="check-item <?php echo $check['status']; ?>">
                <div class="check-item-header">
                    <span class="status-icon <?php echo $check['status']; ?>">
                        <?php echo $check['status'] === 'pass' ? '✓' : '✗'; ?>
                    </span>
                    <span class="check-item-title"><?php echo $check['name']; ?></span>
                </div>
                <div class="check-item-description">
                    <?php echo $check['message']; ?>
                    <?php if (isset($check['current_perms'])): ?>
                        (Current: <?php echo $check['current_perms']; ?>)
                    <?php endif; ?>
                </div>
                
                <?php if ($check['status'] === 'fail'): ?>
                <div class="remediation">
                    <strong>How to fix:</strong>
                    <?php if (!$check['exists']): ?>
                        <p>Create the directory:</p>
                        <code>mkdir -p <?php echo $check['path']; ?></code>
                    <?php else: ?>
                        <p>Make the directory writable:</p>
                        <code>chmod 775 <?php echo $check['path']; ?></code>
                        <p style="margin-top: 10px;">Or via FTP client, set permissions to 775 or 777</p>
                        <p style="margin-top: 10px;">Note: Path is relative to: <code><?php echo __DIR__; ?></code></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- DATABASE -->
        <div class="section">
            <h3>Database Connectivity</h3>
            
            <?php if ($results['database']['tested']): ?>
                <div class="check-item <?php echo $results['database']['status']; ?>">
                    <div class="check-item-header">
                        <span class="status-icon <?php echo $results['database']['status']; ?>">
                            <?php echo $results['database']['status'] === 'pass' ? '✓' : ($results['database']['status'] === 'warning' ? '⚠' : '✗'); ?>
                        </span>
                        <span class="check-item-title">MySQL Connection</span>
                    </div>
                    <div class="check-item-description">
                        <?php if ($results['database']['connected']): ?>
                            Connected successfully. Version: <?php echo $results['database']['version']; ?>
                            <br><?php echo $results['database']['message']; ?>
                        <?php else: ?>
                            Connection failed: <?php echo htmlspecialchars($results['database']['error']); ?>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($results['database']['status'] !== 'pass'): ?>
                    <div class="remediation">
                        <strong>How to fix:</strong>
                        <?php if (!$results['database']['connected']): ?>
                            <ul style="margin-left: 20px;">
                                <li>Verify database credentials are correct</li>
                                <li>Ensure MySQL server is running</li>
                                <li>Check if remote connections are allowed (if database is on different server)</li>
                                <li>Verify firewall allows connection on port 3306</li>
                                <li>Contact hosting provider if issue persists</li>
                            </ul>
                        <?php else: ?>
                            <p>MySQL 8.0 or higher is recommended for best performance and security. Contact your hosting provider to upgrade.</p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <p style="color: #718096; margin-bottom: 20px;">
                    Database connectivity test was not performed. Enter credentials below to test your MySQL connection.
                </p>
            <?php endif; ?>
            
            <details <?php echo !$results['database']['tested'] ? 'open' : ''; ?>>
                <summary>Test Database Connection</summary>
                <div class="db-form">
                    <h4>Enter Database Credentials</h4>
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Host:</label>
                                <input type="text" name="db_host" value="localhost" required>
                            </div>
                            <div class="form-group">
                                <label>Port:</label>
                                <input type="text" name="db_port" value="3306" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Database Name:</label>
                                <input type="text" name="db_name" required>
                            </div>
                            <div class="form-group">
                                <label>Username:</label>
                                <input type="text" name="db_user" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Password:</label>
                                <input type="password" name="db_pass">
                            </div>
                        </div>
                        <button type="submit" name="test_database" class="btn">Test Connection</button>
                    </form>
                    <p style="margin-top: 15px; color: #718096; font-size: 0.9em;">
                        <strong>Note:</strong> Credentials are not stored. They are only used for this connection test.
                    </p>
                </div>
            </details>
        </div>
        
        <div class="footer">
            <p><strong>HomoCanis Server Requirements Check v1.0.0</strong></p>
            <p>Generated: <?php echo date('Y-m-d H:i:s'); ?> | PHP <?php echo PHP_VERSION; ?></p>
            <p style="margin-top: 10px;">
                <a href="https://www.php.net/manual/en/extensions.php" target="_blank" style="color: #4299e1;">PHP Extensions Documentation</a>
            </p>
        </div>
    </div>
</body>
</html>
