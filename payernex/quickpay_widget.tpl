{* PayerNex Quick Pay widget for the DHRU Add Funds page (client_addfunds.tpl).
   Injected automatically by modules/gateways/payernex.php; edit this file, not the template. *}
<div id="pnxqp" class="pnxqp"
     data-user="{$userdetails.username|escape:'html'}"
     data-cur="{$userdetails.currency.prefix|escape:'html'}"
     data-bal="{$userdetails.credit.credit|escape:'html'}"
     data-pay="__PNX_BASE__/payernex/quickpay.php"
     data-status="__PNX_BASE__/payernex/status.php"
     data-hide-manual="__PNX_HIDE__">
{literal}
<style>
  .pnxqp { position: relative; margin: 0 0 22px; border-radius: 18px; overflow: hidden; color: #f3f3ee; font-family: Inter, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
    background: radial-gradient(120% 140% at 100% 0%, #3a2a07 0%, #17120a 38%, #0c0c0d 72%); border: 1px solid rgba(245,158,11,.28); box-shadow: 0 18px 40px -22px rgba(0,0,0,.6); }
  .pnxqp * { box-sizing: border-box; }
  .pnxqp-in { display: flex; align-items: center; gap: 20px; padding: 22px 24px; flex-wrap: wrap; }
  .pnxqp-mark { width: 52px; height: 52px; border-radius: 15px; background: #0b0b0c; border: 1px solid rgba(245,158,11,.35); display: grid; place-items: center; flex: none; }
  .pnxqp-main { flex: 1 1 280px; min-width: 0; }
  .pnxqp-t { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; font-size: 19px; font-weight: 800; letter-spacing: -.01em; }
  .pnxqp-badge { font-size: 10.5px; font-weight: 800; letter-spacing: .16em; text-transform: uppercase; color: #1b1300; background: linear-gradient(180deg,#fcd34d,#f59e0b); padding: 4px 9px; border-radius: 999px; }
  .pnxqp-s { font-size: 13.5px; color: #c9c6bb; margin-top: 4px; line-height: 1.5; }
  .pnxqp-chips { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 10px; }
  .pnxqp-chip { font-size: 11.5px; font-weight: 700; color: #f3f3ee; background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.12); border-radius: 999px; padding: 4px 10px; }
  .pnxqp-side { display: flex; align-items: center; gap: 16px; margin-left: auto; flex-wrap: wrap; }
  .pnxqp-bal { text-align: right; }
  .pnxqp-bal small { display: block; font-size: 10.5px; letter-spacing: .16em; text-transform: uppercase; color: #9b978b; font-weight: 700; }
  .pnxqp-bal b { font-size: 20px; font-weight: 800; }
  .pnxqp-btn { border: 0; cursor: pointer; border-radius: 12px; padding: 14px 22px; font-size: 15px; font-weight: 800; color: #1b1300; background: linear-gradient(180deg,#fcd34d,#f59e0b 55%,#d97706); box-shadow: 0 8px 20px -8px rgba(245,158,11,.6); white-space: nowrap; display: inline-flex; align-items: center; gap: 8px; }
  .pnxqp-btn:hover { filter: brightness(1.06); }
  .pnxqp-btn:disabled { opacity: .6; cursor: wait; }
  .pnxqp-foot { display: flex; gap: 16px; flex-wrap: wrap; padding: 10px 24px 14px; border-top: 1px solid rgba(255,255,255,.07); font-size: 12px; color: #9b978b; }
  .pnxqp-foot span::before { content: '✓ '; color: #4ade80; font-weight: 800; }
  .pnxqp-err { color: #fca5a5; font-size: 13px; padding: 0 24px 14px; display: none; }
  .pnxqp-wait { display: none; align-items: center; gap: 10px; flex-wrap: wrap; margin: 0 24px 16px; padding: 12px 14px; border-radius: 12px; background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.1); font-size: 13.5px; color: #e7e5dc; }
  .pnxqp-wait.show { display: flex; }
  .pnxqp-wait.ok { background: rgba(74,222,128,.08); border-color: rgba(74,222,128,.35); color: #d1fae5; }
  .pnxqp-dot { width: 8px; height: 8px; border-radius: 50%; background: #f59e0b; animation: pnxqp-p 1.6s infinite; flex: none; }
  .pnxqp-wait.ok .pnxqp-dot { background: #4ade80; animation: none; }
  @keyframes pnxqp-p { 0% { box-shadow: 0 0 0 0 rgba(245,158,11,.55); } 70% { box-shadow: 0 0 0 8px rgba(245,158,11,0); } 100% { box-shadow: 0 0 0 0 rgba(245,158,11,0); } }
  .pnxqp-link { background: none; border: 0; color: #fcd34d; font-weight: 700; text-decoration: underline; cursor: pointer; padding: 0; font-size: 13px; }
  @media (max-width: 640px) {
    .pnxqp-in { padding: 18px 16px; }
    .pnxqp-side { width: 100%; justify-content: space-between; margin-left: 0; }
    .pnxqp-bal { text-align: left; }
    .pnxqp-foot { padding: 10px 16px 14px; }
    .pnxqp-wait { margin: 0 16px 14px; }
  }
</style>
{/literal}
  <div class="pnxqp-in">
    <div class="pnxqp-mark" aria-hidden="true">
      <svg width="34" height="34" viewBox="0.45 0.5 1.3 1.3" aria-hidden="true"><path fill="#F59E0B" d="M1.17 1.23l-0.55 0 0 0.47 0.55 0 0 -0.47c0.08,0 0.32,0.02 0.38,-0.01 0.1,-0.04 0.12,-0.16 0.05,-0.25l-0.26 -0.33c-0.03,-0.04 -0.07,-0.11 -0.1,-0.11 -0.18,0 -0.43,-0.01 -0.61,0 0.02,0.03 0.37,0.48 0.41,0.53 0.02,0.03 0.12,0.15 0.13,0.17z"/></svg>
    </div>
    <div class="pnxqp-main">
      <div class="pnxqp-t">Quick Pay · USDT / USDC <span class="pnxqp-badge">Instant</span></div>
      <div class="pnxqp-s">Your own deposit address. Send any amount, any time: it is added to your balance automatically, no invoice needed.</div>
      <div class="pnxqp-chips"><span class="pnxqp-chip">USDT</span><span class="pnxqp-chip">USDC</span><span class="pnxqp-chip">BNB Smart Chain (BEP-20)</span></div>
    </div>
    <div class="pnxqp-side">
      <div class="pnxqp-bal"><small>Your balance</small><b>{$userdetails.currency.prefix|escape:'html'}{$userdetails.credit.credit|escape:'html'}</b></div>
      <button type="button" class="pnxqp-btn" id="pnxqp-open">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M13 2L3 14h8l-1 8 10-12h-8l1-8z"/></svg>
        Open Quick Pay
      </button>
    </div>
  </div>
  <div class="pnxqp-err" id="pnxqp-err" role="alert"></div>
  <div class="pnxqp-wait" id="pnxqp-wait" aria-live="polite">
    <span class="pnxqp-dot"></span>
    <span id="pnxqp-wait-t">Quick Pay is open in a new window. Send your payment there; this page updates by itself.</span>
    <button type="button" class="pnxqp-link" id="pnxqp-again">Reopen window</button>
  </div>
  <div class="pnxqp-foot"><span>Credited in about a minute</span><span>Same address every time</span><span>Secured by PayerNex</span></div>
</div>
{literal}
<script>
(function () {
  var box = document.getElementById('pnxqp');
  if (!box || box.__pnx) return; box.__pnx = 1;
  var ORIGIN = 'https://payernex.com';
  var $ = function (id) { return document.getElementById(id); };
  var user = box.getAttribute('data-user'), payUrl = box.getAttribute('data-pay'), statusUrl = box.getAttribute('data-status');
  var win = null, url = null, closedTimer = null;

  if (box.getAttribute('data-hide-manual') === '1') {
    // Show only Quick Pay: hide DHRU's own Add Funds form that follows the widget.
    var n = box.nextElementSibling;
    while (n) { if (n.tagName !== 'STYLE' && n.tagName !== 'SCRIPT') n.style.display = 'none'; n = n.nextElementSibling; }
  }

  function err(msg) { var e = $('pnxqp-err'); e.textContent = msg || ''; e.style.display = msg ? 'block' : 'none'; }
  function wait(text, ok) { var w = $('pnxqp-wait'); w.classList.add('show'); w.classList.toggle('ok', !!ok); if (text) $('pnxqp-wait-t').textContent = text; }

  // The PayerNex page opens in its own window: shop pages often have a
  // Content-Security-Policy that blocks payment pages inside an iframe.
  function openWindow(target) {
    var W = Math.min(980, screen.availWidth - 40), H = Math.min(900, screen.availHeight - 60);
    var L = Math.max(0, (screen.availWidth - W) / 2), T = Math.max(0, (screen.availHeight - H) / 2);
    return window.open(target || '', 'pnx_quickpay', 'width=' + W + ',height=' + H + ',left=' + L + ',top=' + T + ',menubar=no,toolbar=no,location=yes');
  }
  function watchClosed() {
    clearInterval(closedTimer);
    closedTimer = setInterval(function () { if (win && win.closed) { clearInterval(closedTimer); $('pnxqp-wait-t').textContent = 'Quick Pay window closed. Paid already? Your balance updates by itself in about a minute.'; } }, 1500);
  }

  $('pnxqp-open').onclick = function () {
    var btn = this; btn.disabled = true; err('');
    // Open the window inside the click so popup blockers allow it.
    win = url ? openWindow(url) : openWindow('');
    if (win && !url) { try { win.document.write('<p style="font:16px sans-serif;padding:24px">Opening Quick Pay…</p>'); } catch (e) {} }
    fetch(payUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'username=' + encodeURIComponent(user), credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d || !d.ok) { if (win) win.close(); err((d && d.error) || 'Quick Pay is unavailable right now.'); return; }
        url = d.order_url;
        if (!win) { window.location.href = url; return; }   // popup blocked: open it here
        win.location.href = url;
        try { win.focus(); } catch (e) {}
        wait('Quick Pay is open in a new window. Send your payment there; this page updates by itself.', false);
        watchClosed();
      })
      .catch(function () { if (win) win.close(); err('Network error. Please try again.'); })
      .then(function () { btn.disabled = false; });
  };
  $('pnxqp-again').onclick = function () { if (url) { win = openWindow(url); watchClosed(); } else $('pnxqp-open').click(); };

  // Wait until DHRU has recorded the deposit, then refresh the balance.
  function waitCredited(id, tries) {
    fetch(statusUrl + '?d=' + encodeURIComponent(id), { cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (s) {
      if (s && s.credited) { wait('Balance updated. Refreshing…', true); setTimeout(function () { location.reload(); }, 1500); }
      else if (tries > 0) setTimeout(function () { waitCredited(id, tries - 1); }, 3000);
      else wait('Payment received. Your balance will update shortly.', true);
    }).catch(function () { if (tries > 0) setTimeout(function () { waitCredited(id, tries - 1); }, 4000); });
  }

  window.addEventListener('message', function (e) {
    if (e.origin !== ORIGIN || !e.data || typeof e.data !== 'object') return;
    if (e.data.type === 'payernex:express_wallet.paid') {
      wait('Payment received: ' + e.data.credited_amount + ' USD is being added to your balance…', true);
      if (e.data.deposit_id) waitCredited(e.data.deposit_id, 30);
    }
  });
})();
</script>
{/literal}
