/* Authenticator Global — "Install app" prompt */
(function () {
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register('/sw.js').catch(function () {});
    });
  }

  // Already running as an installed app? Nothing to show.
  var installed = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
  if (installed || sessionStorage.getItem('agInstallDismissed')) return;

  var deferred = null;
  var isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent) && !window.MSStream;

  var css =
    '#agInstall{position:fixed;left:50%;bottom:18px;transform:translateX(-50%);z-index:900;display:none;' +
    'align-items:center;gap:12px;background:#fff;border:1px solid rgba(58,58,58,.18);border-radius:12px;' +
    'padding:12px 14px 12px 16px;box-shadow:0 10px 40px rgba(58,58,58,.18);max-width:calc(100% - 24px);' +
    'font-family:"DM Sans",sans-serif;font-size:13px;color:#3A3A3A}' +
    '#agInstall.show{display:flex}' +
    '#agInstall button{font:inherit;cursor:pointer;border-radius:7px;padding:8px 14px;white-space:nowrap}' +
    '#agInstallBtn{background:#930E16;color:#fff;border:0;font-weight:500}' +
    '#agInstallX{background:none;border:0;color:#9a9a9a;font-size:16px;padding:4px 6px}';
  var st = document.createElement('style'); st.textContent = css; document.head.appendChild(st);

  var bar = document.createElement('div');
  bar.id = 'agInstall';
  bar.innerHTML = '<span id="agInstallMsg">Install Authenticator Global on your device</span>' +
    '<button id="agInstallBtn">Install</button><button id="agInstallX" aria-label="Dismiss">✕</button>';
  document.body.appendChild(bar);

  function hide(remember) {
    bar.classList.remove('show');
    if (remember) sessionStorage.setItem('agInstallDismissed', '1');
  }
  document.getElementById('agInstallX').onclick = function () { hide(true); };

  // Chrome / Edge / Samsung Internet / Android: browser fires this when the app is installable
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    deferred = e;
    bar.classList.add('show');
  });
  document.getElementById('agInstallBtn').onclick = async function () {
    if (deferred) {
      deferred.prompt();
      await deferred.userChoice;
      deferred = null;
      hide(false);
    } else if (isIOS) {
      hide(false);
    }
  };
  window.addEventListener('appinstalled', function () { hide(false); });

  // iPhone/iPad Safari has no install event — show manual instructions instead
  if (isIOS) {
    document.getElementById('agInstallMsg').textContent = 'To install: tap the Share icon, then "Add to Home Screen"';
    document.getElementById('agInstallBtn').textContent = 'Got it';
    bar.classList.add('show');
  }
})();
