{**
 * Cap widget. The token is delivered through the 'solve' event (handled by ls_captcha.js).
 *}
<div class="ls-captcha-widget ls-captcha-cap" data-ls-captcha-zone="{$ls_zone|escape:'html':'UTF-8'}">
  <cap-widget data-cap-api-endpoint="{$ls_instance|escape:'html':'UTF-8'}/{$ls_site_key|escape:'html':'UTF-8'}/"></cap-widget>
</div>
