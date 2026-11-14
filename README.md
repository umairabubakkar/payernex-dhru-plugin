# PayerNex — Dhru Fusion Payment Gateway (v2)

Two ways for your Dhru Fusion customers to pay, from one plugin:

- **Invoice payments.** The customer pays an invoice on the PayerNex checkout (Binance C2C, bank transfer, crypto, cards). Dhru marks it paid automatically.
- **Quick Pay (Express Wallet), new in v2.** Every customer gets their own permanent USDT / USDC address, shown at the top of the **Add Funds** page. They can send any amount, any time, with no invoice first, and it is added to their balance automatically. The funds settle in your own wallet.

The plugin sets itself up: it allows its callback in Dhru's `.htaccess` and places the Quick Pay widget on the Add Funds page. It repeats both after a Dhru update overwrites those files.

---

## Install (about 5 minutes)

### 1. Upload the files

Upload the ZIP to your Dhru folder (the one that contains `modules/`, `templates/` and `configs/`, usually `public_html`) and **Extract** it. The files land here:

```
modules/gateways/payernex.php              gateway module (settings, invoice payments, self-setup)
modules/gateways/callback/payernex.php     payment notifications (invoices + Quick Pay)
payernex/quickpay.php                      Quick Pay: opens the customer's deposit page
payernex/status.php                        Quick Pay: tells the page when Dhru has credited
payernex/lib.php                           shared helpers
payernex/quickpay_widget.tpl               the Add Funds widget (copied into your template)
```

> Upgrading from v1? Upload over the old files. Nothing else changes for invoice payments.

### 2. Get your PayerNex keys

At **payernex.com → Dashboard → Developer → API Keys**, copy your **live API key** and your **Webhook Secret**.

### 3. Turn on Quick Pay in PayerNex

At **payernex.com → Payments → Express Wallet → Add wallet**, enter your **own** wallet address (Trust Wallet, MetaMask, Ledger; not an exchange deposit address), pick the networks, and save. Your plan must include Express Wallet.

### 4. Activate the gateway in Dhru

**Dhru Admin → Settings → Payment Gateways → PayerNex → Activate**, then fill in:

| Field | Value |
|---|---|
| Merchant Token | the PayerNex **API key** |
| Webhook Secret | the PayerNex **Webhook Secret** |
| Quick Pay (Express Wallet) | **on** to show Quick Pay on Add Funds |
| Keep the standard Add Funds form | off = only Quick Pay; on = Quick Pay above Dhru's normal form |

Click **Save**, then open the PayerNex settings once more. The setup box shows green ticks when:

- ✅ the callback is allowed in `callback/.htaccess`
- ✅ the Quick Pay widget is on the Add Funds page

The callback URL is sent to PayerNex automatically, so there is nothing to paste for Quick Pay. For invoice payments, keep pasting the callback URL into your PayerNex gateway as before.

### 5. Test

1. Log in to your shop as a customer and open **Add Funds**. You'll see the **Quick Pay** card.
2. Click **Open Quick Pay**. Your deposit address and QR appear.
3. Send at least the minimum (for example 11 USDT on BNB Smart Chain).
4. About a minute later the page says "Payment received" and your balance goes up. In Dhru it appears as a paid **Add Funds** invoice ("PayerNex Quick Pay").

---

## How Quick Pay credits a deposit

1. PayerNex sees the deposit on the blockchain and sends a signed notification to `modules/gateways/callback/payernex.php`.
2. The callback checks the HMAC signature with your Webhook Secret.
3. It reads the deposit back from the PayerNex API with your API key. That answer, not the notification, decides who is credited and how much.
4. It creates an **Add Funds** invoice for the credited amount and pays it with Dhru's own `addPayment()`, so the balance, ledger and statement are all native.
5. Each deposit is credited once (transaction id `PNXEW-<deposit id>`, under a database lock), however many times the notification arrives.

---

## Custom templates

The widget is placed automatically in every `templates/*/client_addfunds.tpl` that has the stock Dhru Add Funds layout. If your theme is customised, the setup box says *"Customised template: add the widget by hand"*. In that case, paste the contents of `payernex/quickpay_widget.tpl` where you want it, then replace:

- `__PNX_BASE__` with your shop's sub-folder path, or delete it if the shop is at the domain root;
- `__PNX_HIDE__` with `1` (only Quick Pay) or `0` (keep the normal form).

---

## Troubleshooting

| Problem | What to check |
|---|---|
| Setup box: *Could not update callback/.htaccess* | Make `modules/gateways/callback/.htaccess` writable, or add `\|^payernex\.php` to its `<FilesMatch` line yourself. |
| Setup box: *Template is not writable* | Make `templates/default/client_addfunds.tpl` writable (644 for your user), then open the gateway settings again. |
| Quick Pay says *not enabled* | Turn on "Quick Pay (Express Wallet)" in the gateway settings and check the Merchant Token. |
| Quick Pay says *Set up Express Wallet…* | Add your settlement wallet in PayerNex → Payments → Express Wallet. |
| Paid but balance not updated | In PayerNex → Express Wallet → Deposits, look at the **Webhook** column. "Failed" usually means the Webhook Secret in Dhru doesn't match. Fix it, then press ↻ on that deposit. |
| Invoice payments: invoice still unpaid | Paste the callback URL into your PayerNex gateway (Gateway Settings → Edit → Callback / Webhook URL). |

---

## Requirements

- Dhru Fusion 2024 or newer, PHP 7.4+ with `curl` and `mysqli`
- A PayerNex account. Quick Pay needs a plan that includes Express Wallet and a settlement wallet set up.

---

## Changelog

**2.0.1**
- Quick Pay opens in its own window (many shops' Content-Security-Policy blocks payment pages in an iframe).
- Credits are converted to the customer's currency with DHRU's currency rates (e.g. USD → PKR).
- The widget sits before the deposit form on newer DHRU templates; PayerNex logo is built in.

**2.0.0**
- Quick Pay (Express Wallet): a permanent deposit address per customer on the Add Funds page, credited automatically as a native Add Funds payment.
- Self-setup: allows the callback in `callback/.htaccess` and places the widget on the Add Funds page, again after Dhru updates; status shown in the gateway settings.
- Deposits are verified twice (signature + API read-back) and credited once.

**1.x**
- Invoice payments through the PayerNex checkout.

© 2026 PayerNex. Developed by [Umair Abubakkar](https://github.com/umairabubakkar).
