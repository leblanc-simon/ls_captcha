{**
 * Google reCAPTCHA. v3 renders no visible widget (token obtained on submit).
 *}
<div class="ls-captcha-widget ls-captcha-recaptcha" data-ls-captcha-zone="{$ls_zone|escape:'html':'UTF-8'}">
{if $ls_variant == 'v2_checkbox'}
  <div class="g-recaptcha" data-sitekey="{$ls_site_key|escape:'html':'UTF-8'}" data-theme="{$ls_theme|escape:'html':'UTF-8'}"></div>
{elseif $ls_variant == 'v2_invisible'}
  <div class="g-recaptcha" data-sitekey="{$ls_site_key|escape:'html':'UTF-8'}" data-size="invisible" data-callback="lsCaptchaCb"></div>
{/if}
</div>
