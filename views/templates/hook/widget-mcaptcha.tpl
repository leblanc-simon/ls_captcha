{**
 * mCaptcha. The glue script (ls_captcha.js loads it) fills #mcaptcha__token.
 *}
<div class="ls-captcha-widget ls-captcha-mcaptcha" data-ls-captcha-zone="{$ls_zone|escape:'html':'UTF-8'}">
  <label data-mcaptcha_url="{$ls_widget_url|escape:'html':'UTF-8'}" for="mcaptcha__token" id="mcaptcha__token-label">
    <input type="text" name="mcaptcha__token" id="mcaptcha__token" autocomplete="off" readonly="readonly" />
  </label>
  <div id="mcaptcha__widget-container"></div>
</div>
