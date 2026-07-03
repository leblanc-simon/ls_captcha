# ls_captcha

Multi-provider captcha for PrestaShop **1.7.8.11 → 9**, without any override.

Licensed under the **GNU AGPL v3** (see [LICENSE](LICENSE)).

Protects (each zone optional, toggled independently):

- customer **registration** (also guest/registration during checkout);
- customer **login** (also checkout login);
- **contact** page (`contactform` module);
- **newsletter** block (`ps_emailsubscription` ≥ 2.6.0).

Supported providers (one global provider at a time):
**Cap** (self-hosted), **Google reCAPTCHA** (v2 checkbox / v2 invisible / v3),
**hCaptcha**, **Cloudflare Turnstile**, **mCaptcha** (self-hosted),
**Friendly Captcha** (v2).

## Configuration

Modules → *LS Captcha* → **Configure**. Pick a provider, fill its fields, enable
the zones you want, then save. Network options: HTTP timeout and *fail open on
network error* (accept submissions when the verification service is unreachable).

No composer install is required: the `LsCaptcha\` namespace is autoloaded by the
main module file.

## Architecture

- `src/Provider/` — one class per captcha type (`ProviderInterface`).
- `src/Zone/` — one class per protected location (`ZoneInterface`).
- `src/ProviderRegistry.php`, `src/ZoneRegistry.php` — the two lists to edit.
- `src/Settings.php` — configuration access (keys prefixed `LS_CAPTCHA_`).
- `src/Http/Client.php` — dependency-free cURL client.
- `src/Admin/ConfigForm.php` — the `getContent()` + HelperForm screen.
- `views/js/ls_captcha.js` — loads the provider script(s) and guarantees the
  token reaches every protected form under the single field `ls_captcha_token`
  (the only field the server reads).

## Add a captcha provider

1. Create `src/Provider/XxxProvider.php` extending `AbstractProvider`
   (implement `getCode`, `getLabel`, `getConfigFields`, `getTokenFieldName`,
   `getFrontScripts`, `callSiteverify`, `interpret`; override
   `getClientBehavior` / `getFrontExtra` / `renderWidget` if needed).
2. Create `views/templates/hook/widget-xxx.tpl`.
3. Add the class to `ProviderRegistry::classes()`.

The admin selector, the generated config fields, script loading and server
verification are then automatic.

## Add a protected zone

1. Create `src/Zone/XxxZone.php` extending `AbstractZone`: implement `getCode`,
   `getLabel`, `getHooks`, and one `hook<Name>()` method per hook.
2. Add the class to `ZoneRegistry::classes()`.
3. For each **new** hook (not already handled by another zone), add the matching
   `hook<Name>()` delegation method in `ls_captcha.php`, then reinstall the
   module (or register the hook on upgrade).

The admin enable switch is then automatic.

## Privacy & GDPR

Configured under **Privacy & loading** on the configuration screen.

**Script loading strategy** — when the third-party captcha script is loaded:

- `immediate` — on page load (legacy behavior).
- `interaction` — on first interaction with the protected form (**default**, privacy-friendly: nothing third-party loads until the visitor engages the form).
- `consent` — only after consent (see below). A placeholder with a "Load the captcha" button is shown until then.

**Consent** (always forced for consent-requiring providers, whatever the strategy). Consent is detected by any of:

- a cookie whose name is set in **Consent cookie name** (to plug in an existing consent banner / CMP);
- the JS flag `window.lsCaptchaConsentGranted = true`;
- the JS call `window.lsCaptchaGrantConsent()`;
- the DOM event `window.dispatchEvent(new Event('lsCaptcha:consent'))`;
- the visitor clicking the "Load the captcha" placeholder button.

**Provider classification:**

- **Consent required** (cookies / profiling / third-country reuse): reCAPTCHA, hCaptcha. Their script is loaded only after consent.
- **Legitimate interest (security)**, no consent needed in most setups: Cap and mCaptcha (self-hosted, first-party), Friendly Captcha (EU endpoint by default), Cloudflare Turnstile.
- **Recommended for the strongest privacy posture:** Cap or mCaptcha (self-hosted, no third party) or Friendly Captcha (EU).

**Data minimization** — **Send visitor IP to the provider** is **off by default**; the IP is forwarded to the verification service only when enabled.

**Content Security Policy** — the exact sources to allow for the selected provider are shown in the configuration screen (diagnostics). To keep dynamically injected scripts working under a strict CSP with a nonce, set the nonce before the front renders:

```php
\ls_captcha::$scriptNonce = $yourCspNonce; // e.g. from a theme/module hook
```

## Notes

- `mCaptcha` loads its glue script from unpkg by default; serve it locally
  (`views/js/vendor/`) for stricter CSP.
- Captcha tokens are single-use; the module memoizes verification per request so
  complementary hooks never consume a token twice.
- A misconfigured provider never locks customers out: verification passes until
  the required fields are filled (a warning is shown in the admin diagnostics).
