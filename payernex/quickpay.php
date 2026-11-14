<?php
/*
 * PayerNex Quick Pay — returns the logged-in customer's permanent Express
 * Wallet address and page. Called by the widget on the Add Funds page.
 *
 * The customer is identified by their DHRU username, which the widget gets from
 * the page ({$userdetails.username}). Knowing a username only shows that
 * customer's deposit address: deposits to it still credit that customer.
 * Unknown usernames get the same answer as a lookup failure (no enumeration).
 */
require_once __DIR__ . '/lib.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') pnx_out(405, array('ok' => false, 'error' => 'POST only'));
if (!pnx_rate_limit('quickpay', 20, 60)) pnx_out(429, array('ok' => false, 'error' => 'Too many requests. Please wait a moment.'));

$db = pnx_db();
if (!$db) pnx_out(500, array('ok' => false, 'error' => 'Quick Pay is unavailable right now.'));
$cfg = pnx_gateway_config($db);
$apiKey = trim(isset($cfg['payernexAPIKey']) ? $cfg['payernexAPIKey'] : '');
if ($apiKey === '' || !pnx_on(isset($cfg['quickpay']) ? $cfg['quickpay'] : '')) {
    pnx_out(503, array('ok' => false, 'error' => 'Quick Pay is not enabled.'));
}
$site = pnx_site_url();
if (!$site) pnx_out(503, array('ok' => false, 'error' => 'Quick Pay is not set up yet. Ask the store to open Payment Gateways → PayerNex once.'));

$username = trim(isset($_POST['username']) ? (string) $_POST['username'] : '');
if ($username === '' || strlen($username) > 190) pnx_out(403, array('ok' => false, 'error' => 'Please sign in again.'));

$stmt = $db->prepare('SELECT * FROM tblUsers WHERE username = ? LIMIT 1');
$stmt->bind_param('s', $username);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$user || empty($user['id'])) pnx_out(403, array('ok' => false, 'error' => 'Please sign in again.'));

$first = '';
foreach (array('firstname', 'first_name', 'fname') as $k) if (!empty($user[$k])) { $first = $user[$k]; break; }
$last = '';
foreach (array('lastname', 'last_name', 'lname') as $k) if (!empty($user[$k])) { $last = $user[$k]; break; }
$name = trim($first . ' ' . $last);
if ($name === '') $name = $user['username'];

list($code, $res) = pnx_api('POST', '/api/v1/express_wallet', $apiKey, array(
    // Numeric DHRU user id: stays the same if the username changes.
    'merchant_client_id'    => 'dhru-' . (int) $user['id'],
    'merchant_client_email' => isset($user['email']) ? (string) $user['email'] : null,
    'merchant_client_name'  => $name,
    'ipn_url'               => $site . '/modules/gateways/callback/payernex.php',
));
if ($code < 200 || $code >= 300 || !is_array($res) || !isset($res['data']['order_url'])) {
    $msg = (is_array($res) && !empty($res['message'])) ? $res['message'] : 'Could not open Quick Pay. Please try again.';
    pnx_out(502, array('ok' => false, 'error' => $msg));
}
$d = $res['data'];
pnx_out(200, array(
    'ok'          => true,
    'order_url'   => $d['order_url'] . '?embed=1',
    'address'     => $d['address'],
    'networks'    => isset($d['networks']) ? $d['networks'] : array(),
    'min_deposit' => isset($d['min_deposit']) ? $d['min_deposit'] : null,
    'fee'         => isset($d['fee']) ? $d['fee'] : null,
));
