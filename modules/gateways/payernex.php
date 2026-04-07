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
            return '
            <div style="background: linear-gradient(135deg, #0a0a0a 0%, #1a1a2e 100%); border-radius: 16px; padding: 28px 24px; margin: 12px 0; border: 1px solid rgba(245,158,11,0.15); position: relative; overflow: hidden; text-align: center;">
                <div style="position: absolute; top: 0; left: 15%; right: 15%; height: 1px; background: linear-gradient(90deg, transparent, rgba(245,158,11,0.4), transparent);"></div>
                <div style="display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 14px;">
                    <div style="width: 36px; height: 36px; border-radius: 10px; background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.2); display: flex; align-items: center; justify-content: center;">
                        <svg width="18" height="18" viewBox="0 0 126 126" fill="none"><path d="M63 0C28.2 0 0 28.2 0 63s28.2 63 63 63 63-28.2 63-63S97.8 0 63 0z" fill="#F0B90B"/><path d="M45.4 63L35 52.6l10.4-10.4 10.4 10.4L45.4 63zm17.6-17.6L52.6 35 63 24.6 73.4 35 63 45.4zM80.6 63L70.2 52.6l10.4-10.4 10.4 10.4L80.6 63zM63 80.6L52.6 70.2l10.4-10.4 10.4 10.4L63 80.6z" fill="#fff"/></svg>
                    </div>
                    <span style="font-size: 16px; font-weight: 700; color: #fff; letter-spacing: -0.3px;">PayerNex</span>
                </div>
                <p style="font-size: 12px; color: #9CA3AF; margin: 0 0 18px; line-height: 1.5;">Pay securely via <strong style="color: #F59E0B;">Binance Pay</strong> or <strong style="color: #4ADE80;">Bank Transfer</strong></p>
                <a href="' . htmlspecialchars($checkoutUrl) . '" style="display: inline-block; padding: 12px 40px; border-radius: 50px; background: #F59E0B; color: #000; font-size: 14px; font-weight: 700; text-decoration: none; transition: all 0.2s; box-shadow: 0 0 20px rgba(245,158,11,0.2);"
                   onmouseover="this.style.background=\'#D97706\';this.style.transform=\'translateY(-1px)\';this.style.boxShadow=\'0 0 30px rgba(245,158,11,0.35)\'"
                   onmouseout="this.style.background=\'#F59E0B\';this.style.transform=\'translateY(0)\';this.style.boxShadow=\'0 0 20px rgba(245,158,11,0.2)\'">
                    Pay Now
                </a>
                <div style="margin-top: 14px; display: flex; align-items: center; justify-content: center; gap: 6px;">
                    <svg width="10" height="10" fill="none" stroke="#6B7280" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                    <span style="font-size: 10px; color: #6B7280;">Secured by PayerNex &middot; Zero Fees</span>
                </div>
            </div>';
        } else {
            return '<script>document.location = "' . addslashes($checkoutUrl) . '";</script>';
        }
    } catch (Exception $ex) {
        echo '<div align="center" style="padding: 20px; color: #C00;">PayerNex Gateway Error:<br />' . htmlspecialchars($ex->getMessage()) . '</div>';
        die;
    }
}
