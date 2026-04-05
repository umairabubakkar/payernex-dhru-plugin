# PayerNex — Dhru Fusion Integration Plugin

## Files

| File | Description | Where to place |
|------|-------------|----------------|
| `payernex.php` | Payment gateway module | Dhru root directory (`/var/www/dhru/`) |
| `payernexcallback.php` | Webhook receiver | Dhru root directory (`/var/www/dhru/`) |

## Installation

### Step 1: Upload Files

Copy both files to your Dhru Fusion root directory (where `admin.php`, `comm.php` etc. are located):

```bash
# Via SCP
scp payernex.php payernexcallback.php user@your-dhru-server:/path/to/dhru/

# Or via FTP — upload to the root of your Dhru installation
```

### Step 2: Add Payment Gateway in Dhru Admin

1. Go to **Dhru Admin Panel** → **Settings** → **Payment Gateways**
2. Click **Add New Gateway**
3. Select **PayerNex** from the gateway list
4. Fill in the configuration:

| Field | Value |
|-------|-------|
| **PayerNex API URL** | Your PayerNex gateway URL (e.g. `https://pay.yourdomain.com`) |
| **API Key** | Your API key from PayerNex Dashboard → Developer → API Keys |
| **Webhook Secret** | Your webhook secret from PayerNex Dashboard → Developer |
| **Webhook URL** | Auto-filled — copy this URL (usually `https://your-dhru-site.com/payernexcallback.php`) |

5. Click **Save**

### Step 3: Configure PayerNex Dashboard

1. Login to your **PayerNex Dashboard**
2. Go to **Gateway Settings**
   - Enter your Binance API Key (Read-only permission)
   - Enter your Binance API Secret
   - Upload your Binance Funding Wallet QR code
3. The system will auto-extract the QR URL

### Step 4: Test

1. Go to your Dhru site as a customer
2. Click **Add Funds**
3. Select **PayerNex** as payment method
4. You'll be redirected to the PayerNex checkout page
5. Scan QR with Binance app, enter amount, paste NOTE code
6. Payment will be auto-detected and credited to customer account

## How It Works

```
Customer clicks "Add Funds" on Dhru
    ↓
Dhru calls PayerNex API (POST /api/v1/create-order)
    ↓
PayerNex returns checkout URL
    ↓
Customer redirected to PayerNex checkout page
    ↓
Customer scans QR, pays via Binance (C2C, zero fee)
    ↓
PayerNex detects payment (polling Binance API)
    ↓
PayerNex sends webhook to payernexcallback.php
    ↓
Callback verifies signature + amount
    ↓
Dhru credits customer account
    ↓
Customer sees balance updated
```

## Webhook Payload

PayerNex sends this JSON to your callback URL:

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

## Security

- All webhooks are signed with **HMAC-SHA256** (`X-Signature` header)
- Timestamp validation prevents replay attacks (5 minute window)
- Amount verification with 1% tolerance
- Duplicate payment protection (checks invoice status before crediting)

## Troubleshooting

- Check `payernex.log` in your Dhru root for detailed logs
- Verify API URL doesn't have trailing slash
- Verify API Key and Webhook Secret match your PayerNex dashboard
- Make sure `payernexcallback.php` is accessible from the internet
- Test webhook URL: `curl -I https://your-dhru-site.com/payernexcallback.php`
