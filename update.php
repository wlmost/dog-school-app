<?php
declare(strict_types=1);

/**
 * HomoCanis Update Wizard
 *
 * Applies database migrations and clears caches after deploying a new version.
 * Enables a maintenance page (visible to visitors) during the update.
 *
 * Usage:
 *   1. Upload and extract the new deployment package (overwriting existing files).
 *   2. Open https://your-domain.com/update.php in a browser.
 *   3. Log in with your admin credentials.
 *   4. Click "Update starten".
 *   5. Delete this file afterwards.
 */

// ─── Constants ────────────────────────────────────────────────────────────────
define('BACKEND_DIR',       __DIR__ . '/backend');
define('ENV_FILE',          BACKEND_DIR . '/.env');
define('INSTALL_LOCK_FILE', __DIR__ . '/install.lock');
define('MAINTENANCE_FLAG',  __DIR__ . '/maintenance.flag');
define('HTACCESS_FILE',     __DIR__ . '/.htaccess');
define('UPDATE_LOG_FILE',   __DIR__ . '/update_' . date('Ymd_His') . '.log');

session_start();

// ─── Block if not installed ───────────────────────────────────────────────────
if (!file_exists(INSTALL_LOCK_FILE) || !file_exists(ENV_FILE)) {
    die('<p style="font-family:sans-serif;padding:2em">Die Anwendung ist noch nicht installiert. Bitte zuerst <a href="install.php">install.php</a> ausführen.</p>');
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
function logUpdate(string $message, string $level = 'INFO'): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] [' . $level . '] ' . $message . "\n";
    file_put_contents(UPDATE_LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}

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
            $result[$resultKey] = trim($m[1], " \t\r\n\"'");
        }
    }
    return $result;
}

function getPdo(): PDO
{
    $db  = parseEnvForDb((string) file_get_contents(ENV_FILE));
    $dsn = match ($db['connection']) {
        'pgsql'  => "pgsql:host={$db['host']};port={$db['port']};dbname={$db['database']}",
        default  => "mysql:host={$db['host']};port={$db['port']};dbname={$db['database']};charset=utf8mb4",
    };
    return new PDO($dsn, $db['username'], $db['password'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function verifyAdmin(string $email, string $password): bool
{
    try {
        $pdo  = getPdo();
        $stmt = $pdo->prepare(
            "SELECT password FROM users WHERE email = ? AND role = 'admin' AND deleted_at IS NULL LIMIT 1"
        );
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        return $row && password_verify($password, $row['password']);
    } catch (Throwable) {
        return false;
    }
}

/**
 * Enable maintenance mode:
 * - Creates maintenance.flag (read by .htaccess RewriteCond)
 * - Patches .htaccess to add maintenance redirect rules if not already present
 */
function enableMaintenance(): void
{
    file_put_contents(MAINTENANCE_FLAG, date('Y-m-d H:i:s'));

    if (!file_exists(HTACCESS_FILE)) {
        return;
    }

    $content = file_get_contents(HTACCESS_FILE);
    if (str_contains($content, '# MAINTENANCE_MODE')) {
        return; // rules already present
    }

    $rules = "\n    # MAINTENANCE_MODE\n"
        . "    RewriteCond %{REQUEST_URI} !maintenance\\.html$\n"
        . "    RewriteCond %{REQUEST_URI} !update\\.php$\n"
        . "    RewriteCond %{DOCUMENT_ROOT}/maintenance.flag -f\n"
        . "    RewriteRule ^ /maintenance.html [R=302,L]\n"
        . "    # /MAINTENANCE_MODE\n";

    // Insert after "RewriteBase /"
    $patched = preg_replace('/(RewriteBase\s+\/\s*\n)/', "$1$rules", $content, 1);
    if ($patched && $patched !== $content) {
        file_put_contents(HTACCESS_FILE, $patched);
    }
}

function disableMaintenance(): void
{
    if (file_exists(MAINTENANCE_FLAG)) {
        @unlink(MAINTENANCE_FLAG);
    }
}

function runArtisan(string $command): array
{
    set_time_limit(120);

    if (!function_exists('shell_exec')) {
        return ['success' => false, 'output' => 'shell_exec ist auf diesem Server nicht verfügbar.'];
    }

    $cmd    = 'cd ' . escapeshellarg(BACKEND_DIR) . ' && php artisan ' . $command . ' 2>&1';
    $output = (string) shell_exec($cmd);
    logUpdate("$ php artisan $command\n$output");

    $lower   = strtolower($output);
    $success = !str_contains($lower, 'fatal error')
        && !str_contains($lower, 'uncaught exception')
        && !str_contains($lower, 'error:');

    return ['success' => $success, 'output' => $output];
}

function getAppVersion(): string
{
    $composerFile = BACKEND_DIR . '/composer.json';
    if (file_exists($composerFile)) {
        $data = json_decode((string) file_get_contents($composerFile), true);
        return $data['version'] ?? '–';
    }
    return '–';
}

// ─── Action handling ──────────────────────────────────────────────────────────
$action = $_POST['action'] ?? '';
$error  = '';

// Login
if ($action === 'login') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($email === '' || $password === '') {
        $error = 'Bitte E-Mail und Passwort eingeben.';
    } elseif (verifyAdmin($email, $password)) {
        $_SESSION['update_auth']  = true;
        $_SESSION['update_email'] = $email;
        header('Location: update.php');
        exit;
    } else {
        $error = 'E-Mail oder Passwort ungültig, oder kein Administrator-Konto.';
    }
}

// Logout
if ($action === 'logout') {
    session_destroy();
    header('Location: update.php');
    exit;
}

// Delete this file
if ($action === 'delete_updater' && isset($_SESSION['update_auth'])) {
    disableMaintenance();
    session_destroy();
    @unlink(__FILE__);
    ?><!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><title>Gelöscht</title>
    <style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#667eea}
    .box{background:#fff;padding:2em 3em;border-radius:12px;text-align:center;box-shadow:0 10px 40px rgba(0,0,0,.2)}
    a{color:#667eea;font-weight:600}</style></head>
    <body><div class="box"><h2>✓ update.php wurde gelöscht.</h2><p style="margin-top:1em"><a href="/">Zur Anwendung →</a></p></div></body></html>
    <?php
    exit;
}

// Run update
$updateResults = null;
if ($action === 'run_update' && isset($_SESSION['update_auth'])) {
    logUpdate('=== Update gestartet von ' . ($_SESSION['update_email'] ?? 'unbekannt') . ' ===');

    enableMaintenance();
    logUpdate('Wartungsmodus aktiviert');

    $steps = [];

    // 1. Migrate (never migrate:fresh – that would drop all data!)
    $steps['migrate'] = runArtisan('migrate --force');

    // 2. Clear all caches
    $steps['config'] = runArtisan('config:clear');
    $steps['cache']  = runArtisan('cache:clear');
    $steps['view']   = runArtisan('view:clear');
    $steps['route']  = runArtisan('route:clear');

    // 3. Re-optimize for production
    $steps['optimize'] = runArtisan('optimize');

    disableMaintenance();
    logUpdate('Wartungsmodus deaktiviert');

    $allOk = array_reduce($steps, fn($carry, $s) => $carry && $s['success'], true);
    logUpdate('=== Update ' . ($allOk ? 'erfolgreich' : 'mit Fehlern') . ' abgeschlossen ===');

    $updateResults = ['steps' => $steps, 'success' => $allOk];
    $_SESSION['update_done'] = true;
}

$isAuth = isset($_SESSION['update_auth']) && $_SESSION['update_auth'] === true;

// ─── HTML ─────────────────────────────────────────────────────────────────────
?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HomoCanis – Update</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            line-height: 1.6;
        }
        .container { max-width: 700px; margin: 0 auto; }
        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,.3);
            overflow: hidden;
            margin-bottom: 20px;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            padding: 30px;
            text-align: center;
        }
        .header h1 { font-size: 1.8em; margin-bottom: 8px; }
        .header p  { opacity: .9; }
        .success-header { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .content { padding: 30px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #333; }
        .form-group input {
            width: 100%; padding: 12px;
            border: 2px solid #e0e0e0; border-radius: 6px;
            font-size: 1em; transition: border-color .3s;
        }
        .form-group input:focus { outline: none; border-color: #667eea; }
        .btn {
            display: inline-block; padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff; border: none; border-radius: 6px;
            font-size: 1em; font-weight: 600; cursor: pointer;
            transition: transform .2s, box-shadow .2s; text-decoration: none;
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(102,126,234,.4); }
        .btn-danger { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .btn-secondary { background: #6c757d; }
        .btn-group { display: flex; gap: 10px; margin-top: 20px; flex-wrap: wrap; }
        .alert { padding: 15px 20px; border-radius: 6px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .alert-error   { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
        .alert-warning { background: #fff3cd; color: #856404; border-left: 4px solid #ffc107; }
        .alert-info    { background: #d1ecf1; color: #0c5460;  border-left: 4px solid #17a2b8; }
        .step-list { list-style: none; }
        .step-list li {
            display: flex; align-items: flex-start; gap: 12px;
            padding: 12px; margin-bottom: 8px;
            background: #f8f9fa; border-radius: 6px;
        }
        .step-icon { font-size: 1.2em; flex-shrink: 0; margin-top: 1px; }
        .step-label { font-weight: 600; color: #333; }
        .step-output {
            font-family: monospace; font-size: .8em;
            background: #1e1e2e; color: #cdd6f4;
            padding: 10px; border-radius: 4px; margin-top: 6px;
            white-space: pre-wrap; max-height: 150px; overflow-y: auto;
        }
        .warning-box {
            background: #fff3cd; border: 1px solid #ffc107;
            border-radius: 6px; padding: 15px 20px; margin-bottom: 20px;
        }
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .info-table td { padding: 8px 12px; border-bottom: 1px solid #eee; }
        .info-table td:first-child { font-weight: 600; color: #555; width: 40%; }
        .logout-link { font-size: .85em; color: rgba(255,255,255,.8); text-decoration: none; }
        .logout-link:hover { color: #fff; }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <div class="header <?php echo ($updateResults && $updateResults['success']) ? 'success-header' : ''; ?>">
            <h1>🔄 HomoCanis Update</h1>
            <p>Datenbank-Migrationen & Cache-Aktualisierung</p>
            <?php if ($isAuth): ?>
                <p style="margin-top:12px">
                    <a href="update.php?logout=1" class="logout-link"
                       onclick="document.getElementById('logoutForm').submit(); return false;">Abmelden</a>
                    <form id="logoutForm" method="post" style="display:none">
                        <input type="hidden" name="action" value="logout">
                    </form>
                </p>
            <?php endif; ?>
        </div>
        <div class="content">

<?php if (!$isAuth): ?>
    <!-- ── LOGIN ── -->
    <?php if ($error): ?>
        <div class="alert alert-error">⚠ <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <p style="margin-bottom:24px;color:#555">
        Melde dich mit deinem Administrator-Konto an, um das Update durchzuführen.
    </p>

    <form method="post">
        <input type="hidden" name="action" value="login">
        <div class="form-group">
            <label for="email">E-Mail</label>
            <input type="email" id="email" name="email" required
                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                   placeholder="admin@example.com" autofocus>
        </div>
        <div class="form-group">
            <label for="password">Passwort</label>
            <input type="password" id="password" name="password" required placeholder="••••••••">
        </div>
        <div class="btn-group">
            <button type="submit" class="btn">Anmelden →</button>
        </div>
    </form>

<?php elseif ($updateResults !== null): ?>
    <!-- ── RESULT ── -->
    <?php if ($updateResults['success']): ?>
        <div class="alert alert-success">
            <strong>✓ Update erfolgreich abgeschlossen!</strong><br>
            Die Datenbank wurde migriert, alle Caches wurden geleert.
        </div>
    <?php else: ?>
        <div class="alert alert-error">
            <strong>⚠ Update mit Fehlern abgeschlossen.</strong><br>
            Bitte die Details unten prüfen und ggf. manuell eingreifen.
        </div>
    <?php endif; ?>

    <ul class="step-list">
        <?php
        $stepLabels = [
            'migrate'  => 'Datenbank-Migrationen',
            'config'   => 'Config-Cache geleert',
            'cache'    => 'Application-Cache geleert',
            'view'     => 'View-Cache geleert',
            'route'    => 'Route-Cache geleert',
            'optimize' => 'Production-Optimierung',
        ];
        foreach ($updateResults['steps'] as $key => $result):
            $icon  = $result['success'] ? '✅' : '❌';
            $label = $stepLabels[$key] ?? $key;
        ?>
        <li>
            <span class="step-icon"><?php echo $icon; ?></span>
            <div style="flex:1">
                <div class="step-label"><?php echo htmlspecialchars($label); ?></div>
                <?php if (trim($result['output']) !== ''): ?>
                    <div class="step-output"><?php echo htmlspecialchars(trim($result['output'])); ?></div>
                <?php endif; ?>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>

    <div class="warning-box">
        <strong>⚠ Sicherheitshinweis</strong><br>
        Lösche diese Datei nach dem Update, um unbefugten Zugriff zu verhindern.
    </div>

    <form method="post">
        <div class="btn-group">
            <button type="submit" name="action" value="delete_updater" class="btn btn-danger">
                🗑 update.php jetzt löschen
            </button>
            <a href="/" class="btn btn-secondary">Zur Anwendung →</a>
        </div>
    </form>

<?php else: ?>
    <!-- ── CONFIRM ── -->
    <div class="alert alert-info">
        <strong>Angemeldet als:</strong> <?php echo htmlspecialchars($_SESSION['update_email'] ?? ''); ?>
    </div>

    <div class="warning-box">
        <strong>⚠ Wichtig:</strong> Während des Updates wird für alle Besucher eine
        Wartungsseite angezeigt. Das Update dauert typischerweise 5–30 Sekunden.
    </div>

    <h3 style="margin-bottom:16px;color:#333">Das Update führt folgende Schritte aus:</h3>
    <ul class="step-list">
        <li><span class="step-icon">🔧</span><div><div class="step-label">Wartungsmodus aktivieren</div></div></li>
        <li><span class="step-icon">🗄</span><div><div class="step-label">Datenbank-Migrationen ausführen <em style="font-weight:normal;color:#888">(artisan migrate –force)</em></div></div></li>
        <li><span class="step-icon">🧹</span><div><div class="step-label">Config-, App-, View- und Route-Cache leeren</div></div></li>
        <li><span class="step-icon">⚡</span><div><div class="step-label">Production-Optimierung neu aufbauen</div></div></li>
        <li><span class="step-icon">✅</span><div><div class="step-label">Wartungsmodus deaktivieren</div></div></li>
    </ul>

    <table class="info-table">
        <tr><td>Anwendungsversion</td><td><?php echo htmlspecialchars(getAppVersion()); ?></td></tr>
        <tr><td>PHP CLI Version</td><td><?php echo PHP_VERSION; ?></td></tr>
        <tr><td>Backend-Verzeichnis</td><td><?php echo htmlspecialchars(BACKEND_DIR); ?></td></tr>
        <tr><td>Log-Datei</td><td><?php echo htmlspecialchars(basename(UPDATE_LOG_FILE)); ?></td></tr>
    </table>

    <form method="post" onsubmit="this.querySelector('button[type=submit]').disabled=true;
                                   this.querySelector('button[type=submit]').textContent='⏳ Update läuft...';">
        <input type="hidden" name="action" value="run_update">
        <div class="btn-group">
            <button type="submit" class="btn">🚀 Update starten</button>
            <a href="/" class="btn btn-secondary">Abbrechen</a>
        </div>
    </form>

<?php endif; ?>

        </div><!-- .content -->
    </div><!-- .card -->
</div><!-- .container -->
</body>
</html>
