/**
 * ls_captcha front controller.
 *
 * Responsibilities:
 *  - load the active provider script(s) at the right time (immediate /
 *    on-interaction / after-consent), applying a CSP nonce if provided;
 *  - guarantee a hidden field named cfg.tokenField, filled with the token,
 *    inside every protected form (the server always reads that one field);
 *  - for "execute" providers (reCAPTCHA v3 / invisible, hCaptcha invisible),
 *    trigger the challenge on submit and submit once the token is available.
 *
 * Loading strategy (cfg.loadStrategy):
 *  - 'immediate'   : load on page ready.
 *  - 'interaction' : load on first interaction with a protected form.
 *  - 'consent'     : load only after consent (cookie / JS signal / click).
 * Providers that require consent are always forced to 'consent' server-side.
 */
(function () {
  'use strict';

  var cfg = window.lsCaptcha;
  if (!cfg || !cfg.tokenField) {
    return;
  }

  var TOKEN_FIELD = cfg.tokenField;
  var messages = cfg.messages || {};
  var loaded = false;
  var placeholders = [];

  // Zone => name of the submit control identifying its form.
  var ZONE_CONTROL = {
    registration: 'submitCreate',
    login: 'submitLogin',
    contact: 'submitMessage',
    newsletter: 'submitNewsletter'
  };

  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  // ---- Provider script loading --------------------------------------------

  function loadProvider() {
    if (loaded) {
      return;
    }
    loaded = true;
    if (cfg.extra && cfg.extra.capWasmUrl) {
      window.CAP_CUSTOM_WASM_URL = cfg.extra.capWasmUrl;
    }
    (cfg.scripts || []).forEach(function (s) {
      if (!s || !s.url || document.getElementById(s.id)) {
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
      document.head.appendChild(el);
    });
  }

  // ---- Form discovery & token wiring --------------------------------------

  function formByControl(name) {
    var el = document.querySelector('[name="' + name + '"]');
    return el ? el.closest('form') : null;
  }

  function zoneForm(zone) {
    if (zone === 'registration') {
      return document.getElementById('customer-form') || formByControl(ZONE_CONTROL.registration);
    }
    return formByControl(ZONE_CONTROL[zone]);
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

  function fillAll(token) {
    var inputs = document.querySelectorAll('input[name="' + TOKEN_FIELD + '"]');
    for (var i = 0; i < inputs.length; i++) {
      inputs[i].value = token || '';
    }
  }

  function nativeToken() {
    if (!cfg.providerTokenField) {
      return '';
    }
    var el = document.querySelector('[name="' + cfg.providerTokenField + '"]');
    return el ? el.value : '';
  }

  function passthroughSubmit(form) {
    form.dataset.lsPassthrough = '1';
    if (typeof form.requestSubmit === 'function') {
      form.requestSubmit();
    } else {
      form.submit();
    }
  }

  function fillAndSubmit(form, token) {
    ensureHidden(form).value = token || '';
    passthroughSubmit(form);
  }

  function execute(form) {
    var zone = form.dataset.lsZone || 'submit';
    var variant = (cfg.extra && cfg.extra.variant) || '';

    if (cfg.provider === 'recaptcha' && variant === 'v3') {
      if (window.grecaptcha) {
        window.grecaptcha.ready(function () {
          window.grecaptcha.execute(cfg.siteKey, { action: zone }).then(function (t) {
            fillAndSubmit(form, t);
          });
        });
      }
      return;
    }

    if (cfg.provider === 'recaptcha' && variant === 'v2_invisible') {
      window.lsCaptchaCb = function (t) { fillAndSubmit(form, t); };
      if (window.grecaptcha) {
        window.grecaptcha.execute();
      }
      return;
    }

    if (cfg.provider === 'hcaptcha') {
      window.lsCaptchaCb = function (t) { fillAndSubmit(form, t); };
      if (window.hcaptcha) {
        window.hcaptcha.execute();
      }
      return;
    }

    fillAndSubmit(form, nativeToken());
  }

  function setup(form, zone) {
    if (!form || form.dataset.lsSetup === '1') {
      return;
    }
    form.dataset.lsSetup = '1';
    form.dataset.lsZone = zone;
    ensureHidden(form);

    form.addEventListener('submit', function (ev) {
      if (form.dataset.lsPassthrough === '1') {
        form.dataset.lsPassthrough = '';
        return; // token already set: let the submit proceed
      }
      if (cfg.behavior === 'execute') {
        ev.preventDefault();
        execute(form);
        return;
      }
      // auto: the provider filled its native field; mirror it into our field.
      ensureHidden(form).value = nativeToken();
    });
  }

  // ---- Consent handling ----------------------------------------------------

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
      var box = document.createElement('div');
      box.className = 'ls-captcha-consent';

      var text = document.createElement('p');
      text.className = 'ls-captcha-consent-notice';
      text.textContent = messages.consentNotice || 'This form is protected by a captcha.';
      box.appendChild(text);

      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'ls-captcha-consent-button btn btn-primary';
      btn.textContent = messages.consentButton || 'Load the captcha';
      btn.addEventListener('click', grantConsent);
      box.appendChild(btn);

      widgets[i].insertBefore(box, widgets[i].firstChild);
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

  function armInteraction(forms) {
    forms.forEach(function (entry) {
      var handler = function () { loadProvider(); };
      entry.form.addEventListener('focusin', handler, { once: true });
      entry.form.addEventListener('pointerdown', handler, { once: true });
    });
  }

  // Cap delivers its token through a 'solve' event.
  document.addEventListener('solve', function (e) {
    if (e && e.detail && e.detail.token) {
      fillAll(e.detail.token);
    }
  }, true);

  ready(function () {
    var forms = [];
    (cfg.zones || []).forEach(function (zone) {
      if (!ZONE_CONTROL[zone]) {
        return;
      }
      var form = zoneForm(zone);
      if (form) {
        setup(form, zone);
        forms.push({ form: form, zone: zone });
      }
    });

    var strategy = cfg.loadStrategy || 'immediate';

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
    armInteraction(forms);
  });
})();
