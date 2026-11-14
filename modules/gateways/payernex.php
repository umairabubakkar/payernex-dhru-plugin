<?php
/*
 * ==========================================================
 * PAYERNEX - DHRU FUSION PAYMENT GATEWAY MODULE  (v2.0.0)
 *
 * Author: Umair Abubakkar
 * URL: https://github.com/umairabubakkar
 *
 * 1. Invoice payments: the customer pays an invoice on the PayerNex checkout
 *    (Binance C2C, bank, crypto, cards…); callback/payernex.php marks it paid.
 * 2. Quick Pay (Express Wallet): every customer gets a permanent USDT/USDC
 *    address on the Add Funds page. Whatever they send is added to their
 *    balance automatically, with no invoice first.
 * ==========================================================
 *
 *  2026 PayerNex. All rights reserved.
 */

defined("DEFINE_MY_ACCESS") or die('<h1 style="color: #C00; text-align: center;"><strong>Restricted Access</strong></h1>');

if (!defined('PAYERNEX_API_URL')) define('PAYERNEX_API_URL', getenv('PAYERNEX_API_URL') ?: 'https://payernex.com');
define('PAYERNEX_PLUGIN_VERSION', '2.0.0');

// ─────────────────────────────────────────────────────────────────────────────
// Self-healing setup. Runs whenever DHRU loads this module (payment pages,
// callbacks, admin gateway settings), so it also repairs itself after a DHRU
// update overwrites the files below. Every step is a silent no-op if a file is
// missing or not writable; payernex_setup_status() reports what happened.
// ─────────────────────────────────────────────────────────────────────────────

$GLOBALS['payernex_setup'] = array();

// DHRU's gateway-callback folder only runs PHP files named in its .htaccess.
function payernex_ensure_callback_allowed()
{
    $ht = __DIR__ . '/callback/.htaccess';
    if (!is_file($ht)) { $GLOBALS['payernex_setup']['htaccess'] = 'missing'; return; }
    $c = @file_get_contents($ht);
    if ($c === false) { $GLOBALS['payernex_setup']['htaccess'] = 'unreadable'; return; }
    if (preg_match('/\^payernex\\\\\.php/', $c)) { $GLOBALS['payernex_setup']['htaccess'] = 'ok'; return; }
    if (!is_writable($ht)) { $GLOBALS['payernex_setup']['htaccess'] = 'not_writable'; return; }
    $patched = preg_replace('/(<FilesMatch\s+")/i', '$1^payernex\\.php|', $c, 1);
    if ($patched && $patched !== $c && @file_put_contents($ht, $patched) !== false) {
        $GLOBALS['payernex_setup']['htaccess'] = 'patched';
    } else {
        $GLOBALS['payernex_setup']['htaccess'] = 'no_filesmatch';
    }
}

// Stores DHRU's own site address for payernex/quickpay.php (never the request
// Host header, which a caller can forge).
function payernex_store_site()
{
    global $config;
    $site = isset($config['site_address']) ? trim($config['site_address']) : '';
    if ($site === '' || !preg_match('#^https?://#i', $site)) return null;
    $site = rtrim($site, '/');
    $file = dirname(dirname(__DIR__)) . '/payernex/site.json';
    $want = json_encode(array('site' => $site));
    if (is_dir(dirname($file)) && @file_get_contents($file) !== $want) @file_put_contents($file, $want);
    return $site;
}

function payernex_settings()
{
    $lib = dirname(dirname(__DIR__)) . '/payernex/lib.php';
    if (!is_file($lib)) return array();
    require_once $lib;
    $db = pnx_db();
    return $db ? pnx_gateway_config($db) : array();
}

// Puts the Quick Pay widget at the top of every theme's Add Funds page (or
// removes it when Quick Pay is off). A ZIP install can't edit templates, so the
// block is inserted between markers, updated when the plugin version changes,
// and re-added if a DHRU update replaces the template.
function payernex_sync_widget($settings, $site)
{
    $root = dirname(dirname(__DIR__));
    $widgetFile = $root . '/payernex/quickpay_widget.tpl';
    $on = isset($settings['quickpay']) && in_array(strtolower($settings['quickpay']), array('on', 'yes', '1'), true)
        && !empty($settings['payernexAPIKey']) && $site;
    $hide = isset($settings['showmanual']) && in_array(strtolower($settings['showmanual']), array('on', 'yes', '1'), true) ? '0' : '1';
    $tag = 'PAYERNEX-QUICKPAY v' . PAYERNEX_PLUGIN_VERSION . ' hide=' . $hide;
    $result = array();
    $changed = false;

    foreach ((array) @glob($root . '/templates/*/client_addfunds.tpl') as $tplFile) {
        $theme = basename(dirname($tplFile));
        $tpl = @file_get_contents($tplFile);
        if ($tpl === false) continue;
        // The block is stored as "\n{* tag *}\n…\n{* /PAYERNEX-QUICKPAY *}\n", so removing it restores the template byte for byte.
        $hasBlock = preg_match('/\n?\{\* PAYERNEX-QUICKPAY[^*]*\*\}.*?\{\* \/PAYERNEX-QUICKPAY \*\}\n?/s', $tpl, $m);
        if ($hasBlock && $on && strpos(ltrim($m[0], "\n"), '{* ' . $tag . ' *}') === 0) { $result[$theme] = 'ok'; continue; }
        if (!is_writable($tplFile)) { $result[$theme] = 'not_writable'; continue; }
        $clean = $hasBlock ? str_replace($m[0], '', $tpl) : $tpl;
        if (!$on) {
            if ($hasBlock && @file_put_contents($tplFile, $clean) !== false) { $changed = true; }
            $result[$theme] = 'off';
            continue;
        }
        $widget = @file_get_contents($widgetFile);
        if ($widget === false || $widget === '') { $result[$theme] = 'widget_missing'; continue; }
        $base = (string) parse_url($site, PHP_URL_PATH);
        $widget = str_replace(array('__PNX_BASE__', '__PNX_HIDE__'), array(htmlspecialchars(rtrim($base, '/'), ENT_QUOTES), $hide), $widget);
        $block = '{* ' . $tag . ' *}' . "\n" . $widget . "\n" . '{* /PAYERNEX-QUICKPAY *}' . "\n";
        // Top of the Add Funds card in the stock DHRU template.
        $anchor = 'border col-lg-5 p-4 rounded m-auto">';
        $pos = strpos($clean, $anchor);
        if ($pos === false) { $result[$theme] = 'no_anchor'; continue; }
        $at = $pos + strlen($anchor);
        $new = substr($clean, 0, $at) . "\n" . $block . substr($clean, $at);   // $block ends with "\n"
        if (@file_put_contents($tplFile, $new) !== false) { $result[$theme] = 'injected'; $changed = true; }
        else { $result[$theme] = 'not_writable'; }
    }
    if ($changed) {
        foreach ((array) @glob($root . '/templates_c/*client_addfunds*') as $c) { @unlink($c); }
    }
    $GLOBALS['payernex_setup']['widget'] = $result;
}

function payernex_selfheal()
{
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        payernex_ensure_callback_allowed();
        $site = payernex_store_site();
        payernex_sync_widget(payernex_settings(), $site);
        $GLOBALS['payernex_setup']['site'] = $site;
    } catch (Throwable $e) {
        $GLOBALS['payernex_setup']['error'] = $e->getMessage();
    }
}
payernex_selfheal();

function payernex_setup_status_html()
{
    $s = $GLOBALS['payernex_setup'];
    $line = function ($ok, $text) {
        return '<div style="margin:3px 0;color:' . ($ok ? '#2e7d32' : '#c62828') . '">' . ($ok ? '✅ ' : '⚠️ ') . $text . '</div>';
    };
    $out = '';
    $h = isset($s['htaccess']) ? $s['htaccess'] : 'unknown';
    $out .= $line(in_array($h, array('ok', 'patched'), true), $h === 'patched' ? 'Callback allowed in callback/.htaccess (added just now).'
        : ($h === 'ok' ? 'Callback allowed in callback/.htaccess.' : 'Could not update modules/gateways/callback/.htaccess (' . htmlspecialchars($h) . '). Add <code>|^payernex\.php</code> to its FilesMatch line.'));
    $w = isset($s['widget']) ? $s['widget'] : array();
    if (!$w) $out .= $line(false, 'No Add Funds template found (templates/*/client_addfunds.tpl).');
    foreach ($w as $theme => $st) {
        $msg = array(
            'ok' => 'Quick Pay widget is on the Add Funds page', 'injected' => 'Quick Pay widget added to the Add Funds page',
            'off' => 'Quick Pay is off (widget removed)', 'not_writable' => 'Template is not writable, so the widget could not be added',
            'no_anchor' => 'Customised template: add the widget by hand (see README)', 'widget_missing' => 'payernex/quickpay_widget.tpl is missing (upload the payernex/ folder)'
        );
        $out .= $line(in_array($st, array('ok', 'injected', 'off'), true), htmlspecialchars(isset($msg[$st]) ? $msg[$st] : $st) . ' <span style="color:#888">(' . htmlspecialchars($theme) . ')</span>');
    }
    if (isset($s['error'])) $out .= $line(false, 'Setup error: ' . htmlspecialchars($s['error']));
    return $out;
}

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
            'Description' => 'Your PayerNex live API key: payernex.com → Dashboard → Developer → API Keys.',
        ),
        'payernexWebhookSecret' => array(
            'Name' => 'Webhook Secret',
            'Type' => 'text',
            'Size' => '65',
            'Default' => '',
            'Description' => 'From the same PayerNex page. Verifies every payment notification.',
        ),
        'quickpay' => array(
            'Name' => 'Quick Pay (Express Wallet)',
            'Type' => 'yesno',
            'Description' => 'Show the Quick Pay widget on the Add Funds page: each customer gets a permanent USDT/USDC address and is credited automatically. Set up Express Wallet in PayerNex first (Payments → Express Wallet).',
        ),
        'showmanual' => array(
            'Name' => 'Keep the standard Add Funds form',
            'Type' => 'yesno',
            'Description' => 'Leave off to show only Quick Pay on the Add Funds page. Turn on to also show DHRU\'s normal form below it.',
        ),
        'notes' => array(
            'Type' => 'System',
            'Value' => '
                <div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 16px; margin: 8px 0;">
                    <div style="margin-bottom: 12px;">
                        <strong style="color: #e67e22;">⚡ PayerNex</strong> v' . PAYERNEX_PLUGIN_VERSION . ' — invoice payments and Quick Pay (Express Wallet)
                    </div>
                    <div style="margin-bottom: 12px;">
                        <span style="color: #666;">Callback URL (filled in automatically for both):</span><br />
                        <code style="background: #e8f5e9; padding: 6px 12px; border-radius: 4px; display: inline-block; margin-top: 4px; color: #2e7d32; font-size: 13px; border: 1px solid #c8e6c9;">' . htmlspecialchars($config['site_address']) . 'modules/gateways/callback/payernex.php</code>
                    </div>
                    <div style="font-size: 12.5px; margin-bottom: 10px;">' . payernex_setup_status_html() . '</div>
                    <div style="color: #888; font-size: 12px;">
                        Save, then open this page again to see the setup status. Quick Pay credits arrive as paid "Add Funds" invoices.
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

        // Local currency amount (PKR) for bank transfer
        $localAmount = $params['basecurrencyamount'] > 0 ? $params['basecurrencyamount'] : null;
        $localCurrency = $params['basecurrency'] ?: null;

        // Create order via PayerNex API
        $orderData = array(
            'amount'        => (float) $amount,
            'currency'      => $currency,
            'customer_id'   => 'DHRU-' . $invoiceid,
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

        // Send customer's current credit balance for checkout display
        $creditInfo = $params['clientdetails']['credit'] ?? null;
        if (is_array($creditInfo) && isset($creditInfo['credit'])) {
            $orderData['customer_balance'] = (float) $creditInfo['credit'];
        } elseif (is_numeric($creditInfo)) {
            $orderData['customer_balance'] = (float) $creditInfo;
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

        // Dhru pattern: on invoice page show button, otherwise open modal
        if (strpos($_SERVER['REQUEST_URI'], 'viewinvoice') !== false) {
            return '<div style="text-align: center;"><a href="' . htmlspecialchars($checkoutUrl) . '" style="display: inline-block; padding: 12px 40px; border-radius: 50px; background: #F59E0B; color: #000; font-size: 14px; font-weight: 700; text-decoration: none; box-shadow: 0 0 20px rgba(245,158,11,0.2);">Pay Now</a></div>';
        } else {
            // Open checkout in modal popup
            $escapedUrl = htmlspecialchars($checkoutUrl, ENT_QUOTES);
            return '
            <div id="payernex-overlay" style="position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:99999;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(4px);">
                <div style="position:relative;width:95%;max-width:480px;height:85vh;max-height:750px;border-radius:16px;overflow:hidden;box-shadow:0 25px 60px rgba(0,0,0,0.5);border:1px solid rgba(245,158,11,0.2);">
                    <button onclick="document.getElementById(\'payernex-overlay\').remove();" style="position:absolute;top:12px;right:12px;z-index:10;width:32px;height:32px;border-radius:50%;border:none;background:rgba(0,0,0,0.6);color:#fff;font-size:18px;cursor:pointer;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(10px);">&times;</button>
                    <iframe src="' . $escapedUrl . '" style="width:100%;height:100%;border:none;border-radius:16px;" allow="clipboard-write"></iframe>
                </div>
            </div>
            <script>
            document.getElementById("payernex-overlay").addEventListener("click", function(e) {
                if (e.target === this) this.remove();
            });
            // Poll for payment completion
            var _pnxPoll = setInterval(function() {
                try {
                    var iframe = document.querySelector("#payernex-overlay iframe");
                    if (!iframe) { clearInterval(_pnxPoll); return; }
                    // Check if iframe redirected to success URL
                    try {
                        var loc = iframe.contentWindow.location.href;
                        if (loc.indexOf("paid=1") > -1) {
                            clearInterval(_pnxPoll);
                            document.getElementById("payernex-overlay").remove();
                            location.reload();
                        }
                    } catch(e) {}
                } catch(e) { clearInterval(_pnxPoll); }
            }, 2000);
            </script>';
        }
    } catch (Exception $ex) {
        echo '<div align="center" style="padding: 20px; color: #C00;">PayerNex Gateway Error:<br />' . htmlspecialchars($ex->getMessage()) . '</div>';
        die;
    }
}
