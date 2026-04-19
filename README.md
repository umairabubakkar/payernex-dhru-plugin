# PayerNex — Dhru Fusion Payment Gateway

Accept payments from your Dhru Fusion customers through PayerNex. Funds land directly in your Binance wallet or bank account — PayerNex just verifies and confirms the payment, then tells Dhru to mark the invoice paid.

---

## Installation

### Step 1 — Download the plugin files

This folder contains two files you need to upload to your Dhru server:

```
modules/gateways/payernex.php              ← gateway module
modules/gateways/callback/payernex.php     ← payment confirmation handler
```

### Step 2 — Upload to your Dhru server

Using FTP, SFTP, or your hosting control panel's file manager, copy the two files into the matching folders inside your Dhru installation:

| File from this plugin | Upload to (on your Dhru server) |
|---|---|
| `modules/gateways/payernex.php` | `/your-dhru-folder/modules/gateways/payernex.php` |
| `modules/gateways/callback/payernex.php` | `/your-dhru-folder/modules/gateways/callback/payernex.php` |

> **Tip:** The simplest way is to just upload the whole `modules/` folder into your Dhru root — the paths will line up automatically. If the folders already exist (they do in every Dhru install), your FTP client will merge them.

### Step 3 — Get your PayerNex credentials

1. Sign in to your PayerNex account at **https://payernex.com**
2. Go to **Dashboard → API Keys** (left sidebar under *Developer*)
3. You'll see two values — keep this page open, you'll paste them into Dhru next:
   - **API Key** (a long hex string)
   - **Webhook Secret**

### Step 4 — Enable the gateway in Dhru

1. Log in to your **Dhru Admin Panel**
2. Go to **Settings → Payment Gateways**
3. Click **Activate** on the **PayerNex** row
4. Fill in the two fields:

   | Field | What to paste |
   |---|---|
   | **Merchant Token** | The **API Key** you copied from PayerNex in Step 3 |
   | **Webhook Secret** | The **Webhook Secret** from the same page |

5. Click **Save Changes**
6. On the same screen you'll see a **Webhook / Callback URL** box — copy that URL (you'll need it in the next step)

### Step 5 — Paste the Callback URL into your PayerNex gateway

The callback URL is set per-gateway inside PayerNex, not in a global setting.

1. Back in your **PayerNex Dashboard**, open **Gateway Settings** (left sidebar under *Payments*)
2. Click **Edit** on the gateway you want to receive Dhru payments on (Binance / Bank / TRC20 / BEP20 — whichever one your customers will pay through)
3. Find the field labelled **Callback / Webhook URL**
4. Paste the URL you copied from Dhru in Step 4
5. Click **Update Gateway**

> If you have multiple gateways enabled (e.g. Binance + BEP20 wallet), paste the same callback URL into each one — PayerNex will notify Dhru regardless of which method the customer chose.

### Step 6 — Test it

1. In Dhru, add a small amount of funds to any test customer (e.g. $1)
2. Choose **PayerNex** as the payment method and proceed
3. You'll be redirected to the PayerNex checkout page
4. Scan the QR with your Binance app (or send USDT manually), paste the NOTE code shown, confirm the payment
5. Within a few seconds Dhru will mark the invoice as **Paid** and the customer's balance will update

Done — PayerNex is live on your Dhru panel.

---

## Troubleshooting

| Problem | What to check |
|---|---|
| "PayerNex" not visible under Payment Gateways | Make sure both files uploaded to the correct folders. Refresh the Dhru admin page. |
| Customer redirected but sees "Invalid credentials" | **Merchant Token** in Dhru doesn't match the API Key in PayerNex. Re-copy and save. |
| Payment completed on PayerNex but invoice still unpaid on Dhru | The Dhru callback URL (Step 4) isn't pasted into your PayerNex gateway's **Callback / Webhook URL** field (Step 5). Open the gateway in PayerNex → Edit → paste → Update. |
| "Invalid signature" in Dhru gateway log | The **Webhook Secret** doesn't match. Copy it fresh from the PayerNex dashboard into Dhru. |
| Redirect page says "Order not found" | Your Dhru server can't reach PayerNex. Check your server's outbound internet connection / firewall. |

Need help? Contact your PayerNex reseller or email **support@payernex.com** with a screenshot of the error and your Dhru site URL.

---

## Requirements

- **Dhru Fusion** v2024 or newer
- **PHP 7.4+** with the `curl` extension enabled (every standard hosting setup already has this)
- An active **PayerNex account** with at least one payment gateway (Binance, bank, or crypto wallet) configured

---

© 2026 PayerNex. Developed by [Umair Abubakkar](https://github.com/umairabubakkar).
