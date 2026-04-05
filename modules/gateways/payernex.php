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

// ─────────────────────────────────────────────
// PayerNex Gateway Configuration (hardcoded)
// ─────────────────────────────────────────────
define('PAYERNEX_API_URL', 'https://payernex.com');

function payernex_config()
{
    global $config;
    $configarray = array(
        'name' => array(
            'Type' => 'System',
            'Value' => 'PayerNex',
        ),
        'payernexAPIKey' => array(
            'Name' => 'API Key',
            'Type' => 'text',
            'Size' => '65',
            'Default' => '',
            'Description' => 'Enter your PayerNex API Key. Sign up at payernex.com and get it from Dashboard > Developer > API Keys.',
        ),
        'notes' => array(
            'Type' => 'System',
            'Value' => '
                <div style="background: #1a1a1a; border: 1px solid #333; border-radius: 8px; padding: 16px; margin: 8px 0;">
                    <div style="margin-bottom: 12px;">
                        <strong style="color: #F59E0B;">⚡ PayerNex</strong> — Non-Custodial Binance Pay C2C Payment Gateway
                    </div>
                    <div style="margin-bottom: 12px;">
                        <span style="color: #888;">Webhook / Callback URL:</span><br />
                        <code style="background: #111; padding: 6px 12px; border-radius: 4px; display: inline-block; margin-top: 4px; color: #4ADE80; font-size: 13px;">' . $config['site_address'] . 'modules/gateways/callback/payernex.php</code>
                    </div>
                    <div style="color: #666; font-size: 12px;">
                        ✅ No additional setup needed — just enter your API Key above and save.<br />
                        ✅ Webhook URL is auto-configured. Payments will be credited automatically.
                    </div>
                </div>
            ',
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
        $apiURL = PAYERNEX_API_URL;
        $apiKey = trim($params['payernexAPIKey']);
        $callbackUrl = $params['systemurl'] . '/modules/gateways/callback/payernex.php';

        if (empty($apiKey)) {
            throw new Exception('PayerNex API Key is required. Get it from payernex.com > Dashboard > Developer.');
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
