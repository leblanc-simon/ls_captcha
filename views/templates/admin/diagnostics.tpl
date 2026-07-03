{if isset($ls_warnings) && $ls_warnings|@count > 0}
  {foreach from=$ls_warnings item=w}
    <div class="alert alert-{if $w.type == 'warning'}warning{else}info{/if}">
      {$w.message|escape:'html':'UTF-8'}
    </div>
  {/foreach}
{/if}
