{**
 * FCaptcha widget. The token is delivered through the 'fcaptcha:verified'
 * event handled by ls_captcha.js.
 *
 * data-fcaptcha is the auto-init marker; the optional data-sitekey scopes
 * rate-limit state per site on the instance (empty = shared "default" bucket).
 *}
<div data-fcaptcha
     data-sitekey="{$ls_site_key|escape:'html':'UTF-8'}"
     data-endpoint="{$ls_instance|escape:'html':'UTF-8'}"
     data-theme="{$ls_theme|escape:'html':'UTF-8'}"></div>
