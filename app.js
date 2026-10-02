(function () {
  // Show / hide password
  document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = document.getElementById(btn.getAttribute('data-toggle-password'));
      if (!input) return;
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.setAttribute('aria-pressed', show ? 'true' : 'false');
      btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });
  });

  // Confirm before destructive actions
  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      if (!window.confirm(form.getAttribute('data-confirm'))) e.preventDefault();
    });
  });

  // GLN / GTIN: digits only + live check-digit hint with suggested fix
  function calc(payload) {
    var sum = 0, w = 3;
    for (var i = payload.length - 1; i >= 0; i--) { sum += parseInt(payload[i], 10) * w; w = (w === 3) ? 1 : 3; }
    return (10 - (sum % 10)) % 10;
  }
  function valid(d) { return d.length > 1 && calc(d.slice(0, -1)) === parseInt(d[d.length - 1], 10); }
  function suggest(d, allowed) {
    var out = [];
    if (allowed.indexOf(d.length) !== -1 && valid(d)) return out;
    if (allowed.indexOf(d.length) !== -1 && d.length > 1) out.push(d.slice(0, -1) + calc(d.slice(0, -1)));
    if (allowed.indexOf(d.length + 1) !== -1) { var v = d + calc(d); if (out.indexOf(v) === -1) out.push(v); }
    return out;
  }
  document.querySelectorAll('input[data-gs1]').forEach(function (input) {
    var kind = input.getAttribute('data-gs1');
    var allowed = kind === 'gln' ? [13] : [8, 12, 13, 14];
    var max = allowed[allowed.length - 1];
    var hint = document.createElement('div');
    hint.className = 'hint';
    hint.setAttribute('aria-live', 'polite');
    input.insertAdjacentElement('afterend', hint);
    var touched = false;
    function update() {
      var d = input.value.replace(/\D+/g, '').slice(0, max);
      if (input.value !== d) input.value = d;
      hint.className = 'hint';
      hint.textContent = '';
      if (!d) return;
      var ok = allowed.indexOf(d.length) !== -1 && valid(d);
      if (ok) { hint.textContent = 'Check digit is valid'; hint.className = 'hint ok'; return; }
      if (!touched && d.length < max) { hint.textContent = d.length + ' digits so far'; return; }
      var sug = suggest(d, allowed);
      var lenOk = allowed.indexOf(d.length) !== -1;
      if (!lenOk && !sug.length) {
        hint.textContent = d.length + ' digits so far' + (kind === 'gln' ? ' (a GLN has 13)' : ' (a GTIN has 8, 12, 13 or 14)');
        return;
      }
      hint.className = 'hint bad';
      hint.textContent = (lenOk ? 'Check digit does not match.' : (kind === 'gln' ? 'A GLN has 13 digits.' : 'The check digit may be missing.')) + (sug.length ? ' Did you mean' : '');
      sug.forEach(function (v) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'link-btn';
        b.textContent = v;
        b.addEventListener('click', function () { input.value = v; update(); input.focus(); });
        hint.appendChild(document.createTextNode(' '));
        hint.appendChild(b);
      });
    }
    input.addEventListener('input', update);
    input.addEventListener('blur', function () { touched = true; update(); });
    if (input.value) touched = true;
    update();
  });
})();
