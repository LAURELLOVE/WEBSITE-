(function () {
  var MSG = {
    sending: 'Sending…',
    ok: 'Thank you! We have received your request and the COC team will get back to you soon.',
    invalid: 'Please check the highlighted fields and try again.',
    rate: 'Too many submissions from your connection. Please try again later or call us.',
    server: 'Sorry, something went wrong on our side. Please try again later or call us.',
    network: 'We could not reach the server. Please check your connection and try again.'
  };

  function show(form, key, kind) {
    var box = form.querySelector('.form-status');
    box.className = 'form-status ' + kind;
    box.textContent = MSG[key];
  }

  // Appointment dates cannot be in the past.
  var today = new Date();
  var iso = today.getFullYear() + '-' + ('0' + (today.getMonth() + 1)).slice(-2) + '-' + ('0' + today.getDate()).slice(-2);
  document.querySelectorAll('form[data-coc-form] input[type=date]').forEach(function (el) { el.min = iso; });

  document.querySelectorAll('form[data-coc-form]').forEach(function (form) {
    form.noValidate = true;
    var button = form.querySelector('button[type=submit]');

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      form.querySelectorAll('[aria-invalid]').forEach(function (el) { el.removeAttribute('aria-invalid'); });

      button.disabled = true;
      show(form, 'sending', 'busy');

      fetch('api/submit.php', { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' } })
        .then(function (res) {
          return res.json().catch(function () { return { ok: false, error: 'server' }; })
            .then(function (body) { return { status: res.status, body: body }; });
        })
        .then(function (r) {
          if (r.body.ok) {
            form.reset();
            show(form, 'ok', 'ok');
            return;
          }
          if (r.body.error === 'invalid' && r.body.fields) {
            var first = null;
            r.body.fields.forEach(function (name) {
              var el = form.querySelector('[name="' + name + '"]');
              if (el) {
                el.setAttribute('aria-invalid', 'true');
                first = first || el;
              }
            });
            if (first) first.focus();
            show(form, 'invalid', 'error');
          } else if (r.body.error === 'rate') {
            show(form, 'rate', 'error');
          } else {
            show(form, 'server', 'error');
          }
        })
        .catch(function () { show(form, 'network', 'error'); })
        .then(function () { button.disabled = false; });
    });
  });
})();
