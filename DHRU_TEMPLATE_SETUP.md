# PayerNex — Dhru Fusion Template Setup (Add Fund Page)

By default, PayerNex appears in the dropdown on the Add Fund page. To make it show as a **direct radio button** (like PayerOne/gsmcoin) and appear **above other gateways**, follow these steps.

## File to Edit

```
/templates/default/client_addfunds.tpl
```

> Always backup before editing: `cp client_addfunds.tpl client_addfunds.tpl.bak`

---

## Step 1: Add PayerNex Detection Variable

Find this line (around line 110):

```smarty
{if $gsmcoinenabled}
```

Add this **before** it:

```smarty
{assign var="payernexenabled" value=false}
{foreach key=num item=gw from=$gateways}
    {if $gw.sysname eq 'payernex'}{assign var="payernexenabled" value=true}{/if}
{/foreach}
```

---

## Step 2: Add PayerNex Radio Button (Above gsmcoin)

Add this block **between** the detection code and the `{if $gsmcoinenabled}` block:

```smarty
{if $payernexenabled}
    <div class="mb-3">
        <div class="custom-control custom-radio">
            <input type="radio" name="paymentmethod" {if !$paypalexpressenabled}checked="checked"{/if} onchange="addPlus('payernex');" value="payernex" id="payment-payernex" class="custom-control-input">
            <label class="custom-control-label align-items-center " for="payment-payernex">
                {foreach key=num item=gateway from=$gateways}
                    {if $gateway.sysname eq 'payernex'}
                        {$gateway.name}
                    {/if}
                {/foreach}
            </label>
        </div>
    </div>
{/if}
```

Also update the gsmcoin block's checked condition so it doesn't auto-select when PayerNex is active:

```smarty
{if !$paypalexpressenabled and !$payernexenabled}checked="checked"{/if}
```

---

## Step 3: Remove PayerNex from Dropdown

Find:

```smarty
{if $gateway.sysname neq 'gsmcoin'}
```

Replace with:

```smarty
{if $gateway.sysname neq 'gsmcoin' and $gateway.sysname neq 'payernex'}
```

---

## Step 4: Update Default Selection Script

At the bottom of the file, find:

```smarty
{if $paypalexpressenabled}
    addPlus('paypalexpress');
{else if $gsmcoinenabled}
    addPlus('gsmcoin');
{else}
```

Replace with:

```smarty
{if $paypalexpressenabled}
    addPlus('paypalexpress');
{elseif $payernexenabled}
    addPlus('payernex');
{elseif $gsmcoinenabled}
    addPlus('gsmcoin');
{else}
```

---

## Step 5: Update No-Gateway Fallback

At the very bottom, find:

```smarty
{if !$paypalexpressenabled and !$gsmcoinenabled}
```

Replace with:

```smarty
{if !$paypalexpressenabled and !$gsmcoinenabled and !$payernexenabled}
```

---

## Step 6: Clear Template Cache

Delete compiled templates so changes take effect:

```bash
rm -f /your-dhru-root/templates_c/*addfund*
```

---

## Database Settings (Optional)

Run these SQL queries to ensure PayerNex has proper settings:

```sql
-- Make PayerNex appear first in gateway list
UPDATE tbl_paymentgateways SET `order` = 1 WHERE gateway = 'payernex';

-- Enable express wallet (matches gsmcoin behavior)
INSERT INTO tbl_paymentgateways (gateway, setting, value, `order`)
VALUES ('payernex', 'express_wallet', '1', 1);
```

---

## Result

- PayerNex shows as **first radio button** (no dropdown)
- Auto-selected by default on page load
- Other gateways appear below or in dropdown
