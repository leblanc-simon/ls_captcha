{**
 * FCaptcha widget. The token is delivered through the 'fcaptcha:verified'
 * event handled by ls_captcha.js.
 *
 * data-fcaptcha is the marker FCaptcha's auto-init looks for; data-sitekey
 * carries the site key. FCaptcha reads the key as
 * `dataset.sitekey || dataset.fcaptcha`, so the key can also ride on the
 * marker itself, but spelling it out keeps it obvious that the setting is
 * load-bearing. Without it the instance receives an empty site key, which
 * pools rate limit and fingerprint state into one shared bucket instead of
 * scoping it per site.
 *}
<div class="ls-captcha-widget ls-captcha-fcaptcha" data-ls-captcha-zone="{$ls_zone|escape:'html':'UTF-8'}">
  <div data-fcaptcha
       data-sitekey="{$ls_site_key|escape:'html':'UTF-8'}"
       data-endpoint="{$ls_instance|escape:'html':'UTF-8'}"
       data-theme="{$ls_theme|escape:'html':'UTF-8'}"></div>
</div>
