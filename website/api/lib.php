<?php
// Shared helpers for the COC website forms backend (PHP 7.3 or newer, including 8.x).

define('COC_ROOT', dirname(__DIR__));
define('COC_DATA', COC_ROOT . '/data');
date_default_timezone_set('UTC');

// Fallbacks for hosts that do not have the mbstring extension.
if (!function_exists('mb_check_encoding')) {
    function mb_check_encoding($s, $enc = null)
    {
        return preg_match('//u', (string) $s) === 1;
    }
}
if (!function_exists('mb_strlen')) {
    function mb_strlen($s, $enc = null)
    {
        return (int) preg_match_all('/./us', (string) $s);
    }
}
if (!function_exists('mb_substr')) {
    function mb_substr($s, $start, $len = null, $enc = null)
    {
        return implode('', array_slice(preg_split('//u', (string) $s, -1, PREG_SPLIT_NO_EMPTY), $start, $len));
    }
}
if (!function_exists('mb_encode_mimeheader')) {
    function mb_encode_mimeheader($s, $charset = 'UTF-8')
    {
        return '=?UTF-8?B?' . base64_encode((string) $s) . '?=';
    }
}

function coc_config()
{
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }
    $cfg = require __DIR__ . '/config.php';
    $local = __DIR__ . '/config.local.php';
    if (is_file($local)) {
        $cfg = array_replace_recursive($cfg, require $local);
    }
    return $cfg;
}

// Random secret created on first use; used to hash visitor IPs and to name the SQLite file.
function coc_secret()
{
    static $secret = null;
    if ($secret !== null) {
        return $secret;
    }
    $file = COC_DATA . '/secret.php';
    if (is_file($file)) {
        $v = include $file;
        if (is_string($v) && strlen($v) >= 32) {
            return $secret = $v;
        }
    }
    $new = bin2hex(random_bytes(32));
    @file_put_contents($file, "<?php return '" . $new . "';\n", LOCK_EX);
    if (!is_file($file)) {
        throw new RuntimeException('The "data" folder is not writable. Give it write permission on your host.');
    }
    return $secret = $new;
}

function coc_sqlite_path()
{
    return COC_DATA . '/coc-' . substr(hash('sha256', 'db' . coc_secret()), 0, 16) . '.sqlite';
}

function coc_driver()
{
    return coc_config()['db']['driver'] === 'mysql' ? 'mysql' : 'sqlite';
}

function coc_db()
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    $c = coc_config()['db'];
    $opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
    if (coc_driver() === 'mysql') {
        $dsn = 'mysql:host=' . $c['mysql_host'] . ';port=' . (int) $c['mysql_port'] . ';dbname=' . $c['mysql_name'] . ';charset=utf8mb4';
        $pdo = new PDO($dsn, $c['mysql_user'], $c['mysql_pass'], $opts);
        $id = 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
        $tail = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    } else {
        $pdo = new PDO('sqlite:' . coc_sqlite_path(), null, null, $opts);
        $pdo->setAttribute(PDO::ATTR_TIMEOUT, 5);
        $id = 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $tail = '';
    }
    $pdo->exec('CREATE TABLE IF NOT EXISTS submissions (
        id ' . $id . ',
        type VARCHAR(20) NOT NULL,
        name VARCHAR(150) NOT NULL,
        email VARCHAR(190) NOT NULL,
        phone VARCHAR(40) NOT NULL,
        data TEXT NOT NULL,
        status VARCHAR(12) NOT NULL,
        ip_hash CHAR(64) NOT NULL,
        created_at VARCHAR(19) NOT NULL
    )' . $tail);
    $pdo->exec('CREATE TABLE IF NOT EXISTS login_fail (
        id ' . $id . ',
        ip_hash CHAR(64) NOT NULL,
        created_at VARCHAR(19) NOT NULL
    )' . $tail);
    return $pdo;
}

function coc_now()
{
    return gmdate('Y-m-d H:i:s');
}

function coc_ip_hash()
{
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    return hash('sha256', $ip . '|' . coc_secret());
}

function coc_is_https()
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
}

// ---- Form definitions (the server is the source of truth; the HTML must use the same names/values) ----

function coc_regions()
{
    return ['Adamawa', 'Centre', 'East', 'Far North', 'Littoral', 'North', 'North-West', 'West', 'South', 'South-West', 'Outside Cameroon'];
}

function coc_services()
{
    return ['radiation', 'medical', 'surgery', 'imaging', 'nuclear', 'laboratory', 'pharmacy', 'urgent', 'supportive', 'not_sure'];
}

function coc_form_types()
{
    $name = ['t' => 'text', 'req' => true, 'max' => 150];
    $email = ['t' => 'email', 'req' => true];
    return [
        'appointment' => [
            'label' => 'Appointment request',
            'fields' => [
                'name' => $name,
                'phone' => ['t' => 'phone', 'req' => true],
                'email' => ['t' => 'email'],
                'visit_type' => ['t' => 'enum', 'opts' => ['new', 'follow_up', 'second_opinion', 'other'], 'req' => true],
                'service' => ['t' => 'enum', 'opts' => coc_services(), 'req' => true],
                'preferred_date' => ['t' => 'date'],
                'preferred_time' => ['t' => 'enum', 'opts' => ['morning', 'afternoon', 'any']],
                'message' => ['t' => 'textarea'],
            ],
        ],
        'contact' => [
            'label' => 'Contact message',
            'fields' => [
                'name' => $name,
                'email' => $email,
                'phone' => ['t' => 'phone'],
                'topic' => ['t' => 'enum', 'opts' => ['general', 'appointments', 'insurance', 'media', 'careers', 'research', 'feedback', 'other'], 'req' => true],
                'message' => ['t' => 'textarea', 'req' => true],
            ],
        ],
        'affiliate' => [
            'label' => 'Affiliate network inquiry',
            'fields' => [
                'organization' => ['t' => 'text', 'req' => true, 'max' => 150],
                'name' => $name,
                'role' => ['t' => 'text', 'max' => 100],
                'email' => $email,
                'phone' => ['t' => 'phone', 'req' => true],
                'org_type' => ['t' => 'enum', 'opts' => ['physician', 'clinic', 'hospital', 'other'], 'req' => true],
                'region' => ['t' => 'enum', 'opts' => coc_regions(), 'req' => true],
                'city' => ['t' => 'text', 'max' => 100],
                'level' => ['t' => 'enum', 'opts' => ['referral', 'service', 'collaborative', 'advanced', 'not_sure'], 'req' => true],
                'message' => ['t' => 'textarea'],
            ],
        ],
    ];
}

function coc_clean_text($v, $multiline)
{
    $v = str_replace(["\r\n", "\r"], "\n", (string) $v);
    if (!mb_check_encoding($v, 'UTF-8')) {
        return null;
    }
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $v);
    if (!$multiline) {
        $v = preg_replace('/\s+/u', ' ', $v);
    }
    return trim($v);
}

// Returns [cleanValues, invalidFieldNames].
function coc_validate($type, array $input)
{
    $types = coc_form_types();
    $clean = [];
    $bad = [];
    foreach ($types[$type]['fields'] as $key => $spec) {
        $raw = isset($input[$key]) && is_scalar($input[$key]) ? (string) $input[$key] : '';
        $multiline = $spec['t'] === 'textarea';
        $v = coc_clean_text($raw, $multiline);
        if ($v === null) {
            $bad[] = $key;
            continue;
        }
        $required = !empty($spec['req']);
        if ($v === '') {
            if ($required) {
                $bad[] = $key;
            }
            $clean[$key] = '';
            continue;
        }
        switch ($spec['t']) {
            case 'text':
                if (mb_strlen($v) > (isset($spec['max']) ? $spec['max'] : 200)) {
                    $bad[] = $key;
                }
                break;
            case 'textarea':
                if (mb_strlen($v) > 3000) {
                    $bad[] = $key;
                }
                break;
            case 'email':
                if (strlen($v) > 190 || !filter_var($v, FILTER_VALIDATE_EMAIL)) {
                    $bad[] = $key;
                }
                break;
            case 'phone':
                if (!preg_match('/^[0-9+\-\s().]{6,25}$/', $v)) {
                    $bad[] = $key;
                }
                break;
            case 'enum':
                if (!in_array($v, $spec['opts'], true)) {
                    $bad[] = $key;
                }
                break;
            case 'date':
                // YYYY-MM-DD, a real calendar date, from yesterday (time-zone slack) up to one year ahead.
                if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])
                    || $v < gmdate('Y-m-d', time() - 86400) || $v > gmdate('Y-m-d', time() + 366 * 86400)) {
                    $bad[] = $key;
                }
                break;
        }
        $clean[$key] = $v;
    }
    return [$clean, $bad];
}

function coc_notify($type, array $clean)
{
    $cfg = coc_config();
    $to = trim($cfg['notify_email']);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return;
    }
    $types = coc_form_types();
    $host = isset($_SERVER['HTTP_HOST']) ? preg_replace('/[^A-Za-z0-9.\-]/', '', $_SERVER['HTTP_HOST']) : 'localhost';
    $from = trim($cfg['mail_from']) !== '' ? trim($cfg['mail_from']) : 'no-reply@' . preg_replace('/^www\./', '', $host);
    $subject = '[COC website] ' . $types[$type]['label'] . ' from ' . (isset($clean['name']) ? $clean['name'] : '');
    $lines = [];
    foreach ($clean as $k => $v) {
        if ($v !== '') {
            $lines[] = ucfirst(str_replace('_', ' ', $k)) . ': ' . $v;
        }
    }
    $lines[] = '';
    $lines[] = 'View and manage all submissions in the admin area of the website.';
    $headers = "From: " . preg_replace('/[\r\n]+/', '', $from) . "\r\n"
        . "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n";
    if (!empty($clean['email'])) {
        $headers .= 'Reply-To: ' . preg_replace('/[\r\n]+/', '', $clean['email']) . "\r\n";
    }
    @mail($to, mb_encode_mimeheader(preg_replace('/[\r\n]+/', ' ', $subject), 'UTF-8'), implode("\n", $lines), $headers);
}
