{**
 * Cloudflare Turnstile.
 *}
<div class="ls-captcha-widget ls-captcha-turnstile" data-ls-captcha-zone="{$ls_zone|escape:'html':'UTF-8'}">
  <div class="cf-turnstile"
       data-sitekey="{$ls_site_key|escape:'html':'UTF-8'}"
       data-theme="{$ls_theme|escape:'html':'UTF-8'}"
       data-size="{$ls_size|escape:'html':'UTF-8'}"
       data-action="{$ls_zone|escape:'html':'UTF-8'}"></div>
</div>
