<?php
/*
 * ==========================================================
 * PAYERNEX DHRU FUSION MODULE
 *
 * Author: Umair Abubakkar
 *
 * PayerNex - Non-Custodial Binance Pay C2C Payment Gateway
 * ==========================================================
 *
 *  2026 PayerNex. All rights reserved.
 *
 */

defined("DEFINE_MY_ACCESS") or die('<h1 style="color: #C00; text-align: center;"><strong>Restricted Access</strong></h1>');

function payernex_config()
{
    global $config;
    return array(
        'name' => array(
            'Type' => 'System',
            'Value' => 'PayerNex',
        ),
        'apiURL' => array(
            'Name' => 'PayerNex API URL',
            'Type' => 'text',
            'Size' => '50',
            'Default' => '',
            'Description' => 'Enter your PayerNex gateway URL, e.g. https://pay.yourdomain.com',
        ),
        'apiKey' => array(
            'Name' => 'API Key',
            'Type' => 'text',
            'Size' => '65',
            'Default' => '',
            'Description' => 'Enter your PayerNex API Key. Get it from PayerNex Dashboard > Developer > API Keys.',
        ),
        'webhookSecret' => array(
            'Name' => 'Webhook Secret',
            'Type' => 'text',
            'Size' => '65',
            'Default' => '',
            'Description' => 'Enter your PayerNex Webhook Secret. Get it from PayerNex Dashboard > Developer.',
        ),
        'webhookURL' => array(
            'Name' => 'Webhook / Callback URL',
            'Type' => 'text',
            'Size' => '50',
            'Value' => $config['site_address'] . 'payernexcallback.php',
            'Default' => $config['site_address'] . 'payernexcallback.php',
            'Description' => 'Copy this URL and it will be used automatically. No changes needed.',
        ),
    );
}

function payernex_link($params)
{
    try {
        $amount = isset($params['basecurrencyamount']) ? $params['basecurrencyamount'] : $params['amount'];
        $currency = trim($params['currency']);
        $invoiceId = $params['invoiceid'];
        $returnUrl = $params['returnurl'];
        $apiURL = rtrim(trim($params['apiURL']), '/');
        $apiKey = trim($params['apiKey']);

        // Map currency to supported crypto — default USDT
        $cryptoCurrency = 'USDT';
        if (in_array(strtoupper($currency), ['USDT', 'BNB', 'BTC', 'ETH'])) {
            $cryptoCurrency = strtoupper($currency);
        }

        // Create order via PayerNex API
        $response = payernex_api_call($apiURL, $apiKey, 'POST', '/api/v1/create-order', [
            'amount' => (float) $amount,
            'currency' => $cryptoCurrency,
            'customer_id' => 'DHRU-' . $invoiceId,
            'description' => 'Invoice #' . $invoiceId,
            'ipn_url' => $params['webhookURL'] ?? ($GLOBALS['config']['site_address'] . 'payernexcallback.php'),
            'success_url' => $returnUrl,
            'cancel_url' => $returnUrl,
        ]);

        if (!$response || !isset($response['checkout_url'])) {
            $error = $response['error'] ?? 'Unknown error from PayerNex API';
            throw new Exception($error);
        }

        $checkoutUrl = $response['checkout_url'];

        // If viewing invoice page, show Pay Now button. Otherwise, auto-redirect.
        if (strpos($_SERVER['REQUEST_URI'], 'viewinvoice') !== false) {
            return '<a href="' . htmlspecialchars($checkoutUrl) . '" class="btn btn-success btn-sm" id="payernex-link">'
                . (!isset($_LANG) || empty($_LANG['PAY_NOW']) ? 'Pay with Binance' : $_LANG['PAY_NOW'])
                . '</a>';
        } else {
            return '<script>document.location = "' . $checkoutUrl . '";</script>';
        }
    } catch (Exception $ex) {
        echo '<div align="center" style="color: #C00;">PayerNex Gateway Error: <br>' . htmlspecialchars($ex->getMessage()) . '</div>';
        die;
    }
}

/**
 * Make API call to PayerNex
 */
function payernex_api_call($baseUrl, $apiKey, $method, $endpoint, $data = [])
{
    $url = $baseUrl . $endpoint;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['error' => 'cURL error: ' . $error];
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        $decoded = json_decode($response, true);
        return ['error' => $decoded['error'] ?? 'HTTP ' . $httpCode . ': ' . $response];
    }

    return json_decode($response, true);
}
