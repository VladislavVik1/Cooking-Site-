<?php declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$dsn  = getenv('DB_DSN') ?: '';
$user = getenv('DB_USER') ?: '';
$pass = getenv('DB_PASS') ?: '';

if (!$dsn && getenv('DATABASE_URL')) {
    $p = parse_url(getenv('DATABASE_URL'));
    if ($p && ($p['scheme'] ?? null) === 'postgres') {
        $host = $p['host'] ?? 'localhost';
        $port = (int)($p['port'] ?? 5432);
        $db   = ltrim($p['path'] ?? '', '/');
        $user = urldecode($p['user'] ?? $user);
        $pass = urldecode($p['pass'] ?? $pass);
        $dsn  = "pgsql:host={$host};port={$port};dbname={$db};sslmode=require";
    }
}

if (!$dsn) {
    $host = getenv('DB_HOST') ?: 'db';
    $port = getenv('DB_PORT') ?: '5432';
    $name = getenv('DB_NAME') ?: 'cooking_site';
    $user = $user ?: (getenv('DB_USER') ?: 'Admin');
    $pass = $pass ?: (getenv('DB_PASS') ?: '12345ttt');
    $dsn  = "pgsql:host={$host};port={$port};dbname={$name}";
}

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (Throwable $e) {
    error_log('DB connect error: ' . $e->getMessage());
    http_response_code(500);
    exit('Ошибка подключения к базе данных.');
}

if (!function_exists('safe')) {
    function safe($value) {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
function h(string $v): string { return safe($v); }

$uploadsDir = getenv('UPLOADS_DIR');
if (!$uploadsDir) {
    $uploadsDir = getenv('RENDER') ? '/data/uploads' : (__DIR__ . '/public/images');
}
define('UPLOAD_DIR', rtrim($uploadsDir, '/\\') . '/');
define('PUBLIC_UPLOAD_PATH', 'images/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024);
$allowed_types = ['image/jpeg','image/png','image/gif','image/webp'];


$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (Throwable $e) {
    error_log('DB connect error: '.$e->getMessage());
    http_response_code(500);
    exit('Ошибка подключения к базе данных.');
}

if (!function_exists('safe')) {
    function safe($value) {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
function h(string $v): string { return safe($v); }

function uploadImage(array $file): array {
    global $allowed_types;

    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'message' => 'Некорректный файл.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Ошибка загрузки (код '.$file['error'].').'];
    }
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'Файл больше '.(int)(MAX_FILE_SIZE/1048576).' МБ.'];
    }

    if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true) && !is_dir(UPLOAD_DIR)) {
        return ['success' => false, 'message' => 'Не удалось создать папку загрузок.'];
    }

    if (!extension_loaded('fileinfo')) {
        return ['success' => false, 'message' => 'Расширение fileinfo не доступно.'];
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']) ?: '';
    if (!in_array($mime, $allowed_types, true)) {
        return ['success' => false, 'message' => 'Недопустимый тип файла.'];
    }

    $extMap = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
    $ext = $extMap[$mime] ?? 'jpg';

    $newName = bin2hex(random_bytes(16)) . '.' . $ext;
    $target  = rtrim(UPLOAD_DIR, '/\\') . '/' . $newName;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        return ['success' => false, 'message' => 'Не удалось сохранить файл.'];
    }
    @chmod($target, 0644);

    return ['success' => true, 'filename' => $newName, 'publicPath' => PUBLIC_UPLOAD_PATH . $newName];
}

function getYouTubeId(string $url): string {
    $id = '';
    $parts = parse_url($url);
    if (!$parts || !isset($parts['host'])) return $id;

    if (str_contains($parts['host'], 'youtu.be')) {
        $id = ltrim($parts['path'] ?? '', '/');
    } elseif (str_contains($parts['host'], 'youtube.com')) {
        parse_str($parts['query'] ?? '', $q);
        $id = $q['v'] ?? '';
        if (!$id && isset($parts['path']) && preg_match('~^/embed/([A-Za-z0-9_-]{11})~', $parts['path'], $m)) {
            $id = $m[1];
        }
    }
    return (is_string($id) && preg_match('~^[A-Za-z0-9_-]{11}$~', $id)) ? $id : '';
}

function http_json_get(string $url, array $headers = [], int $timeout = 15): ?array {
    if (!extension_loaded('curl')) return null; 
    $u = parse_url($url);
    if (!$u || !in_array(($u['scheme'] ?? ''), ['http','https'], true)) return null;

    $ch = curl_init($url);
    $headers = array_merge(['Accept: application/json','User-Agent: CookingSiteBot/1.0'], $headers);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $resp = curl_exec($ch);
    if ($resp === false) { curl_close($ch); return null; }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code >= 200 && $code < 300) {
        $data = json_decode($resp, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $data : null;
    }
    return null;
}

function http_download_image(string $url, string $targetDir = UPLOAD_DIR, string $prefix = 'stock_'): array {
    if (!extension_loaded('curl')) return [false, 'Расширение curl не установлено'];
    $u = parse_url($url);
    if (!$u || !in_array(($u['scheme'] ?? ''), ['http','https'], true)) {
        return [false, 'Только http/https ссылки поддерживаются'];
    }
    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
        return [false, 'Не удалось создать папку'];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $bin = curl_exec($ch);
    if ($bin === false) { curl_close($ch); return [false, 'Не удалось скачать']; }
    $ctype = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: '';
    $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code < 200 || $code >= 300) return [false, 'HTTP '.$code];
    if (strpos($ctype, 'image/') !== 0) return [false, 'Не изображение'];
    if (strlen($bin) > MAX_FILE_SIZE)   return [false, 'Файл больше лимита'];

    $extMap = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
    $ext  = $extMap[$ctype] ?? 'jpg';
    $name = $prefix . bin2hex(random_bytes(12)) . '.' . $ext;
    $path = rtrim($targetDir, '/\\') . '/' . $name;

    if (file_put_contents($path, $bin) === false) return [false, 'Не удалось записать файл'];
    @chmod($path, 0644);

    return [true, $name, 'publicPath' => PUBLIC_UPLOAD_PATH . $name];
}
