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

// Load gateway parameters
$GATEWAY = loadGatewayModule('payernex');

// Read incoming webhook payload
$rawPayload = file_get_contents('php://input');
$GETDATA = json_decode($rawPayload, true);

if (!$GETDATA) {
    logTransaction('payernex', 'Invalid payload: ' . $rawPayload, 'Error');
    http_response_code(400);
    exit();
}

// ─────────────────────────────────────────────
// Verify HMAC-SHA256 signature
// ─────────────────────────────────────────────
$receivedSignature = isset($_SERVER['HTTP_X_SIGNATURE']) ? $_SERVER['HTTP_X_SIGNATURE'] : '';
$webhookSecret = trim($GATEWAY['payernexWebhookSecret']);
$expectedSignature = hash_hmac('sha256', $rawPayload, $webhookSecret);

if (!hash_equals($expectedSignature, $receivedSignature)) {
    logTransaction('payernex', 'Invalid signature. Received: ' . $receivedSignature, 'Error-Signature');
    http_response_code(401);
    exit();
}

// ─────────────────────────────────────────────
// Check timestamp (prevent replay attacks — 5 min window)
// ─────────────────────────────────────────────
$timestamp = isset($GETDATA['timestamp']) ? $GETDATA['timestamp'] : 0;
if (abs(time() - $timestamp) > 300) {
    logTransaction('payernex', 'Expired timestamp: ' . $timestamp, 'Error-Expired');
    http_response_code(400);
    exit();
}

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
$orderRef = isset($GETDATA['order_ref']) ? $GETDATA['order_ref'] : '';
$paidAmount = isset($GETDATA['paid_amount']) ? $GETDATA['paid_amount'] : '0';
$transactionId = isset($GETDATA['transaction_id']) ? $GETDATA['transaction_id'] : '';
$customerId = isset($GETDATA['customer_id']) ? $GETDATA['customer_id'] : '';

// Extract Dhru invoice ID from customer_id (format: DHRU-{invoiceId})
$invoiceid = '';
if (strpos($customerId, 'DHRU-') === 0) {
    $invoiceid = str_replace('DHRU-', '', $customerId);
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
// Validate amount (with 1% tolerance)
// ─────────────────────────────────────────────
$invoiceTotal = floatval($invoice['total']);
$receivedAmount = floatval($paidAmount);
$tolerance = $invoiceTotal * 0.01;

if ($receivedAmount < ($invoiceTotal - $tolerance)) {
    logTransaction('payernex', $rawPayload, 'Error-AmountMismatch', 'invoice', $invoiceid);
    http_response_code(200); // 200 so PayerNex doesn't retry
    exit();
}

// ─────────────────────────────────────────────
// Process payment
// ─────────────────────────────────────────────
if ($invoice['status'] == 'Unpaid') {
    // Use invoice total if received amount is close enough
    if ($receivedAmount >= ($invoiceTotal - $tolerance) && $receivedAmount <= ($invoiceTotal + $tolerance)) {
        $receivedAmount = $invoiceTotal;
    }

    addPayment($invoiceid, $transactionId, $receivedAmount, 0, 'payernex');
    logTransaction('payernex', $rawPayload, 'Successful', 'invoice', $invoiceid);
} else {
    logTransaction('payernex', $rawPayload, 'Already-' . $invoice['status'], 'invoice', $invoiceid);
}

http_response_code(200);
echo json_encode(array('status' => 'ok'));
