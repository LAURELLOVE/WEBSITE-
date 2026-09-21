<?php
// Password-protected admin area: view, filter, export and manage form submissions.
require dirname(__DIR__) . '/api/lib.php';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');

session_name('cocadmin');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => coc_is_https(),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

function h($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function csrf_ok()
{
    return isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string) $_POST['csrf']);
}

function local_time($utc)
{
    try {
        $d = new DateTime($utc, new DateTimeZone('UTC'));
        $d->setTimezone(new DateTimeZone('Africa/Douala'));
        return $d->format('Y-m-d H:i');
    } catch (Exception $e) {
        return $utc;
    }
}

function summary_of($type, array $d)
{
    $pick = function ($keys) use ($d) {
        $out = [];
        foreach ($keys as $k) {
            if (!empty($d[$k])) {
                $out[] = $d[$k];
            }
        }
        return implode(' · ', $out);
    };
    switch ($type) {
        case 'appointment':
            return $pick(['service', 'visit_type', 'preferred_date', 'preferred_time']);
        case 'affiliate':
            return $pick(['organization', 'org_type', 'region']);
        default:
            return $pick(['topic']) . (!empty($d['message']) ? ' · ' . mb_substr($d['message'], 0, 60) : '');
    }
}

$cfg = coc_config();
$hash = (string) $cfg['admin_password_hash'];
$types = coc_form_types();
$error = '';
$setupHash = '';

// ---- No password configured yet: show a helper that only *generates* a hash to paste into config. ----
if ($hash === '') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])) {
        $pw = (string) $_POST['new_password'];
        if (strlen($pw) < 10) {
            $error = 'Use at least 10 characters.';
        } else {
            $setupHash = password_hash($pw, PASSWORD_DEFAULT);
        }
    }
    page_start('Admin setup');
    echo '<h1>Admin setup</h1><p>No admin password is set yet. Choose one; you will get a line to paste into <code>api/config.local.php</code> on your host.</p>';
    if ($setupHash !== '') {
        echo '<p>Create (or edit) the file <code>api/config.local.php</code> so it contains exactly:</p><pre>&lt;?php
return [
    \'admin_password_hash\' =&gt; \'' . h($setupHash) . '\',
];</pre><p>Then reload this page and sign in with your password. Keep the password private.</p>';
        if (!coc_is_https()) {
            echo '<p class="warn">This page is not using HTTPS. On your live site, do this over https:// so your password is not sent in the clear.</p>';
        }
    } else {
        if ($error) {
            echo '<p class="warn">' . h($error) . '</p>';
        }
        echo '<form method="post"><label>New admin password (10+ characters)<input type="password" name="new_password" autocomplete="new-password" required minlength="10"></label><button>Generate</button></form>';
    }
    page_end();
    exit;
}

// ---- Logout ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout']) && csrf_ok()) {
    $_SESSION = [];
    session_destroy();
    header('Location: ./');
    exit;
}

// ---- Login ----
if (empty($_SESSION['auth'])) {
    try {
        $db = coc_db();
    } catch (Throwable $e) {
        error_log('COC admin database error: ' . $e->getMessage());
        http_response_code(500);
        page_start('Database problem');
        echo '<h1>Cannot connect to the database</h1><p>Check the database host, name, user and password in <code>api/config.local.php</code> on the server. If you just created the database in Network Solutions, make sure the user has been given access to it.</p>';
        page_end();
        exit;
    }
    $ip = coc_ip_hash();
    $db->prepare('DELETE FROM login_fail WHERE created_at < ?')->execute([gmdate('Y-m-d H:i:s', time() - 86400)]);
    $st = $db->prepare('SELECT COUNT(*) FROM login_fail WHERE ip_hash = ? AND created_at > ?');
    $st->execute([$ip, gmdate('Y-m-d H:i:s', time() - 900)]);
    $locked = (int) $st->fetchColumn() >= 5;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        if ($locked) {
            $error = 'Too many failed attempts. Try again in 15 minutes.';
        } elseif (password_verify((string) $_POST['password'], $hash)) {
            session_regenerate_id(true);
            $_SESSION['auth'] = true;
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
            header('Location: ./');
            exit;
        } else {
            $db->prepare('INSERT INTO login_fail (ip_hash, created_at) VALUES (?,?)')->execute([$ip, coc_now()]);
            sleep(1);
            $error = 'Wrong password.';
        }
    } elseif ($locked) {
        $error = 'Too many failed attempts. Try again in 15 minutes.';
    }
    page_start('Sign in');
    echo '<h1>COC admin</h1>';
    if ($error) {
        echo '<p class="warn">' . h($error) . '</p>';
    }
    echo '<form method="post"><label>Password<input type="password" name="password" autocomplete="current-password" required autofocus></label><button>Sign in</button></form>';
    page_end();
    exit;
}

$db = coc_db();

// ---- Actions on submissions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!csrf_ok()) {
        http_response_code(400);
        exit('Invalid request. Go back and reload the page.');
    }
    $id = (int) (isset($_POST['id']) ? $_POST['id'] : 0);
    if ($_POST['action'] === 'status') {
        $new = isset($_POST['status']) && $_POST['status'] === 'handled' ? 'handled' : 'new';
        $db->prepare('UPDATE submissions SET status = ? WHERE id = ?')->execute([$new, $id]);
    } elseif ($_POST['action'] === 'delete') {
        $db->prepare('DELETE FROM submissions WHERE id = ?')->execute([$id]);
    }
    header('Location: ' . (isset($_POST['back']) && preg_match('/^\?[A-Za-z0-9=&_.\-]*$/', $_POST['back']) ? $_POST['back'] : './'));
    exit;
}

$fType = isset($_GET['type']) && isset($types[$_GET['type']]) ? $_GET['type'] : '';
$fStatus = isset($_GET['status']) && in_array($_GET['status'], ['new', 'handled'], true) ? $_GET['status'] : '';

// ---- CSV export ----
if (isset($_GET['export'])) {
    $where = [];
    $args = [];
    if ($fType !== '') { $where[] = 'type = ?'; $args[] = $fType; }
    if ($fStatus !== '') { $where[] = 'status = ?'; $args[] = $fStatus; }
    $st = $db->prepare('SELECT * FROM submissions' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY id DESC');
    $st->execute($args);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="coc-submissions-' . gmdate('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    $safe = function ($v) {
        $v = (string) $v;
        return $v !== '' && strpos("=+-@\t\r", $v[0]) !== false ? "'" . $v : $v;
    };
    fputcsv($out, ['id', 'received (Douala time)', 'type', 'status', 'name', 'email', 'phone', 'details']);
    foreach ($st as $r) {
        $d = json_decode($r['data'], true) ?: [];
        $parts = [];
        foreach ($d as $k => $v) {
            $parts[] = $k . ': ' . str_replace("\n", ' ', $v);
        }
        fputcsv($out, array_map($safe, [$r['id'], local_time($r['created_at']), $r['type'], $r['status'], $r['name'], $r['email'], $r['phone'], implode(' | ', $parts)]));
    }
    exit;
}

// ---- Dashboard ----
$counts = [];
foreach ($db->query('SELECT type, status, COUNT(*) AS n FROM submissions GROUP BY type, status') as $r) {
    $counts[$r['type']][$r['status']] = (int) $r['n'];
}
$where = [];
$args = [];
if ($fType !== '') { $where[] = 'type = ?'; $args[] = $fType; }
if ($fStatus !== '') { $where[] = 'status = ?'; $args[] = $fStatus; }
$st = $db->prepare('SELECT * FROM submissions' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY id DESC LIMIT 200');
$st->execute($args);
$rows = $st->fetchAll();
$back = '?' . http_build_query(array_filter(['type' => $fType, 'status' => $fStatus]));
$tab = isset($_GET['tab']) && $_GET['tab'] === 'check' ? 'check' : 'inbox';

page_start('Submissions');
echo '<div class="bar"><h1>COC submissions</h1><span><a href="?tab=check">System check</a><form method="post" class="inline"><input type="hidden" name="csrf" value="' . h($_SESSION['csrf']) . '"><button name="logout" value="1" class="ghost">Sign out</button></form></span></div>';

if ($tab === 'check') {
    $checks = [];
    $checks[] = ['PHP version (7.3 or newer needed)', PHP_VERSION, version_compare(PHP_VERSION, '7.3.0', '>=')];
    $checks[] = ['Database driver', coc_driver(), true];
    $checks[] = ['PDO extension (database access)', extension_loaded('pdo') ? 'yes' : 'no', extension_loaded('pdo')];
    if (coc_driver() === 'mysql') {
        $checks[] = ['PDO MySQL driver', extension_loaded('pdo_mysql') ? 'yes' : 'no', extension_loaded('pdo_mysql')];
    } else {
        $checks[] = ['PDO SQLite driver', extension_loaded('pdo_sqlite') ? 'yes' : 'no', extension_loaded('pdo_sqlite')];
    }
    $checks[] = ['mbstring (optional - a built-in fallback is used without it)', extension_loaded('mbstring') ? 'yes' : 'no - fallback in use', true];
    $checks[] = ['mail() available for email alerts', function_exists('mail') ? 'yes' : 'no', function_exists('mail')];
    $ok = true;
    try {
        $db->query('SELECT COUNT(*) FROM submissions')->fetchColumn();
    } catch (Throwable $e) {
        $ok = false;
    }
    $checks[] = ['Database reachable and tables exist', $ok ? 'yes' : 'no', $ok];
    $checks[] = ['Data folder writable', is_writable(COC_DATA) ? 'yes' : 'no', is_writable(COC_DATA)];
    $mailTo = trim($cfg['notify_email']);
    $checks[] = ['Email alerts (notify_email set)', $mailTo !== '' ? $mailTo : 'not set - you will not get emails', $mailTo !== ''];
    $checks[] = ['HTTPS', coc_is_https() ? 'yes' : 'no - use https:// on the live site', coc_is_https()];
    echo '<h2>System check</h2><table><tr><th>Check</th><th>Result</th><th></th></tr>';
    foreach ($checks as $c) {
        echo '<tr><td>' . h($c[0]) . '</td><td>' . h($c[1]) . '</td><td>' . ($c[2] ? '<b class="ok">OK</b>' : '<b class="warn">Check</b>') . '</td></tr>';
    }
    if (coc_driver() === 'sqlite') {
        $url = '../data/' . basename(coc_sqlite_path());
        echo '<tr><td>Database file is private</td><td id="privacy">testing…</td><td id="privacyflag"></td></tr>';
        echo '<script>fetch(' . json_encode($url) . ',{cache:"no-store"}).then(function(r){var bad=r.ok;document.getElementById("privacy").textContent=bad?"NO - the database file can be downloaded! Switch to MySQL (see README).":"yes (blocked, HTTP "+r.status+")";document.getElementById("privacyflag").innerHTML=bad?"<b class=warn>FIX</b>":"<b class=ok>OK</b>";}).catch(function(){document.getElementById("privacy").textContent="could not test";});</script>';
    }
    echo '</table><p><a href="./">← Back to submissions</a></p>';
    page_end();
    exit;
}

$labels = ['appointment' => 'Appointments', 'contact' => 'Contact', 'affiliate' => 'Affiliate network'];
echo '<div class="chips"><a class="chip' . ($fType === '' ? ' on' : '') . '" href="?' . h(http_build_query(array_filter(['status' => $fStatus]))) . '">All</a>';
foreach ($labels as $t => $lab) {
    $n = isset($counts[$t]['new']) ? $counts[$t]['new'] : 0;
    echo '<a class="chip' . ($fType === $t ? ' on' : '') . '" href="?' . h(http_build_query(array_filter(['type' => $t, 'status' => $fStatus]))) . '">' . h($lab) . ($n ? ' <b>' . $n . ' new</b>' : '') . '</a>';
}
echo '</div><div class="chips">';
foreach (['' => 'Any status', 'new' => 'New', 'handled' => 'Handled'] as $s => $lab) {
    echo '<a class="chip' . ($fStatus === $s ? ' on' : '') . '" href="?' . h(http_build_query(array_filter(['type' => $fType, 'status' => $s]))) . '">' . h($lab) . '</a>';
}
echo '<a class="chip" href="?' . h(http_build_query(array_filter(['export' => 'csv', 'type' => $fType, 'status' => $fStatus]))) . '">⬇ Export CSV</a></div>';

if (!$rows) {
    echo '<p>No submissions yet.</p>';
} else {
    echo '<table><tr><th>Received</th><th>Type</th><th>From</th><th>Details</th><th>Status</th><th></th></tr>';
    foreach ($rows as $r) {
        $d = json_decode($r['data'], true) ?: [];
        echo '<tr class="' . h($r['status']) . '"><td>' . h(local_time($r['created_at'])) . '</td><td>' . h($labels[$r['type']] ?? $r['type']) . '</td><td><b>' . h($r['name']) . '</b><br>';
        if ($r['email']) { echo '<a href="mailto:' . h($r['email']) . '">' . h($r['email']) . '</a><br>'; }
        if ($r['phone']) { echo '<a href="tel:' . h(preg_replace('/[^0-9+]/', '', $r['phone'])) . '">' . h($r['phone']) . '</a>'; }
        echo '</td><td><details><summary>' . h(summary_of($r['type'], $d)) . '</summary><dl>';
        foreach ($d as $k => $v) {
            echo '<dt>' . h(ucfirst(str_replace('_', ' ', $k))) . '</dt><dd>' . nl2br(h($v)) . '</dd>';
        }
        echo '</dl></details></td><td>' . h($r['status']) . '</td><td class="acts">';
        $tok = '<input type="hidden" name="csrf" value="' . h($_SESSION['csrf']) . '"><input type="hidden" name="id" value="' . (int) $r['id'] . '"><input type="hidden" name="back" value="' . h($back === '?' ? './' : $back) . '">';
        $next = $r['status'] === 'new' ? 'handled' : 'new';
        echo '<form method="post" class="inline">' . $tok . '<input type="hidden" name="action" value="status"><input type="hidden" name="status" value="' . $next . '"><button class="ghost">' . ($next === 'handled' ? 'Mark handled' : 'Reopen') . '</button></form>';
        echo '<form method="post" class="inline" onsubmit="return confirm(\'Delete this submission permanently?\')">' . $tok . '<input type="hidden" name="action" value="delete"><button class="ghost danger">Delete</button></form>';
        echo '</td></tr>';
    }
    echo '</table><p class="small">Showing the latest ' . count($rows) . ' (max 200). Times are Cameroon time. Use Export CSV for the full list.</p>';
}
page_end();

// ---- page shell ----
function page_start($title)
{
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>' . h($title) . ' | COC admin</title><style>
body{font-family:Arial,Helvetica,sans-serif;margin:0;background:#f4f8fc;color:#18223a;line-height:1.5}
main{max-width:1100px;margin:0 auto;padding:24px 16px}
h1{font-size:24px;margin:0;color:#073b83}h2{color:#073b83}
.bar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:16px}
.bar span{display:flex;gap:14px;align-items:center}
a{color:#073b83}
form.inline{display:inline}
label{display:block;font-weight:700;margin:12px 0}
input[type=password]{display:block;width:100%;max-width:340px;margin-top:6px;padding:10px;border:1px solid #c3d2e4;border-radius:6px;font-size:16px}
button{background:#073b83;color:#fff;border:0;border-radius:6px;padding:10px 16px;font-weight:700;cursor:pointer;font-size:14px}
button.ghost{background:#fff;color:#073b83;border:1px solid #c3d2e4;padding:6px 10px;margin:2px}
button.danger{color:#b00020}
.chips{display:flex;gap:8px;flex-wrap:wrap;margin:8px 0}
.chip{background:#fff;border:1px solid #c3d2e4;border-radius:999px;padding:5px 12px;text-decoration:none;font-size:14px}
.chip.on{background:#073b83;color:#fff;border-color:#073b83}.chip b{color:#d71920}.chip.on b{color:#ffc9cc}
table{border-collapse:collapse;width:100%;background:#fff;border:1px solid #dbe7f2;margin-top:12px}
th,td{padding:9px 10px;text-align:left;vertical-align:top;border-bottom:1px solid #eee;font-size:14px}
th{background:#e6eef8}tr.new td:first-child{border-left:4px solid #d71920}
dl{margin:8px 0 0}dt{font-weight:700;font-size:12px;color:#6a6f7c;text-transform:uppercase}dd{margin:0 0 6px}
summary{cursor:pointer}.acts{white-space:nowrap}.small{font-size:13px;color:#6a6f7c}
.warn{color:#b00020;font-weight:700}.ok{color:#167d63}pre{background:#fff;border:1px solid #dbe7f2;padding:12px;overflow:auto}
@media(max-width:700px){table,thead,tbody,tr,td,th{display:block}th{display:none}td{border:0}tr{border-bottom:2px solid #dbe7f2;margin-bottom:8px}}
</style></head><body><main>';
}

function page_end()
{
    echo '</main></body></html>';
}
