{**
 * Google reCAPTCHA. v2 widgets are rendered explicitly by ls_captcha.js (one
 * widget per form); v3 renders no visible widget (token obtained on submit).
 *}
{if $ls_variant == 'v2_checkbox'}
  <div class="ls-captcha-target" data-sitekey="{$ls_site_key|escape:'html':'UTF-8'}" data-theme="{$ls_theme|escape:'html':'UTF-8'}" data-size="normal"></div>
{elseif $ls_variant == 'v2_invisible'}
  <div class="ls-captcha-target" data-sitekey="{$ls_site_key|escape:'html':'UTF-8'}" data-size="invisible"></div>
{/if}
