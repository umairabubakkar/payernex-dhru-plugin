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
define('PAYERNEX_API_URL', 'https://payernex.com');

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
