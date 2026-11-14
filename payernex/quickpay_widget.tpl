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
  .pnxqp-bg { position: fixed; inset: 0; z-index: 100000; background: rgba(8,8,10,.72); backdrop-filter: blur(5px); -webkit-backdrop-filter: blur(5px); display: none; align-items: center; justify-content: center; padding: 16px; }
  .pnxqp-bg.show { display: flex; }
  .pnxqp-box { position: relative; width: 100%; max-width: 960px; height: min(820px, 92vh); border-radius: 20px; overflow: hidden; background: #f4f4f1; box-shadow: 0 30px 80px -20px rgba(0,0,0,.7); }
  .pnxqp-box iframe { width: 100%; height: 100%; border: 0; display: block; }
  .pnxqp-x { position: absolute; top: 12px; right: 12px; z-index: 2; width: 36px; height: 36px; border-radius: 50%; border: 0; background: rgba(17,17,17,.75); color: #fff; font-size: 20px; line-height: 36px; cursor: pointer; }
  .pnxqp-done { position: absolute; left: 50%; bottom: 18px; transform: translateX(-50%); z-index: 3; background: #0f2a1b; color: #d1fae5; border: 1px solid rgba(74,222,128,.4); border-radius: 12px; padding: 12px 18px; font-size: 14px; font-weight: 700; display: none; box-shadow: 0 12px 30px -10px rgba(0,0,0,.5); }
  @media (max-width: 640px) {
    .pnxqp-in { padding: 18px 16px; }
    .pnxqp-side { width: 100%; justify-content: space-between; margin-left: 0; }
    .pnxqp-bal { text-align: left; }
    .pnxqp-foot { padding: 10px 16px 14px; }
    .pnxqp-bg { padding: 0; }
    .pnxqp-box { height: 100%; max-width: none; border-radius: 0; }
  }
</style>
{/literal}
  <div class="pnxqp-in">
    <div class="pnxqp-mark" aria-hidden="true">
      <img src="https://payernex.com/images/favicon-icon.svg" width="30" height="30" alt="">
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
  <div class="pnxqp-foot"><span>Credited in about a minute</span><span>Same address every time</span><span>Secured by PayerNex</span></div>
</div>
<div class="pnxqp-bg" id="pnxqp-bg" role="dialog" aria-modal="true" aria-label="Quick Pay">
  <div class="pnxqp-box">
    <button type="button" class="pnxqp-x" id="pnxqp-x" aria-label="Close">&times;</button>
    <iframe id="pnxqp-frame" title="PayerNex Quick Pay" allow="clipboard-write"></iframe>
    <div class="pnxqp-done" id="pnxqp-done" aria-live="polite"></div>
  </div>
</div>
{literal}
<script>
(function () {
  var box = document.getElementById('pnxqp');
  if (!box || box.__pnx) return; box.__pnx = 1;
  var ORIGIN = 'https://payernex.com';
  var $ = function (id) { return document.getElementById(id); };
  var user = box.getAttribute('data-user'), payUrl = box.getAttribute('data-pay'), statusUrl = box.getAttribute('data-status');

  if (box.getAttribute('data-hide-manual') === '1') {
    // Show only Quick Pay: hide DHRU's own Add Funds form that follows the widget.
    var n = $('pnxqp-bg').nextElementSibling;
    while (n) { if (n.tagName !== 'STYLE' && n.tagName !== 'SCRIPT') n.style.display = 'none'; n = n.nextElementSibling; }
  }

  function err(msg) { var e = $('pnxqp-err'); e.textContent = msg || ''; e.style.display = msg ? 'block' : 'none'; }
  function close() { $('pnxqp-bg').classList.remove('show'); document.body.style.overflow = ''; }
  $('pnxqp-x').onclick = close;
  $('pnxqp-bg').addEventListener('click', function (e) { if (e.target === this) close(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });

  $('pnxqp-open').onclick = function () {
    var btn = this; btn.disabled = true; err('');
    fetch(payUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'username=' + encodeURIComponent(user), credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d || !d.ok) { err((d && d.error) || 'Quick Pay is unavailable right now.'); return; }
        if ($('pnxqp-frame').getAttribute('src') !== d.order_url) $('pnxqp-frame').setAttribute('src', d.order_url);
        $('pnxqp-done').style.display = 'none';
        $('pnxqp-bg').classList.add('show'); document.body.style.overflow = 'hidden';
      })
      .catch(function () { err('Network error. Please try again.'); })
      .then(function () { btn.disabled = false; });
  };

  // Wait until DHRU has recorded the deposit, then refresh the balance.
  function waitCredited(id, tries) {
    fetch(statusUrl + '?d=' + encodeURIComponent(id), { cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (s) {
      if (s && s.credited) { $('pnxqp-done').textContent = 'Balance updated. Refreshing…'; setTimeout(function () { location.reload(); }, 1500); }
      else if (tries > 0) setTimeout(function () { waitCredited(id, tries - 1); }, 3000);
      else $('pnxqp-done').textContent = 'Payment received. Your balance will update shortly.';
    }).catch(function () { if (tries > 0) setTimeout(function () { waitCredited(id, tries - 1); }, 4000); });
  }

  window.addEventListener('message', function (e) {
    if (e.origin !== ORIGIN || !e.data || typeof e.data !== 'object') return;
    if (e.data.type === 'payernex:express_wallet.close') close();
    if (e.data.type === 'payernex:express_wallet.paid') {
      var d = $('pnxqp-done');
      d.textContent = 'Payment received: ' + e.data.credited_amount + ' USD is being added to your balance…';
      d.style.display = 'block';
      if (e.data.deposit_id) waitCredited(e.data.deposit_id, 30);
    }
  });
})();
</script>
{/literal}
