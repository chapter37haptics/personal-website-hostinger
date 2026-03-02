<?php
require_once(__DIR__ . '/../db_config.php');

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$page     = isset($body['page'])     ? substr($body['page'], 0, 255)     : null;
$referrer = isset($body['referrer']) ? substr($body['referrer'], 0, 500)  : null;

// Real IP (handles proxies/load balancers)
function get_real_ip() {
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

$ip = get_real_ip();

// Server-side geo lookup via ip-api.com (free, no key needed, 45 req/min)
$country = null;
$city    = null;
$geo_url = "http://ip-api.com/json/" . urlencode($ip) . "?fields=country,city,status";
$ctx = stream_context_create(['http' => ['timeout' => 3]]);
$geo_raw = @file_get_contents($geo_url, false, $ctx);
if ($geo_raw) {
    $geo = json_decode($geo_raw, true);
    if (isset($geo['status']) && $geo['status'] === 'success') {
        $country = $geo['country'] ?? null;
        $city    = $geo['city']    ?? null;
    }
}

// User-agent parsing (no external call)
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

function detect_device(string $ua): string {
    if (preg_match('/tablet|ipad|playbook|silk/i', $ua))         return 'Tablet';
    if (preg_match('/mobile|android|iphone|ipod|blackberry|opera mini|iemobile/i', $ua)) return 'Mobile';
    return 'Desktop';
}

function detect_browser(string $ua): string {
    if (preg_match('/Edg\//i', $ua))          return 'Edge';
    if (preg_match('/OPR\/|Opera/i', $ua))    return 'Opera';
    if (preg_match('/Chrome\/[0-9]/i', $ua))  return 'Chrome';
    if (preg_match('/Firefox\//i', $ua))      return 'Firefox';
    if (preg_match('/Safari\//i', $ua))       return 'Safari';
    if (preg_match('/MSIE |Trident\//i', $ua)) return 'IE';
    return 'Other';
}

function detect_os(string $ua): string {
    if (preg_match('/Windows NT/i', $ua))     return 'Windows';
    if (preg_match('/iPhone|iPad|iPod/i', $ua)) return 'iOS';
    if (preg_match('/Mac OS X/i', $ua))       return 'macOS';
    if (preg_match('/Android/i', $ua))        return 'Android';
    if (preg_match('/Linux/i', $ua))          return 'Linux';
    return 'Other';
}

$device_type = detect_device($ua);
$browser     = detect_browser($ua);
$os          = detect_os($ua);

// Insert into MySQL
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASSWORD,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $stmt = $pdo->prepare(
        'INSERT INTO visits (page, referrer, user_agent, ip, country, city, device_type, browser, os)
         VALUES (:page, :referrer, :ua, :ip, :country, :city, :device, :browser, :os)'
    );
    $stmt->execute([
        ':page'     => $page,
        ':referrer' => $referrer,
        ':ua'       => substr($ua, 0, 500),
        ':ip'       => $ip,
        ':country'  => $country,
        ':city'     => $city,
        ':device'   => $device_type,
        ':browser'  => $browser,
        ':os'       => $os,
    ]);
} catch (Exception $e) {
    // Silent fail — never break the visitor's experience
    http_response_code(500);
    echo json_encode(['ok' => false]);
    exit;
}

echo json_encode(['ok' => true]);
