<?php
/*
 * PayerNex for DHRU Fusion — Quick Pay helpers (DB, gateway settings, rate limit).
 * Used by payernex/quickpay.php and payernex/status.php, which run without the
 * DHRU bootstrap so they stay fast and always answer JSON.
 */

// JSON endpoints: a PHP notice must never corrupt the response.
@ini_set('display_errors', '0');

if (!defined('PAYERNEX_API_URL')) define('PAYERNEX_API_URL', getenv('PAYERNEX_API_URL') ?: 'https://payernex.com');

function pnx_out($code, $data)
{
    http_response_code($code);
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode($data);
    exit;
}

// Cloudflare edge ranges, so CF-Connecting-IP is believed only from Cloudflare.
function pnx_is_cloudflare($ip)
{
    $ip = (string) $ip;
    if (strpos($ip, ':') !== false) {
        foreach (array('2400:cb00:', '2606:4700:', '2803:f800:', '2405:b500:', '2405:8100:', '2a06:98c0:', '2c0f:f248:') as $p) {
            if (stripos($ip, $p) === 0) return true;
        }
        return false;
    }
    $n = ip2long($ip);
    if ($n === false) return false;
    foreach (array('173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18', '108.162.192.0/18',
        '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
        '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22') as $cidr) {
        list($net, $bits) = explode('/', $cidr);
        $mask = -1 << (32 - (int) $bits);
        if ((ip2long($net) & $mask) === ($n & $mask)) return true;
    }
    return false;
}

function pnx_client_ip()
{
    $peer = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP']) && pnx_is_cloudflare($peer)) return $_SERVER['HTTP_CF_CONNECTING_IP'];
    return $peer;
}

// Fixed-window limiter in the system temp folder.
function pnx_rate_limit($bucket, $limit, $window)
{
    $dir = sys_get_temp_dir() . '/payernex_rl';
    @mkdir($dir, 0700, true);
    $file = $dir . '/' . preg_replace('/[^a-z0-9]/i', '', $bucket) . '_' . md5(pnx_client_ip());
    $now = time();
    $data = @json_decode(@file_get_contents($file), true);
    if (!is_array($data) || ($data['w'] + $window) < $now) $data = array('w' => $now, 'c' => 0);
    $data['c']++;
    @file_put_contents($file, json_encode($data), LOCK_EX);
    return $data['c'] <= $limit;
}

// DHRU database credentials from configs/config.php (read as text, never executed).
function pnx_db()
{
    $roots = array(dirname(__DIR__), isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '');
    foreach ($roots as $r) {
        if (!$r) continue;
        $p = rtrim($r, '/') . '/configs/config.php';
        if (!is_readable($p)) continue;
        $src = file_get_contents($p);
        $grab = function ($k) use ($src) {
            return preg_match('/\$config\[\s*[\'"]' . $k . '[\'"]\s*\]\s*=\s*[\'"](.*?)[\'"]\s*;/s', $src, $m) ? $m[1] : null;
        };
        $u = $grab('user'); $pw = $grab('pass'); $db = $grab('database');
        $h = $grab('server'); if ($h === null) $h = $grab('host');
        if ($u !== null && $db !== null) {
            mysqli_report(MYSQLI_REPORT_OFF);
            $h = $h ? $h : '127.0.0.1';
            $port = null;
            // "host:port" (some hosts write the port into the server field).
            if (substr_count($h, ':') === 1) { list($h, $port) = explode(':', $h); $port = (int) $port; }
            $m = @new mysqli($h, $u, $pw, $db, $port ? $port : null);
            if (!$m->connect_errno) { $m->set_charset('utf8mb4'); return $m; }
        }
    }
    return null;
}

// PayerNex gateway settings entered in DHRU Admin → Payment Gateways.
function pnx_gateway_config($db)
{
    $out = array();
    $res = @$db->query("SELECT setting, value FROM tbl_paymentgateways WHERE gateway='payernex'");
    if ($res) while ($row = $res->fetch_assoc()) $out[$row['setting']] = $row['value'];
    return $out;
}

function pnx_on($v)
{
    return in_array(strtolower((string) $v), array('on', 'yes', '1', 'true'), true);
}

// Site address written by the gateway module from DHRU's own configuration.
// Never derived from the request Host header, which the caller controls.
function pnx_site_url()
{
    $f = __DIR__ . '/site.json';
    $d = is_readable($f) ? json_decode(file_get_contents($f), true) : null;
    return (is_array($d) && !empty($d['site'])) ? rtrim($d['site'], '/') : null;
}

function pnx_api($method, $path, $apiKey, $body = null)
{
    $ch = curl_init(PAYERNEX_API_URL . $path);
    $opts = array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_HTTPHEADER => array('Authorization: Bearer ' . $apiKey, 'Accept: application/json', 'Content-Type: application/json'),
    );
    if ($method === 'POST') { $opts[CURLOPT_POST] = true; $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_SLASHES); }
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    return array($code, is_string($resp) ? json_decode($resp, true) : null);
}
