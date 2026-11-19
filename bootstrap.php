<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Jakarta');
$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_name('rasaraya_php_v2');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

function app_config(): array
{
    static $config;
    if (is_array($config)) {
        return $config;
    }

    $path = __DIR__ . DIRECTORY_SEPARATOR . 'config.local.php';
    if (!is_file($path)) {
        $config = [];
        return $config;
    }
    $loaded = require $path;
    if (!is_array($loaded)) {
        throw new RuntimeException('config.local.php harus mengembalikan array konfigurasi.');
    }
    $config = $loaded;
    return $config;
}

function config_value(string $key, string $default = ''): string
{
    $config = app_config();
    if (isset($config[$key]) && is_string($config[$key])) {
        return $config[$key];
    }
    $value = getenv($key);
    return is_string($value) ? $value : $default;
}

function db(): PDO
{
    static $connection;
    if ($connection instanceof PDO) {
        return $connection;
    }

    $host = config_value('DB_HOST', '127.0.0.1') ?: '127.0.0.1';
    $port = config_value('DB_PORT', '3306') ?: '3306';
    $name = config_value('DB_NAME', 'rasaraya_php') ?: 'rasaraya_php';
    $user = config_value('DB_USER', 'root') ?: 'root';
    $password = config_value('DB_PASS');

    $connection = new PDO(
        "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    $connection->exec("SET time_zone = '+07:00'");

    return $connection;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    $expected = $_SESSION['csrf_token'] ?? '';
    if (!is_string($submitted) || !is_string($expected) || $expected === '' ||
        !hash_equals($expected, $submitted)) {
        http_response_code(400);
        exit('Permintaan tidak valid. Muat ulang halaman, lalu coba lagi.');
    }
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function take_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($flash) ? $flash : null;
}

function page_url(string $page, array $parameters = []): string
{
    return 'index.php?' . http_build_query(['page' => $page] + $parameters);
}

function redirect_to(string $page, array $parameters = []): never
{
    header('Location: ' . page_url($page, $parameters), true, 303);
    exit;
}

function current_user(PDO $pdo): ?array
{
    $id = $_SESSION['user_id'] ?? null;
    if (!is_int($id) && !ctype_digit((string) $id)) {
        return null;
    }

    $statement = $pdo->prepare(
        'SELECT id, full_name, whatsapp, business_name, email, role, created_at
         FROM users WHERE id = ?'
    );
    $statement->execute([(int) $id]);
    $user = $statement->fetch();
    if (!$user) {
        unset($_SESSION['user_id']);
        return null;
    }
    return $user;
}

function is_seller(array $user): bool
{
    return in_array($user['role'], ['PENJUAL', 'ADMIN_PENJUAL'], true);
}

function format_rupiah(int|float|string $amount): string
{
    return 'Rp' . number_format((float) $amount, 0, ',', '.');
}

function app_url(array $parameters = []): string
{
    $configured = config_value('APP_URL');
    if ($configured !== '') {
        $parts = parse_url($configured);
        if (!is_array($parts) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true) ||
            !isset($parts['host'])) {
            throw new RuntimeException('APP_URL harus berupa alamat HTTP atau HTTPS yang valid.');
        }
        $basePath = rtrim($parts['path'] ?? '', '/');
        $base = $parts['scheme'] . '://' . $parts['host'] .
            (isset($parts['port']) ? ':' . $parts['port'] : '') .
            $basePath . '/index.php';
        return $base . ($parameters ? '?' . http_build_query($parameters) : '');
    }

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (!preg_match('/\A[a-z0-9.-]+(?::\d{1,5})?\z/i', $host)) {
        $host = 'localhost';
    }
    $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
    $directory = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');
    $script = ($directory === '' ? '' : $directory) . '/index.php';
    return $scheme . '://' . $host . $script . ($parameters ? '?' . http_build_query($parameters) : '');
}

function form_value(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

function post_integer(string $key, int $minimum = 0): int
{
    $value = filter_var($_POST[$key] ?? null, FILTER_VALIDATE_INT);
    if ($value === false || $value < $minimum) {
        throw new InvalidArgumentException('Periksa kembali angka yang dimasukkan.');
    }
    return $value;
}
