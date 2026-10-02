/* Authenticator Global — live verification + "report suspicious product".
   Replaces the placeholder handleSearch() in index.html. Safe to load from <head> or end of <body>. */
(function () {
  function init() {
    var input = document.getElementById('codeInput');
    var btn   = document.getElementById('searchBtn');
    var box   = document.getElementById('resultBox');
    if (!input || !btn || !box) { console.error('verify.js: page elements not found'); return; }
    var BTN_HTML = btn.innerHTML;

    // Visitors no longer see their coordinates / the "View Map" box. The location is still captured with every
    // verification and stored in the database (admin dashboard shows country + precise-location link).
    window.showLocationTag = function () {};

    var reported = {};                     // codes already reported in this visit

    var st = document.createElement('style');
    st.textContent =
      '.result-box.authentic{background:rgba(26,122,60,.07);border:1px solid rgba(26,122,60,.25);color:#1a7a3c}' +
      '.result-box.suspicious{background:rgba(242,194,0,.12);border:1px solid rgba(196,150,0,.45);color:#8a6100}' +
      '.result-box .rb-body{display:flex;flex-direction:column;gap:4px}' +
      '.result-box .rb-title{font-weight:500;font-size:14px}' +
      '.result-box .rb-meta{opacity:.85;font-size:12px}' +
      '.rb-report{align-self:flex-start;margin-top:8px;background:#fff;color:#8a6100;border:1px solid rgba(196,150,0,.6);border-radius:7px;padding:8px 14px;font:inherit;font-size:12.5px;font-weight:500;cursor:pointer}' +
      '.rb-report:hover{background:#fff8d6}.rb-report:disabled{opacity:.7;cursor:default;background:transparent}' +
      '.ag-ov{position:fixed;inset:0;background:rgba(17,17,17,.55);display:none;align-items:center;justify-content:center;z-index:1000;padding:14px}' +
      '.ag-ov.show{display:flex}' +
      '.ag-modal{background:#fff;border-radius:14px;width:100%;max-width:460px;max-height:calc(100% - 8px);overflow:auto;padding:22px;position:relative;box-shadow:0 20px 60px rgba(0,0,0,.25);color:#3A3A3A;font-size:14px;text-align:left}' +
      '.ag-modal h3{font-size:17px;margin:0 28px 4px 0;font-weight:600}' +
      '.ag-sub{font-size:12px;color:#6b6b6b;margin-bottom:8px;word-break:break-all}' +
      '.ag-modal label{display:block;font-size:11px;letter-spacing:1.2px;text-transform:uppercase;color:#6b6b6b;margin:12px 0 5px}' +
      '.ag-modal input,.ag-modal textarea{width:100%;border:1.5px solid rgba(58,58,58,.25);border-radius:8px;padding:10px 12px;font:inherit;font-size:16px;background:#fafafa;box-sizing:border-box}' +
      '.ag-modal textarea{min-height:76px;resize:vertical}' +
      '.ag-modal input:focus,.ag-modal textarea:focus{outline:none;border-color:#930E16}' +
      '.ag-modal .bad{border-color:#930E16}' +
      '.ag-err{color:#930E16;font-size:12.5px;min-height:18px;margin-top:10px}' +
      '.ag-actions{display:flex;gap:10px;margin-top:8px}' +
      '.ag-btn{flex:1;border:0;border-radius:8px;padding:11px 16px;font:inherit;font-weight:500;cursor:pointer;background:#930E16;color:#fff}' +
      '.ag-btn.ghost{background:#fff;color:#6b6b6b;border:1px solid rgba(58,58,58,.25)}.ag-btn:disabled{opacity:.6;cursor:default}' +
      '.ag-x{position:absolute;top:10px;right:12px;background:none;border:0;font-size:18px;color:#9a9a9a;cursor:pointer}' +
      '.ag-hp{position:absolute;left:-9999px;width:1px;height:1px;opacity:0}' +
      '.ag-ok{text-align:center;padding:14px 4px}.ag-ok b{display:block;font-size:16px;margin-bottom:6px}';
    document.head.appendChild(st);

    /* ---------- result rendering ---------- */
    function render(cls, lines, action) {
      box.className = 'result-box show ' + cls;
      box.replaceChildren();
      var body = document.createElement('div');
      body.className = 'rb-body';
      lines.forEach(function (l) {
        if (!l[0]) return;
        var d = document.createElement('div');
        d.className = l[1] || '';
        d.textContent = l[0];            // textContent => safe against injected HTML
        body.appendChild(d);
      });
      if (action) {
        var b = document.createElement('button');
        b.type = 'button'; b.className = 'rb-report'; b.textContent = action.label;
        if (action.disabled) b.disabled = true; else b.addEventListener('click', function () { action.onClick(b); });
        body.appendChild(b);
      }
      box.appendChild(body);
    }

    function getLocation() {
      return new Promise(function (resolve) {
        if (!navigator.geolocation) return resolve(null);
        navigator.geolocation.getCurrentPosition(
          function (p) { resolve({ lat: +p.coords.latitude.toFixed(6), lng: +p.coords.longitude.toFixed(6), acc: Math.round(p.coords.accuracy) }); },
          function () { resolve(null); },
          { enableHighAccuracy: true, timeout: 5000, maximumAge: 30000 }
        );
      });
    }

    // GPS point -> country name/code (free client-side reverse geocoding; server fills it in later if this fails)
    async function getCountry(loc) {
      if (!loc) return {};
      try {
        var ctl = new AbortController();
        var t = setTimeout(function () { ctl.abort(); }, 3000);
        var r = await fetch('https://api.bigdatacloud.net/data/reverse-geocode-client?latitude=' + loc.lat +
                            '&longitude=' + loc.lng + '&localityLanguage=en', { signal: ctl.signal });
        clearTimeout(t);
        var j = await r.json();
        return { country: j.countryName || '', cc: j.countryCode || '' };
      } catch (e) { return {}; }
    }

    function setBusy(on) {
      btn.innerHTML = on
        ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" style="animation:spin .8s linear infinite"><path d="M21 12a9 9 0 11-6.219-8.56"/></svg> Checking...'
        : BTN_HTML;
      btn.disabled = on;
    }

    function productLine(d) {
      if (!d.product) return '';
      return d.product + (d.package_type ? ' (' + d.package_type + ')' : '') + (d.gtin ? '  •  GTIN ' + d.gtin : '');
    }

    /* ---------- report modal ---------- */
    var ov = null, form = null, errEl = null, okEl = null, sendBtn = null, current = { code: '', country: '', opener: null };

    function buildModal() {
      if (ov) return;
      ov = document.createElement('div');
      ov.className = 'ag-ov';
      ov.innerHTML =
        '<div class="ag-modal" role="dialog" aria-modal="true" aria-labelledby="agRepTitle">' +
          '<button type="button" class="ag-x" aria-label="Close">✕</button>' +
          '<div id="agRepFormWrap"><h3 id="agRepTitle">Report suspicious product</h3>' +
          '<div class="ag-sub">Code: <span id="agRepCode"></span></div>' +
          '<form id="agRepForm" novalidate>' +
            '<label for="agEmail">Email *</label><input id="agEmail" name="email" type="email" autocomplete="email" maxlength="255"/>' +
            '<label for="agMobile">Mobile Number *</label><input id="agMobile" name="mobile" type="tel" autocomplete="tel" maxlength="30"/>' +
            '<label for="agCountry">Country *</label><input id="agCountry" name="country" autocomplete="country-name" maxlength="100"/>' +
            '<label for="agShop">Shop / Retailer Name *</label><input id="agShop" name="shop" maxlength="255"/>' +
            '<label for="agAddress">Address</label><input id="agAddress" name="address" autocomplete="street-address" maxlength="500"/>' +
            '<label for="agRemarks">Remarks</label><textarea id="agRemarks" name="remarks" maxlength="2000"></textarea>' +
            '<input class="ag-hp" name="website" tabindex="-1" autocomplete="off" aria-hidden="true"/>' +
            '<div class="ag-err" id="agRepErr" role="alert"></div>' +
            '<div class="ag-actions"><button type="button" class="ag-btn ghost" id="agRepCancel">Cancel</button>' +
            '<button type="submit" class="ag-btn" id="agRepSend">Submit report</button></div>' +
          '</form></div>' +
          '<div class="ag-ok" id="agRepOk" style="display:none"><b>Thank you</b>Your report has been submitted.' +
            '<div class="ag-actions" style="margin-top:16px"><button type="button" class="ag-btn" id="agRepDone">Close</button></div></div>' +
        '</div>';
      document.body.appendChild(ov);
      form = ov.querySelector('#agRepForm'); errEl = ov.querySelector('#agRepErr'); okEl = ov.querySelector('#agRepOk'); sendBtn = ov.querySelector('#agRepSend');

      ov.addEventListener('click', function (e) { if (e.target === ov) closeModal(); });
      ov.querySelector('.ag-x').addEventListener('click', closeModal);
      ov.querySelector('#agRepCancel').addEventListener('click', closeModal);
      ov.querySelector('#agRepDone').addEventListener('click', closeModal);
      document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && ov.classList.contains('show')) closeModal(); });
      form.addEventListener('submit', submitReport);
    }

    function openModal(code, country, opener) {
      buildModal();
      current = { code: code, country: country, opener: opener };
      ov.querySelector('#agRepCode').textContent = code;
      ov.querySelector('#agRepFormWrap').style.display = '';
      okEl.style.display = 'none';
      errEl.textContent = '';
      Array.prototype.forEach.call(form.elements, function (el) { el.classList && el.classList.remove('bad'); });
      var c = form.elements.country; if (!c.value && country) c.value = country;
      sendBtn.disabled = false; sendBtn.textContent = 'Submit report';
      ov.classList.add('show');
      form.elements.email.focus();
    }

    function closeModal() {
      ov.classList.remove('show');
      if (current.opener && document.body.contains(current.opener)) current.opener.focus();
    }

    function fail(field, msg) {
      errEl.textContent = msg;
      if (field && form.elements[field]) { form.elements[field].classList.add('bad'); form.elements[field].focus(); }
    }

    async function submitReport(e) {
      e.preventDefault();
      Array.prototype.forEach.call(form.elements, function (el) { el.classList && el.classList.remove('bad'); });
      errEl.textContent = '';
      var f = form.elements;
      var v = { email: f.email.value.trim(), mobile: f.mobile.value.trim(), country: f.country.value.trim(),
                shop: f.shop.value.trim(), address: f.address.value.trim(), remarks: f.remarks.value.trim() };

      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.email))           return fail('email', 'Please enter a valid email address.');
      if (!/^\+?[0-9][0-9\s\-()]{5,19}$/.test(v.mobile))         return fail('mobile', 'Please enter a valid mobile number.');
      if (!v.country)                                            return fail('country', 'Please enter your country.');
      if (!v.shop)                                               return fail('shop', 'Please enter the shop / retailer name.');

      sendBtn.disabled = true; sendBtn.textContent = 'Submitting...';
      try {
        var res = await fetch('/api/report.php', {
          method: 'POST', headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ code: current.code, email: v.email, mobile: v.mobile, country: v.country, shop: v.shop,
                                 address: v.address, remarks: v.remarks, website: f.website.value })
        });
        var d = await res.json();
        if (d.status === 'ok') {
          reported[current.code] = true;
          if (current.opener) { current.opener.textContent = 'Report submitted ✓'; current.opener.disabled = true; }
          ov.querySelector('#agRepFormWrap').style.display = 'none';
          okEl.style.display = '';
          form.reset();
          ov.querySelector('#agRepDone').focus();
          return;
        }
        fail(d.fields ? Object.keys(d.fields)[0] : null, d.message || 'Could not submit the report. Please try again.');
      } catch (err) {
        fail(null, 'Network error. Please check your connection and try again.');
      }
      sendBtn.disabled = false; sendBtn.textContent = 'Submit report';
    }

    /* ---------- verification ---------- */
    window.handleSearch = async function () {
      var code = input.value.trim();
      if (!code) {
        input.focus();
        input.style.borderColor = 'var(--crimson)';
        setTimeout(function () { input.style.borderColor = ''; }, 1400);
        return;
      }

      setBusy(true);
      box.classList.remove('show');
      var old = document.getElementById('locationTag');
      if (old) old.remove();

      var loc = await getLocation();
      var geo = await getCountry(loc);
      var shown = false;                  // becomes true only when a real verification result is on screen

      try {
        var res = await fetch('/api/verify.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ code: code, lat: loc && loc.lat, lng: loc && loc.lng, acc: loc && loc.acc, country: geo.country, cc: geo.cc })
        });
        var d = await res.json();

        if (d.status === 'authentic') {
          render('authentic', [['Scan Count : ' + d.scan_count, 'rb-meta'], [d.title, 'rb-title'], [productLine(d), 'rb-meta']]);
          shown = true;
        } else if (d.status === 'suspicious') {
          var rc = d.code || code;
          render('suspicious', [['Scan Count : ' + d.scan_count, 'rb-meta'], [d.title, 'rb-title'], [productLine(d), 'rb-meta']],
            reported[rc] ? { label: 'Report submitted ✓', disabled: true }
                         : { label: 'Report this product', onClick: function (b) { openModal(rc, geo.country || '', b); } });
          shown = true;
        } else if (d.status === 'fake') {
          render('not-found', [[d.title, 'rb-title'], [d.message, 'rb-meta'], ['Scan Count : ' + d.scan_count, 'rb-meta']]);
          shown = true;
        } else if (d.status === 'invalid') {
          render('not-found', [[d.title, 'rb-title'], [d.message, 'rb-meta'],
                               [d.code ? 'Code checked: ' + d.code + ' (' + d.code.length + ' chars)' : '', 'rb-meta']]);
          shown = true;
        } else {
          render('not-found', [[d.message || 'Something went wrong. Please try again.', 'rb-title']]);
        }
      } catch (e) {
        render('not-found', [['Network error. Please check your connection and try again.', 'rb-title']]);
      }

      // Clear the search box only after a verification result is actually displayed (the result stays visible).
      // Setting .value from code does not fire an 'input' event, so index.html's "hide result on typing" is not triggered.
      if (shown) input.value = '';
      setBusy(false);
    };
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();