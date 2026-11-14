<?php
/*
 * ==========================================================
 * PAYERNEX - DHRU FUSION CALLBACK / WEBHOOK HANDLER
 *
 * Author: Umair Abubakkar
 * URL: https://github.com/umairabubakkar
 *
 * PayerNex — Non-Custodial Binance Pay C2C Payment Gateway
 * ==========================================================
 *
 *  2026 PayerNex. All rights reserved.
 *
 *  This file receives webhook notifications from PayerNex
 *  when a payment is confirmed via Binance Pay C2C.
 *
 */

// Default Dhru include files
define("DEFINE_MY_ACCESS", true);
define("DEFINE_DHRU_FILE", true);
include '../../../comm.php';
require '../../../includes/fun.inc.php';
include '../../../includes/gateway.fun.php';
include '../../../includes/invoice.fun.php';

// PayerNex API URL (hardcoded — same as gateway module)
if (!defined('PAYERNEX_API_URL')) define('PAYERNEX_API_URL', getenv('PAYERNEX_API_URL') ?: 'https://payernex.com');

// Load gateway parameters
$GATEWAY = loadGatewayModule('payernex');
$apiKey = trim($GATEWAY['payernexAPIKey']);
$webhookSecret = trim($GATEWAY['payernexWebhookSecret']);

// Read incoming webhook payload
$rawPayload = file_get_contents('php://input');
$GETDATA = json_decode($rawPayload, true);

if (!$GETDATA) {
    logTransaction('payernex', 'Invalid payload: ' . $rawPayload, 'Error');
    http_response_code(400);
    exit();
}

// ─────────────────────────────────────────────
// SECURITY: HMAC-SHA256 signature verification (MANDATORY)
// ─────────────────────────────────────────────
if (empty($webhookSecret)) {
    logTransaction('payernex', 'Webhook secret not configured — rejecting all webhooks', 'Error-NoSecret');
    http_response_code(500);
    exit();
}

$receivedSignature = isset($_SERVER['HTTP_X_SIGNATURE']) ? $_SERVER['HTTP_X_SIGNATURE'] : '';
$expectedSignature = hash_hmac('sha256', $rawPayload, $webhookSecret);

if (!hash_equals($expectedSignature, $receivedSignature)) {
    logTransaction('payernex', 'Invalid HMAC signature. Payload: ' . $rawPayload, 'Error-Signature');
    http_response_code(401);
    exit();
}

// ─────────────────────────────────────────────
// Quick Pay (Express Wallet) deposit: credit the customer's balance
// ─────────────────────────────────────────────
if (isset($GETDATA['type']) && $GETDATA['type'] === 'express_wallet.paid') {
    payernex_express_credit($GETDATA, $apiKey, $rawPayload);
    exit();
}

// ─────────────────────────────────────────────
// SECURITY: Double-verify by checking order status via PayerNex API (MANDATORY)
// ─────────────────────────────────────────────
$orderRef = isset($GETDATA['order_ref']) ? $GETDATA['order_ref'] : '';

if (empty($apiKey)) {
    logTransaction('payernex', 'API key not configured — rejecting webhook', 'Error-NoApiKey');
    http_response_code(500);
    exit();
}
if (empty($orderRef)) {
    logTransaction('payernex', 'Missing order_ref in payload', 'Error-NoOrderRef');
    http_response_code(400);
    exit();
}

$ch = curl_init(PAYERNEX_API_URL . '/api/v1/order/' . urlencode($orderRef));
curl_setopt_array($ch, array(
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_HTTPHEADER     => array(
        'Authorization: Bearer ' . $apiKey,
        'Accept: application/json',
    ),
));
$verifyResponse = curl_exec($ch);
$verifyHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$verifyData = json_decode($verifyResponse, true);

$verifyStatus = isset($verifyData['data']['status']) ? $verifyData['data']['status'] : (isset($verifyData['status']) ? $verifyData['status'] : 'unknown');
if (!$verifyData || $verifyStatus !== 'paid') {
    logTransaction('payernex', 'API verification failed. HTTP: ' . $verifyHttpCode . ' Status: ' . $verifyStatus, 'Error-Verify');
    http_response_code(401);
    exit();
}

// Get the verified paid_amount from PayerNex API (trusted source, not webhook payload)
$verifiedPaidAmount = isset($verifyData['data']['paid_amount']) ? floatval($verifyData['data']['paid_amount']) : 0;
$verifiedPaidCurrency = isset($verifyData['data']['paid_currency']) ? $verifyData['data']['paid_currency'] : '';

// ─────────────────────────────────────────────
// Only process payment.completed events
// ─────────────────────────────────────────────
$event = isset($GETDATA['event']) ? $GETDATA['event'] : '';
if ($event !== 'payment.completed') {
    logTransaction('payernex', $rawPayload, 'Ignored-' . $event);
    http_response_code(200);
    exit();
}

// ─────────────────────────────────────────────
// Extract data from webhook payload
// ─────────────────────────────────────────────
$transactionId = isset($GETDATA['transaction_id']) ? $GETDATA['transaction_id'] : '';

// SECURITY: Extract invoice ID ONLY from description field (server-generated, not user-controlled)
// Never trust customer_id directly — it could be manipulated
$invoiceid = '';
$description = isset($GETDATA['description']) ? $GETDATA['description'] : '';
if (preg_match('/#(\d+)/', $description, $matches)) {
    $invoiceid = $matches[1];
}
// Fallback: customer_id with DHRU- prefix (set by our plugin, not user)
if (!is_numeric($invoiceid) || !$invoiceid) {
    $customerId = isset($GETDATA['customer_id']) ? $GETDATA['customer_id'] : '';
    if (strpos($customerId, 'DHRU-') === 0) {
        $extractedId = str_replace('DHRU-', '', $customerId);
        if (is_numeric($extractedId) && $extractedId > 0) {
            $invoiceid = $extractedId;
        }
    }
}

if (!is_numeric($invoiceid) || !$invoiceid) {
    logTransaction('payernex', $rawPayload, 'Error-NoInvoice');
    http_response_code(400);
    exit();
}

// ─────────────────────────────────────────────
// Check duplicate transaction ID
// ─────────────────────────────────────────────
if ($transactionId) {
    if (checkTransID($transactionId)) {
        exit();
    }
}

// ─────────────────────────────────────────────
// Validate invoice exists
// ─────────────────────────────────────────────
$result = select_query('tbl_invoices', '', array('id' => $invoiceid));
$invoice = mysqli_fetch_assoc($result);

if (!$invoice) {
    logTransaction('payernex', $rawPayload, 'Error-InvoiceNotFound', 'invoice', $invoiceid);
    http_response_code(404);
    exit();
}

// ─────────────────────────────────────────────
// SECURITY: Use API-verified paid_amount (not webhook payload)
// Cap maximum credit to invoice total + 20 PKR (max possible bank offset)
// ─────────────────────────────────────────────
$invoiceTotal = floatval($invoice['total']);
$maxAllowedAmount = $invoiceTotal + 20; // Max bank offset is 10 PKR, 20 PKR safety margin

// Use verified amount from API, not webhook (prevents amount inflation attack)
$receivedAmount = $invoiceTotal;
if ($verifiedPaidAmount >= $invoiceTotal && $verifiedPaidAmount <= $maxAllowedAmount) {
    $receivedAmount = $verifiedPaidAmount;
}

// ─────────────────────────────────────────────
// Process payment
// ─────────────────────────────────────────────
if ($invoice['status'] == 'Unpaid') {
    // If customer paid more than invoice total (bank offset), update invoice amount first
    if ($receivedAmount > $invoiceTotal) {
        update_query('tbl_invoiceitems', array('amount' => $receivedAmount), array('invoiceid' => $invoiceid));
        update_query('tbl_invoices', array('total' => $receivedAmount), array('id' => $invoiceid));
        logTransaction('payernex', 'Invoice updated from ' . $invoiceTotal . ' to ' . $receivedAmount . ' (bank offset)', 'Amount-Updated', 'invoice', $invoiceid);
    }

    addPayment($invoiceid, $transactionId, $receivedAmount, 0, 'payernex');
    logTransaction('payernex', $rawPayload, 'Successful', 'invoice', $invoiceid);
} else {
    logTransaction('payernex', $rawPayload, 'Already-' . $invoice['status'], 'invoice', $invoiceid);
}

http_response_code(200);
echo json_encode(array('status' => 'ok'));

/**
 * Credits one Express Wallet deposit as a paid "Add Funds" invoice.
 *
 * The HMAC signature was checked above. As a second check the deposit is read
 * back from the PayerNex API with this store's API key (only this merchant's
 * deposits are visible), and that answer — not the notification — decides the
 * customer and the amount. A deposit is credited once: its transaction id is
 * PNXEW-<deposit id>, guarded by checkTransID() under a MySQL lock.
 */
function payernex_express_credit($event, $apiKey, $rawPayload)
{
    $depositId = isset($event['data']['deposit_id']) ? (int) $event['data']['deposit_id'] : 0;
    if ($depositId <= 0 || empty($apiKey)) {
        logTransaction('payernex', $rawPayload, 'Express-Invalid');
        http_response_code(400);
        return;
    }
    $transid = 'PNXEW-' . $depositId;

    $ch = curl_init(PAYERNEX_API_URL . '/api/v1/express-wallet/deposits/' . $depositId);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => array('Authorization: Bearer ' . $apiKey, 'Accept: application/json'),
    ));
    $resp = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $api = json_decode((string) $resp, true);
    $dep = isset($api['data']) ? $api['data'] : null;
    if ($code !== 200 || !$dep || (isset($dep['status']) ? $dep['status'] : '') !== 'credited') {
        logTransaction('payernex', 'Express deposit ' . $depositId . ' not confirmed by API (HTTP ' . $code . ')', 'Express-Unconfirmed');
        http_response_code($code === 200 ? 409 : 502);   // non-2xx: PayerNex retries later
        return;
    }
    $clientId = isset($dep['merchant_client_id']) ? (string) $dep['merchant_client_id'] : '';
    if (!preg_match('/^dhru-(\d+)$/', $clientId, $mm)) {
        // Not a Quick Pay customer of this DHRU store (e.g. created by another integration).
        logTransaction('payernex', $rawPayload, 'Express-NotDhruCustomer');
        http_response_code(200);
        return;
    }
    $userId = (int) $mm[1];
    $amount = round((float) $dep['credited_amount'], 2);
    if ($amount <= 0) {
        logTransaction('payernex', $rawPayload, 'Express-ZeroAmount');
        http_response_code(200);
        return;
    }

    $lock = 'pnxew_' . $depositId;
    $lr = mysqli_fetch_row(dquery("SELECT GET_LOCK('" . $lock . "', 15)"));
    if (!$lr || (int) $lr[0] !== 1) {
        http_response_code(503);   // retried by PayerNex
        return;
    }
    try {
        if (checkTransID($transid)) {   // already credited
            http_response_code(200);
            return;
        }
        $u = mysqli_fetch_assoc(select_query('tblUsers', 'id', array('id' => $userId)));
        if (!$u) {
            logTransaction('payernex', $rawPayload, 'Express-UserNotFound');
            http_response_code(200);
            return;
        }
        // PayerNex credits in USD. DHRU invoices are in the customer's own
        // currency (e.g. PKR), so convert with DHRU's currency rates.
        $local = payernex_usd_to_customer_currency($userId, $amount);
        if ($local === null) {
            logTransaction('payernex', $rawPayload, 'Express-NoCurrencyRate');
            http_response_code(500);   // retried by PayerNex once the rate is fixed
            return;
        }
        $amount = $local;
        $invoiceid = payernex_create_addfunds_invoice($userId, $amount);
        if ($invoiceid <= 0) {
            logTransaction('payernex', $rawPayload, 'Express-InvoiceFailed');
            http_response_code(500);
            return;
        }
        addPayment($invoiceid, $transid, $amount, 0, 'payernex');
        logTransaction('payernex', $rawPayload, 'Successful', 'invoice', $invoiceid);
        http_response_code(200);
        echo json_encode(array('status' => 'ok', 'invoice' => $invoiceid));
    } finally {
        dquery("SELECT RELEASE_LOCK('" . $lock . "')");
    }
}

/**
 * USD → the customer's currency, using DHRU's tbl_currencies rates (each rate
 * is per 1 unit of the default currency). Returns null when a rate is missing.
 */
function payernex_usd_to_customer_currency($userId, $usd)
{
    $u = mysqli_fetch_assoc(dquery("SELECT default_currency FROM tblUsers WHERE id=" . (int) $userId));
    $rates = array(); $default = null;
    $r = dquery("SELECT id, code, rate, `default` FROM tbl_currencies");
    while ($row = mysqli_fetch_assoc($r)) {
        $rates[(int) $row['id']] = array('code' => strtoupper(trim($row['code'])), 'rate' => (float) $row['rate']);
        if ((int) $row['default'] === 1) $default = (int) $row['id'];
    }
    $usdId = null;
    foreach ($rates as $id => $c) if ($c['code'] === 'USD') { $usdId = $id; break; }
    $cust = ($u && (int) $u['default_currency'] && isset($rates[(int) $u['default_currency']])) ? (int) $u['default_currency'] : $default;
    if ($cust === null || $usdId === null || $rates[$usdId]['rate'] <= 0 || $rates[$cust]['rate'] <= 0) return null;
    return round((float) $usd * $rates[$cust]['rate'] / $rates[$usdId]['rate'], 2);
}

/** Unpaid "Add Funds" invoice for the deposit; addPayment() then settles it. */
function payernex_create_addfunds_invoice($userId, $amount)
{
    $uid = (int) $userId;
    $amt = number_format((float) $amount, 3, '.', '');
    $today = date('Y-m-d');
    $ts = (string) time();
    dquery("INSERT INTO tbl_invoices
        (userid, invoicenum, date, duedate, datepaid, subtotal, credit, tax, tax2, shippingtax,
         total, taxrate, taxrate2, status, paymentmethod, notes, adminid, promotion, posdiscount,
         discount, screenshot, screenshotenc, businessid, orderstatus, time)
        VALUES ('$uid','','$today','$today','0000-00-00 00:00:00','$amt',0,0,0,0,'$amt',0,0,'Unpaid',
         'payernex','PayerNex Quick Pay','','0','','','','','','new','$ts')");
    $row = mysqli_fetch_assoc(dquery("SELECT LAST_INSERT_ID() AS id"));
    $invid = (int) $row['id'];
    if ($invid > 0) {
        dquery("INSERT INTO tbl_invoiceitems
            (invoiceid, warehouseid, userid, type, relid, description, amount, taxed, shippingtax,
             duedate, paymentmethod, notes, qnt, promotion, promotion_code, pos, bundleid, warehouse,
             serviceid, service, used, serial)
            VALUES ('$invid',0,'$uid','AddFunds',0,'+Add Funds','$amt',0,0,'$today','payernex',
             '','','','',0,0,0,0,'',0,'')");
    }
    return $invid;
}
