/**
 * ls_captcha front controller.
 *
 * Responsibilities:
 *  - load the active provider script(s) at the right time (immediate /
 *    on-interaction / after-consent), applying a CSP nonce if provided;
 *  - bind every widget to its own form and guarantee a hidden field named
 *    cfg.tokenField, filled with that widget's token, inside the form (the
 *    server always reads that one field);
 *  - for "execute" providers (reCAPTCHA v3 / invisible, hCaptcha invisible),
 *    trigger the challenge on submit and submit once the token is available.
 *
 * Submissions are intercepted in the capture phase on document, so the token
 * is in place before any other submit handler runs — including the
 * ps_emailsubscription AJAX handler, which serializes the form immediately.
 *
 * Loading strategy (cfg.loadStrategy):
 *  - 'immediate'   : load on page ready.
 *  - 'interaction' : load on first interaction with a protected form.
 *  - 'consent'     : load only after consent (cookie / JS signal / click).
 * Providers that require consent are always forced to 'consent' server-side.
 *
 * Wordings are read from the data-ls-captcha-msg-* attributes of the widgets
 * (translated server-side); this file contains no user-facing text.
 */
(function () {
  'use strict';

  var cfg = window.lsCaptcha;
  if (!cfg || !cfg.tokenField) {
    return;
  }

  var TOKEN_FIELD = cfg.tokenField;
  var EXTRA = cfg.extra || {};
  var READY_TIMEOUT = 15000;

  var loaded = false; // provider scripts injected
  var providerReady = false; // provider API usable
  var providerFailed = false; // a provider script failed to load
  var readyQueue = [];
  var placeholders = [];
  var forms = []; // protected forms; each carries its state in form.lsCaptcha

  // Zone => name of the submit control identifying its form.
  var ZONE_CONTROL = {
    registration: 'submitCreate',
    login: 'submitLogin',
    contact: 'submitMessage',
    newsletter: 'submitNewsletter'
  };

  function onDomReady(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  // ---- Provider script loading --------------------------------------------

  function providerApi() {
    return EXTRA.api ? window[EXTRA.api] : null;
  }

  function markReady() {
    if (providerReady) {
      return;
    }
    providerReady = true;
    renderWidgets();
    var queue = readyQueue;
    readyQueue = [];
    queue.forEach(function (entry) {
      clearTimeout(entry.timer);
      entry.ok();
    });
  }

  function markFailed() {
    providerFailed = true;
    var queue = readyQueue;
    readyQueue = [];
    queue.forEach(function (entry) {
      clearTimeout(entry.timer);
      entry.ko();
    });
  }

  // Called by the provider scripts loaded with ?onload=<cfg.onloadCallback>.
  window[cfg.onloadCallback || 'lsCaptchaOnload'] = markReady;

  function loadProvider() {
    if (loaded) {
      return;
    }
    loaded = true;
    if (EXTRA.capWasmUrl) {
      window.CAP_CUSTOM_WASM_URL = EXTRA.capWasmUrl;
    }

    var scripts = (cfg.scripts || []).filter(function (s) {
      return s && s.url;
    });
    var pending = scripts.length;
    // Providers without an onload callback are usable once their scripts ran.
    var scriptDone = function () {
      if (--pending === 0 && !EXTRA.waitOnload) {
        markReady();
      }
    };
    if (pending === 0) {
      markReady();
      return;
    }

    scripts.forEach(function (s) {
      if (document.getElementById(s.id)) {
        scriptDone();
        return;
      }
      var el = document.createElement('script');
      el.id = s.id;
      el.src = s.url;
      el.async = true;
      el.defer = true;
      if (s.module) {
        el.type = 'module';
      }
      if (cfg.nonce) {
        el.setAttribute('nonce', cfg.nonce);
        el.nonce = cfg.nonce;
      }
      el.addEventListener('load', scriptDone);
      el.addEventListener('error', markFailed);
      document.head.appendChild(el);
    });
  }

  // Run ok() once the provider API is usable (loading it if needed), or ko()
  // if it fails to load or does not answer in time.
  function whenReady(ok, ko) {
    if (providerReady) {
      ok();
      return;
    }
    if (providerFailed) {
      ko();
      return;
    }
    var entry = { ok: ok, ko: ko };
    entry.timer = setTimeout(function () {
      var index = readyQueue.indexOf(entry);
      if (index > -1) {
        readyQueue.splice(index, 1);
        ko();
      }
    }, READY_TIMEOUT);
    readyQueue.push(entry);
    loadProvider();
  }

  // Explicit rendering (reCAPTCHA v2, hCaptcha, Turnstile): each widget gets
  // its own id and callbacks, so its token lands in its own form.
  function renderWidgets() {
    var api = providerApi();
    if (!api || typeof api.render !== 'function') {
      return;
    }
    forms.forEach(function (form) {
      var state = form.lsCaptcha;
      var target = state.widget ? state.widget.querySelector('.ls-captcha-target') : null;
      if (!target || state.widgetId !== null) {
        return;
      }
      var clear = function () {
        setToken(form, '');
      };
      var params = {
        sitekey: target.getAttribute('data-sitekey'),
        callback: function (token) {
          onToken(form, token);
        },
        'expired-callback': clear,
        'error-callback': clear
      };
      ['theme', 'size', 'action'].forEach(function (name) {
        var value = target.getAttribute('data-' + name);
        if (value) {
          params[name] = value;
        }
      });
      try {
        var id = api.render(target, params);
        state.widgetId = id === undefined ? null : id;
      } catch (e) {
        state.widgetId = null;
      }
    });
  }

  // ---- Forms, widgets & tokens --------------------------------------------

  function zoneForm(zone) {
    if (zone === 'registration') {
      var form = document.getElementById('customer-form');
      if (form) {
        return form;
      }
    }
    var control = document.querySelector('[name="' + ZONE_CONTROL[zone] + '"]');
    return control ? control.closest('form') : null;
  }

  function ensureHidden(form) {
    var input = form.querySelector('input[name="' + TOKEN_FIELD + '"]');
    if (!input) {
      input = document.createElement('input');
      input.type = 'hidden';
      input.name = TOKEN_FIELD;
      form.appendChild(input);
    }
    return input;
  }

  function bind(form, zone, widget) {
    if (!form) {
      return;
    }
    if (!form.lsCaptcha) {
      form.lsCaptcha = { zone: zone, widget: null, widgetId: null, pending: null, passthrough: false };
      forms.push(form);
      ensureHidden(form);
    }
    if (widget && !form.lsCaptcha.widget) {
      form.lsCaptcha.widget = widget;
      widget.lsCaptchaForm = form;
    }
  }

  // A widget rendered away from its form (checkout login) is moved into it;
  // without a target form (e.g. login not offered), it is dropped.
  function relocate(widget, zone) {
    var form = zoneForm(zone);
    if (!form) {
      widget.parentNode.removeChild(widget);
      return null;
    }
    var footer = form.querySelector('.form-footer');
    if (footer) {
      footer.parentNode.insertBefore(widget, footer);
    } else {
      form.appendChild(widget);
    }
    return form;
  }

  function discover() {
    var zones = cfg.zones || [];
    var widgets = document.querySelectorAll('.ls-captcha-widget');
    for (var i = 0; i < widgets.length; i++) {
      var widget = widgets[i];
      var zone = widget.getAttribute('data-ls-captcha-zone');
      if (!ZONE_CONTROL[zone] || zones.indexOf(zone) === -1) {
        continue;
      }
      var form = widget.getAttribute('data-ls-captcha-relocate') === '1'
        ? relocate(widget, zone)
        : (widget.closest('form') || zoneForm(zone));
      bind(form, zone, widget);
    }
    // Zones whose widget is not rendered on this page (single-widget providers).
    zones.forEach(function (zone) {
      if (ZONE_CONTROL[zone]) {
        bind(zoneForm(zone), zone, null);
      }
    });
  }

  function setToken(form, token) {
    ensureHidden(form).value = token || '';
    if (token) {
      clearError(form);
    }
  }

  function onToken(form, token) {
    setToken(form, token);
    var state = form.lsCaptcha;
    if (state.pending) {
      var submitter = state.pending.submitter;
      state.pending = null;
      passthroughSubmit(form, submitter);
    }
  }

  // Native field filled by the provider, looked up in this form's widget first.
  function nativeToken(form) {
    var name = cfg.providerTokenField;
    if (!name || name === TOKEN_FIELD) {
      return '';
    }
    var selector = '[name="' + name + '"]';
    var widget = form.lsCaptcha.widget;
    var el = (widget && widget.querySelector(selector)) || form.querySelector(selector);
    if (!el && EXTRA.singleWidget) {
      el = document.querySelector(selector);
    }
    return el ? el.value : '';
  }

  function syncToken(form) {
    var token = nativeToken(form);
    if (token) {
      ensureHidden(form).value = token;
    }
  }

  // After an AJAX submission (the page stays), the single-use token is spent:
  // clear it and arm a fresh challenge for the next attempt.
  function resetChallenge(form) {
    var state = form.lsCaptcha;
    ensureHidden(form).value = '';
    var api = providerApi();
    if (api && state.widgetId !== null && typeof api.reset === 'function') {
      try {
        api.reset(state.widgetId);
      } catch (e) {
        // widget already gone
      }
    }
    var cap = state.widget ? state.widget.querySelector('cap-widget') : null;
    if (cap && typeof cap.reset === 'function') {
      cap.reset();
    }
  }

  // ---- Messages -----------------------------------------------------------

  function message(form, key) {
    var widget = (form && form.lsCaptcha && form.lsCaptcha.widget) || document.querySelector('.ls-captcha-widget');
    return widget ? (widget.getAttribute('data-ls-captcha-msg-' + key) || '') : '';
  }

  function errorHost(form) {
    return form.lsCaptcha.widget || form;
  }

  function clearError(form) {
    var old = errorHost(form).querySelector('.ls-captcha-error');
    if (old) {
      old.parentNode.removeChild(old);
    }
  }

  function showError(form, key) {
    var text = message(form, key);
    if (!text) {
      return;
    }
    clearError(form);
    var host = errorHost(form);
    var box = document.createElement('div');
    box.className = 'alert alert-danger ls-captcha-error';
    box.setAttribute('role', 'alert');
    box.textContent = text;
    host.insertBefore(box, host.firstChild);
  }

  // ---- Submission ---------------------------------------------------------

  // The control whose name the server checks (submitMessage, ...), used when
  // the browser does not report the clicked button.
  function defaultSubmitter(form) {
    var name = ZONE_CONTROL[form.lsCaptcha.zone];
    return form.querySelector('[type="submit"][name="' + name + '"]');
  }

  // Resubmit after the token was obtained, keeping the clicked button: its
  // name/value is what the server checks (Tools::isSubmit('submitMessage')).
  function passthroughSubmit(form, submitter) {
    var state = form.lsCaptcha;
    if (typeof form.requestSubmit === 'function') {
      state.passthrough = true;
      try {
        if (submitter && submitter.form === form) {
          form.requestSubmit(submitter);
        } else {
          form.requestSubmit();
        }
      } finally {
        // Dispatch is synchronous: reset even if validation blocked it.
        state.passthrough = false;
      }
      return;
    }

    // Legacy browsers: submit() fires no event and posts no button.
    if (submitter && submitter.name) {
      var proxy = form.querySelector('input.ls-captcha-submitter');
      if (!proxy) {
        proxy = document.createElement('input');
        proxy.type = 'hidden';
        proxy.className = 'ls-captcha-submitter';
        form.appendChild(proxy);
      }
      proxy.name = submitter.name;
      proxy.value = submitter.value;
    }
    form.submit();
  }

  function execute(form, submitter) {
    var state = form.lsCaptcha;

    if (!loaded && cfg.loadStrategy === 'consent') {
      if (!hasConsent()) {
        showError(form, 'consent-required');
        return;
      }
      grantConsent();
    }

    clearError(form);
    var unavailable = function () {
      state.pending = null;
      showError(form, 'unavailable');
    };

    whenReady(function () {
      var api = providerApi();
      if (!api) {
        unavailable();
        return;
      }

      if (EXTRA.variant === 'v3') {
        // The action is the zone code, checked server-side.
        api.ready(function () {
          api.execute(cfg.siteKey, { action: state.zone }).then(function (token) {
            if (!token) {
              unavailable();
              return;
            }
            setToken(form, token);
            passthroughSubmit(form, submitter);
          }, unavailable);
        });
        return;
      }

      if (state.widgetId === null) {
        unavailable();
        return;
      }
      state.pending = { submitter: submitter };
      try {
        api.reset(state.widgetId);
        api.execute(state.widgetId);
      } catch (e) {
        unavailable();
      }
    }, unavailable);
  }

  document.addEventListener('submit', function (ev) {
    var form = ev.target;
    var state = form && form.lsCaptcha;
    if (!state) {
      return;
    }

    if (cfg.behavior === 'execute' && !state.passthrough) {
      // Nothing else sees this submission: it is replayed with the token.
      ev.preventDefault();
      ev.stopPropagation();
      execute(form, ev.submitter || defaultSubmitter(form));
      return;
    }

    syncToken(form);
    setTimeout(function () {
      if (ev.defaultPrevented) {
        resetChallenge(form);
      }
    }, 0);
  }, true);

  // Cap ('solve') and FCaptcha ('fcaptcha:verified') deliver their token
  // through an event raised inside the widget.
  function onWidgetEvent(e) {
    var token = e && e.detail && e.detail.token;
    if (!token) {
      return;
    }
    var widget = e.target && e.target.closest ? e.target.closest('.ls-captcha-widget') : null;
    if (widget && widget.lsCaptchaForm) {
      setToken(widget.lsCaptchaForm, token);
      return;
    }
    forms.forEach(function (form) {
      setToken(form, token);
    });
  }
  document.addEventListener('solve', onWidgetEvent, true);
  document.addEventListener('fcaptcha:verified', onWidgetEvent, true);

  // ---- Consent handling ---------------------------------------------------

  function cookiePresent(name) {
    if (!name) {
      return false;
    }
    return document.cookie.split(';').some(function (c) {
      return c.trim().indexOf(name + '=') === 0;
    });
  }

  function hasConsent() {
    return window.lsCaptchaConsentGranted === true || cookiePresent(cfg.consentCookie);
  }

  function removePlaceholders() {
    placeholders.forEach(function (p) {
      if (p && p.parentNode) {
        p.parentNode.removeChild(p);
      }
    });
    placeholders = [];
  }

  function grantConsent() {
    removePlaceholders();
    loadProvider();
  }

  function renderConsentPlaceholders() {
    var widgets = document.querySelectorAll('.ls-captcha-widget');
    for (var i = 0; i < widgets.length; i++) {
      var widget = widgets[i];
      var box = document.createElement('div');
      box.className = 'ls-captcha-consent';

      var notice = widget.getAttribute('data-ls-captcha-msg-consent-notice');
      if (notice) {
        var text = document.createElement('p');
        text.className = 'ls-captcha-consent-notice';
        text.textContent = notice;
        box.appendChild(text);
      }

      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'ls-captcha-consent-button btn btn-primary';
      btn.textContent = widget.getAttribute('data-ls-captcha-msg-consent-button') || '';
      btn.addEventListener('click', grantConsent);
      box.appendChild(btn);

      widget.insertBefore(box, widget.firstChild);
      placeholders.push(box);
    }
  }

  function armConsentListeners() {
    window.addEventListener('lsCaptcha:consent', grantConsent);
    window.lsCaptchaGrantConsent = grantConsent;

    // Poll for a consent cookie set by an external banner (no standard event).
    if (cfg.consentCookie) {
      var ticks = 0;
      var timer = setInterval(function () {
        if (loaded || ++ticks > 40) {
          clearInterval(timer);
          return;
        }
        if (hasConsent()) {
          clearInterval(timer);
          grantConsent();
        }
      }, 1500);
      window.addEventListener('focus', function () {
        if (!loaded && hasConsent()) {
          grantConsent();
        }
      });
    }
  }

  function armInteraction() {
    var handler = function () {
      loadProvider();
    };
    forms.forEach(function (form) {
      var targets = [form];
      var widget = form.lsCaptcha.widget;
      if (widget && !form.contains(widget)) {
        targets.push(widget); // e.g. login widget rendered after its form
      }
      targets.forEach(function (target) {
        target.addEventListener('focusin', handler, { once: true });
        target.addEventListener('pointerdown', handler, { once: true });
      });
    });
  }

  onDomReady(function () {
    discover();
    if (forms.length === 0) {
      return; // no protected form on this page: load nothing
    }

    var strategy = cfg.loadStrategy || 'interaction';

    if (strategy === 'immediate') {
      loadProvider();
      return;
    }

    if (strategy === 'consent') {
      if (hasConsent()) {
        loadProvider();
      } else {
        renderConsentPlaceholders();
        armConsentListeners();
      }
      return;
    }

    // 'interaction' (default)
    armInteraction();
  });
})();
