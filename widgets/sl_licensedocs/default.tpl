{if $license}
    <div class="sharedlicense-widget sharedlicense-widget-docs">
        {if $license.commands}
            <h4 style="margin-top: 0;">Installation Commands</h4>
            {foreach from=$license.commands item=command}
                <div class="well well-sm" style="margin-bottom: 10px;">
                    <div style="font-weight: 600; margin-bottom: 6px;">{$command.title|escape}</div>
                    <pre style="white-space: pre-wrap; word-break: break-word; margin: 0;"><code>{$command.command|escape}</code></pre>
                </div>
            {/foreach}
        {else}
            <div class="alert alert-info" style="margin-bottom: 0;">No installation commands are available for this service yet.</div>
        {/if}
    </div>
{else}
    <div class="alert alert-warning">Unable to load install documentation for this license.</div>
{/if}
