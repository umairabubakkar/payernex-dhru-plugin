<?php
/*
 * ==========================================================
 * PAYERNEX DHRU FUSION MODULE - CALLBACK / WEBHOOK FILE
 *
 * Author: Umair Abubakkar
 *
 * PayerNex - Non-Custodial Binance Pay C2C Payment Gateway
 * ==========================================================
 *
 *  2026 PayerNex. All rights reserved.
 *
 * This file receives webhook notifications from PayerNex
 * when a payment is confirmed. It verifies the signature,
 * validates the payment, and credits the customer's account.
 *
 */

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

define("DEFINE_MY_ACCESS", true);
define("DEFINE_DHRU_FILE", true);
define("ROOTDIR", __DIR__);

include ROOTDIR . "/comm.php";
require ROOTDIR . "/includes/fun.inc.php";
include ROOTDIR . "/includes/gateway.fun.php";
include ROOTDIR . "/includes/invoice.fun.php";

$logFile = 'payernex.log';

/**
 * Log message to file
 */
function payernex_log($message, $file = 'payernex.log')
{
    $timestamp = date('[Y-m-d H:i:s]');
    file_put_contents($file, "$timestamp $message" . PHP_EOL, FILE_APPEND);
}

/**
 * Get invoice details from database
 */
function getInvoiceDetails($invoiceId)
{
    return mysqli_fetch_assoc(dquery("SELECT * FROM tbl_invoices WHERE id = '" . intval($invoiceId) . "' LIMIT 1"));
}

// ─────────────────────────────────────────────────
// Load gateway module configuration
// ─────────────────────────────────────────────────
$gatewayParams = loadGatewayModule('payernex');
$gatewayModuleName = (!empty($gatewayParams['paymentmethod'])) ? $gatewayParams['paymentmethod'] : "PayerNex";

// ─────────────────────────────────────────────────
// Read incoming webhook payload
// ─────────────────────────────────────────────────
$rawPayload = file_get_contents('php://input');
$payload = json_decode($rawPayload, true);

if (!$payload) {
    payernex_log('Invalid payload received: ' . $rawPayload, $logFile);
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

payernex_log('Webhook received: ' . $rawPayload, $logFile);

// ─────────────────────────────────────────────────
// Verify HMAC-SHA256 signature
// ─────────────────────────────────────────────────
$receivedSignature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
$webhookSecret = trim($gatewayParams['webhookSecret']);
$expectedSignature = hash_hmac('sha256', $rawPayload, $webhookSecret);

if (!hash_equals($expectedSignature, $receivedSignature)) {
    payernex_log('Invalid signature. Expected: ' . $expectedSignature . ' Received: ' . $receivedSignature, $logFile);
    http_response_code(401);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

// ─────────────────────────────────────────────────
// Check timestamp to prevent replay attacks (5 min window)
// ─────────────────────────────────────────────────
$timestamp = $payload['timestamp'] ?? 0;
if (abs(time() - $timestamp) > 300) {
    payernex_log('Webhook timestamp too old: ' . $timestamp, $logFile);
    http_response_code(400);
    echo json_encode(['error' => 'Request expired']);
    exit;
}

// ─────────────────────────────────────────────────
// Process payment.completed event
// ─────────────────────────────────────────────────
$event = $payload['event'] ?? '';
$orderRef = $payload['order_ref'] ?? '';
$paidAmount = $payload['paid_amount'] ?? '0';
$paidCurrency = $payload['paid_currency'] ?? '';
$transactionId = $payload['transaction_id'] ?? '';
$customerId = $payload['customer_id'] ?? '';

if ($event !== 'payment.completed') {
    payernex_log('Ignoring event: ' . $event, $logFile);
    http_response_code(200);
    echo json_encode(['status' => 'ignored']);
    exit;
}

// Extract Dhru invoice ID from customer_id (format: DHRU-{invoiceId})
$invoiceId = null;
if (strpos($customerId, 'DHRU-') === 0) {
    $invoiceId = intval(str_replace('DHRU-', '', $customerId));
}

if (!$invoiceId) {
    payernex_log('Could not extract invoice ID from customer_id: ' . $customerId, $logFile);
    http_response_code(400);
    echo json_encode(['error' => 'Invalid customer_id']);
    exit;
}

// ─────────────────────────────────────────────────
// Validate invoice
// ─────────────────────────────────────────────────
$invoice = getInvoiceDetails($invoiceId);

if (!$invoice) {
    payernex_log('Invoice not found: ' . $invoiceId, $logFile);
    http_response_code(404);
    echo json_encode(['error' => 'Invoice not found']);
    exit;
}

// Check amount matches (with 1% tolerance)
$invoiceTotal = floatval($invoice['total']);
$receivedAmount = floatval($paidAmount);
$tolerance = $invoiceTotal * 0.01;

if ($receivedAmount < ($invoiceTotal - $tolerance)) {
    payernex_log(
        '[PayerNex ' . $orderRef . '] Amount mismatch. Invoice total: ' . $invoiceTotal
        . ', Received: ' . $receivedAmount . ', Invoice ID: ' . $invoiceId,
        $logFile
    );
    // Still return 200 so PayerNex doesn't retry — but don't credit
    http_response_code(200);
    echo json_encode(['status' => 'amount_mismatch']);
    exit;
}

// ─────────────────────────────────────────────────
// Credit payment if invoice is unpaid
// ─────────────────────────────────────────────────
$transactionStatus = 'Unpaid';

if ($invoice['status'] === 'Unpaid') {
    $transactionStatus = 'Paid';

    addPayment(
        $invoiceId,
        $transactionId ?: $orderRef,  // transaction reference
        $receivedAmount,              // amount
        null,                         // payment fee
        $gatewayModuleName            // gateway name
    );

    payernex_log(
        '[PayerNex ' . $orderRef . '] Payment credited. Invoice: ' . $invoiceId
        . ', Amount: ' . $receivedAmount . ' ' . $paidCurrency
        . ', TX: ' . $transactionId,
        $logFile
    );
} else {
    payernex_log(
        '[PayerNex ' . $orderRef . '] Invoice already ' . $invoice['status'] . '. Skipping. Invoice: ' . $invoiceId,
        $logFile
    );
}

logTransaction($gatewayParams['name'], $payload, $transactionStatus);

// Return 200 to acknowledge receipt
http_response_code(200);
echo json_encode(['status' => 'ok']);
