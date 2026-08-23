{**
 * FCaptcha widget. The token is delivered through the 'fcaptcha:verified'
 * event handled by ls_captcha.js.
 *}
<div class="ls-captcha-widget ls-captcha-fcaptcha" data-ls-captcha-zone="{$ls_zone|escape:'html':'UTF-8'}">
  <div data-fcaptcha="{$ls_site_key|escape:'html':'UTF-8'}"
       data-endpoint="{$ls_instance|escape:'html':'UTF-8'}"
       data-theme="{$ls_theme|escape:'html':'UTF-8'}"></div>
</div>
