# PayerNex — Dhru Fusion Payment Gateway Module

Non-Custodial Binance Pay C2C Payment Gateway for Dhru Fusion.

## File Structure

```
modules/
└── gateways/
    ├── payernex.php              ← Gateway module (config + payment link)
    └── callback/
        └── payernex.php          ← Webhook callback handler
```

## Installation

### Step 1: Upload Files

Copy the `modules/` folder into your Dhru Fusion root directory. The files should end up at:

```
/your-dhru-root/modules/gateways/payernex.php
/your-dhru-root/modules/gateways/callback/payernex.php
```

### Step 2: Add Gateway in Dhru Admin

1. Go to **Admin Panel** → **Settings** → **Payment Gateways**
2. Click **Add New Gateway**
3. Select **PayerNex** from the list
4. Configure:

| Field | Value |
|-------|-------|
| **PayerNex URL** | Your PayerNex gateway URL (e.g. `https://pay.yourdomain.com`) |
| **API Key** | From PayerNex Dashboard → Developer → API Keys |
| **Webhook Secret** | From PayerNex Dashboard → Developer section |

5. Note the **Webhook / Callback URL** shown at the bottom — this is auto-generated
6. Click **Save**

### Step 3: Configure PayerNex

1. Login to **PayerNex Dashboard** (`https://pay.yourdomain.com/dashboard`)
2. **Gateway Settings** → Enter Binance API Key + Secret, upload QR code
3. **Developer** → Note your API Key and Webhook Secret

### Step 4: Test

1. Create a test invoice in Dhru (small amount, e.g. $1)
2. Click **Pay Now** → You should be redirected to PayerNex checkout
3. Scan QR with Binance app → Enter amount → Paste NOTE code → Send
4. Payment auto-detected → Dhru invoice marked as Paid

## How It Works

```
Customer → "Add Funds" / "Pay Invoice" on Dhru site
    ↓
Dhru calls payernex_link() → POST /api/v1/create-order to PayerNex
    ↓
PayerNex returns checkout URL → Customer redirected
    ↓
Customer scans QR → Pays via Binance (C2C, zero network fee)
    ↓
PayerNex detects payment → Sends webhook to callback/payernex.php
    ↓
Callback verifies HMAC signature → Validates amount → addPayment()
    ↓
Dhru credits customer account → Invoice marked Paid
```

## Security

- **HMAC-SHA256** signature verification on every webhook
- **Timestamp validation** — rejects requests older than 5 minutes (replay protection)
- **Duplicate protection** — `checkTransID()` prevents double-crediting
- **Amount validation** — 1% tolerance match against invoice total
- **Non-custodial** — PayerNex never holds funds, payments go directly to merchant's Binance wallet

## Webhook Payload (Received by Callback)

```json
{
    "event": "payment.completed",
    "order_ref": "ORD-XXXX-XXX",
    "amount": "10.00000000",
    "currency": "USDT",
    "paid_amount": "10.00000000",
    "paid_currency": "USDT",
    "transaction_id": "P_A212MD1W7DS71114",
    "customer_id": "DHRU-12345",
    "paid_at": "2026-04-06T02:09:23+00:00",
    "timestamp": 1775423363,
    "nonce": "random32charstring"
}
```

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Gateway not showing | Ensure `payernex.php` is in `modules/gateways/` |
| "Connection failed" | Check PayerNex URL is correct and reachable |
| Payment not crediting | Check `tbl_gatewaylog` in DB for error details |
| "Invalid signature" | Verify Webhook Secret matches PayerNex dashboard |
| Callback not receiving | Ensure `callback/payernex.php` is web-accessible |

## Requirements

- Dhru Fusion v2024+
- PHP 7.4+ with cURL extension
- PayerNex account with Binance API configured
