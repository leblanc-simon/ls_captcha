{**
 * Shared wrapper of every provider widget. ls_captcha.js finds the zone and
 * its translated wordings here (data- attributes), then the provider template
 * is included with the variables of this render only.
 *}
<div class="ls-captcha-widget ls-captcha-{$ls_provider|escape:'html':'UTF-8'}"
     data-ls-captcha-zone="{$ls_zone|escape:'html':'UTF-8'}"
     {if $ls_relocate}data-ls-captcha-relocate="1"{/if}
     data-ls-captcha-msg-consent-notice="{$ls_messages.consent_notice|escape:'html':'UTF-8'}"
     data-ls-captcha-msg-consent-button="{$ls_messages.consent_button|escape:'html':'UTF-8'}"
     data-ls-captcha-msg-consent-required="{$ls_messages.consent_required|escape:'html':'UTF-8'}"
     data-ls-captcha-msg-unavailable="{$ls_messages.unavailable|escape:'html':'UTF-8'}">
  {include file=$ls_inner_template}
</div>
