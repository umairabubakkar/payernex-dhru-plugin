<?php
/*
 * ==========================================================
 * PAYERNEX - DHRU FUSION PAYMENT GATEWAY MODULE
 *
 * Author: Umair Abubakkar
 * URL: https://github.com/umairabubakkar
 *
 * PayerNex — Non-Custodial Binance Pay C2C Payment Gateway
 * ==========================================================
 *
 *  2026 PayerNex. All rights reserved.
 *
 */

defined("DEFINE_MY_ACCESS") or die('<h1 style="color: #C00; text-align: center;"><strong>Restricted Access</strong></h1>');

function payernex_config()
{
    global $config;
    $configarray = array(
        'name' => array(
            'Type' => 'System',
            'Value' => 'PayerNex',
        ),
        'payernexURL' => array(
            'Name' => 'PayerNex URL',
            'Type' => 'text',
            'Size' => '50',
            'Default' => '',
            'Description' => 'Enter your PayerNex gateway URL. Example: https://pay.yourdomain.com',
        ),
        'payernexAPIKey' => array(
            'Name' => 'API Key',
            'Type' => 'text',
            'Size' => '65',
            'Default' => '',
            'Description' => 'Enter your PayerNex API Key. Get it from PayerNex Dashboard > Developer > API Keys.',
        ),
        'payernexWebhookSecret' => array(
            'Name' => 'Webhook Secret',
            'Type' => 'text',
            'Size' => '65',
            'Default' => '',
            'Description' => 'Enter your PayerNex Webhook Secret. Get it from PayerNex Dashboard > Developer section.',
        ),
        'notes' => array(
            'Type' => 'System',
            'Value' => 'Webhook / Callback URL:<br /><strong>' . $config['site_address'] . 'modules/gateways/callback/payernex.php</strong><br /><br />Copy this URL — PayerNex will send payment notifications to this address.',
        ),
    );
    return $configarray;
}

function payernex_link($params)
{
    global $config;
    global $lng_languag;

    try {
        $invoiceid = $params['invoiceid'];
        $amount = isset($params['basecurrencyamount']) && $params['basecurrencyamount'] > 0
            ? $params['basecurrencyamount']
            : $params['amount'];
        $amount = formatCurrency2($amount);
        $returnurl = $params['returnurl'];
        $apiURL = rtrim(trim($params['payernexURL']), '/');
        $apiKey = trim($params['payernexAPIKey']);
        $callbackUrl = $params['systemurl'] . '/modules/gateways/callback/payernex.php';

        if (empty($apiURL) || empty($apiKey)) {
            throw new Exception('PayerNex URL and API Key must be configured in Admin > Payment Gateways.');
        }

        // Create order via PayerNex API
        $orderData = json_encode(array(
            'amount'      => (float) $amount,
            'currency'    => 'USDT',
            'customer_id' => 'DHRU-' . $invoiceid,
            'description' => $params['description'],
            'ipn_url'     => $callbackUrl,
            'success_url' => $returnurl . '&paymentsuccess=true',
            'cancel_url'  => $returnurl . '&paymentfailed=true',
        ));

        $ch = curl_init($apiURL . '/api/v1/create-order');
        curl_setopt_array($ch, array(
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $orderData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => array(
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ),
        ));
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new Exception('Connection failed: ' . $curlError);
        }

        $result = json_decode($response, true);

        if ($httpCode < 200 || $httpCode >= 300 || !$result) {
            $errorMsg = isset($result['error']) ? $result['error'] : 'HTTP ' . $httpCode;
            throw new Exception('PayerNex API error: ' . $errorMsg);
        }

        if (!isset($result['checkout_url'])) {
            throw new Exception('Invalid response — no checkout URL returned.');
        }

        $checkoutUrl = $result['checkout_url'];

        // Dhru pattern: on invoice page show button, otherwise auto-redirect
        if (strpos($_SERVER['REQUEST_URI'], 'viewinvoice') !== false) {
            $btnText = isset($lng_languag['invoicespaynow']) ? $lng_languag['invoicespaynow'] : 'Pay with Binance';
            return '<a href="' . htmlspecialchars($checkoutUrl) . '" class="btn btn-success btn-sm">' . $btnText . '</a>';
        } else {
            return '<script>document.location = "' . addslashes($checkoutUrl) . '";</script>';
        }
    } catch (Exception $ex) {
        echo '<div align="center" style="padding: 20px; color: #C00;">PayerNex Gateway Error:<br />' . htmlspecialchars($ex->getMessage()) . '</div>';
        die;
    }
}
