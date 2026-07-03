{**
 * hCaptcha.
 *}
<div class="ls-captcha-widget ls-captcha-hcaptcha" data-ls-captcha-zone="{$ls_zone|escape:'html':'UTF-8'}">
  <div class="h-captcha"
       data-sitekey="{$ls_site_key|escape:'html':'UTF-8'}"
       data-theme="{$ls_theme|escape:'html':'UTF-8'}"
       data-size="{$ls_size|escape:'html':'UTF-8'}"{if $ls_size == 'invisible'} data-callback="lsCaptchaCb"{/if}></div>
</div>
