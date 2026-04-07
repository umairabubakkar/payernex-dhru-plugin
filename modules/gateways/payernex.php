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
            'Name' => 'Merchant Token',
            'Type' => 'text',
            'Size' => '65',
            'Default' => '',
            'Description' => 'Enter your PayerNex Merchant Token. Get it from payernex.com > Dashboard > Developer > API Keys.',
        ),
        'payernexWebhookSecret' => array(
            'Name' => 'Webhook Secret',
            'Type' => 'text',
            'Size' => '65',
            'Default' => '',
            'Description' => 'Enter your PayerNex Webhook Secret. Get it from payernex.com > Dashboard > Developer > API Keys.',
        ),
        'notes' => array(
            'Type' => 'System',
            'Value' => '
                <div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 16px; margin: 8px 0;">
                    <div style="margin-bottom: 12px;">
                        <strong style="color: #e67e22;">⚡ PayerNex</strong> — Non-Custodial Binance Pay C2C Payment Gateway
                    </div>
                    <div style="margin-bottom: 12px;">
                        <span style="color: #666;">Webhook / Callback URL:</span><br />
                        <code style="background: #e8f5e9; padding: 6px 12px; border-radius: 4px; display: inline-block; margin-top: 4px; color: #2e7d32; font-size: 13px; border: 1px solid #c8e6c9;">' . $config['site_address'] . 'modules/gateways/callback/payernex.php</code>
                    </div>
                    <div style="color: #888; font-size: 12px;">
                        ✅ Enter your Merchant Token and Webhook Secret from PayerNex Dashboard > Developer > API Keys.<br />
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

        // Dhru Fusion: 'amount' is in gateway currency (USD), 'basecurrencyamount' is in customer currency (PKR)
        // We need USD amount and treat it as USDT (1:1)
        $amount = $params['amount'];
        $amount = formatCurrency2($amount);

        // Determine currency — base currency is USD, treat as USDT (1:1)
        $currency = 'USDT';

        $returnurl = html_entity_decode($params['returnurl']);
        $apiURL = PAYERNEX_API_URL;
        $apiKey = trim($params['payernexAPIKey']);
        $callbackUrl = $params['systemurl'] . '/modules/gateways/callback/payernex.php';

        if (empty($apiKey)) {
            throw new Exception('PayerNex API Key is required. Get it from payernex.com > Dashboard > Developer.');
        }

        // Build customer name from client details
        $clientName = trim(
            ($params['clientdetails']['firstname'] ?? '') . ' ' . ($params['clientdetails']['lastname'] ?? '')
        );

        // Local currency amount (PKR) for bank transfer
        $localAmount = $params['basecurrencyamount'] > 0 ? $params['basecurrencyamount'] : null;
        $localCurrency = $params['basecurrency'] ?: null;

        // Create order via PayerNex API
        $orderData = array(
            'amount'        => (float) $amount,
            'currency'      => $currency,
            'customer_id'   => $clientName ?: ('DHRU-' . $invoiceid),
            'customer_email'=> $params['clientdetails']['email'] ?? '',
            'description'   => $params['description'],
            'ipn_url'       => $callbackUrl,
            'success_url'   => $returnurl,
            'cancel_url'    => $returnurl,
        );

        // Send local amount for bank transfer PKR support
        if ($localAmount && $localCurrency) {
            $orderData['local_amount'] = (float) $localAmount;
            $orderData['local_currency'] = $localCurrency;
        }

        $orderData = json_encode($orderData);

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

        $checkoutUrl = isset($result['data']['checkout_url']) ? $result['data']['checkout_url'] : (isset($result['checkout_url']) ? $result['checkout_url'] : null);
        if (!$checkoutUrl) {
            throw new Exception('Invalid response — no checkout URL returned.');
        }

        // Dhru pattern: on invoice page show button, otherwise auto-redirect
        if (strpos($_SERVER['REQUEST_URI'], 'viewinvoice') !== false) {
            return '<div style="text-align: center;"><a href="' . htmlspecialchars($checkoutUrl) . '" style="display: inline-block; padding: 12px 40px; border-radius: 50px; background: #F59E0B; color: #000; font-size: 14px; font-weight: 700; text-decoration: none; box-shadow: 0 0 20px rgba(245,158,11,0.2);">Pay Now</a></div>';
        } else {
            return '<script>document.location = "' . addslashes($checkoutUrl) . '";</script>';
        }
    } catch (Exception $ex) {
        echo '<div align="center" style="padding: 20px; color: #C00;">PayerNex Gateway Error:<br />' . htmlspecialchars($ex->getMessage()) . '</div>';
        die;
    }
}
