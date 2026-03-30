{if $license}
    <div class="sharedlicense-wrap">
        <div style="margin: 0 0 15px;">
            <a href="#refresh" class="btn btn-default btn-sm sl-refresh-details">Refresh Data</a>
            {if $license.can_change_ip}
                <a href="#change-ip" class="btn btn-default btn-sm sl-change-ip">Change IP</a>
            {/if}
            <a href="#reset-ip-count" class="btn btn-warning btn-sm sl-reset-ip-count">Reset IP Counter</a>
            {if $license.can_renew}
                <a href="#renew" class="btn btn-success btn-sm sl-renew">Renew Now</a>
            {/if}
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="panel panel-default">
                    <div class="panel-heading"><strong>License Overview</strong></div>
                    <div class="panel-body">
                        <div class="sharedlicense-grid">
                            <div class="row lic-row"><div class="col-xs-5 lic-prop-title">Remote Service ID</div><div class="col-xs-7 lic-prop-value"><code>{$license.remote_service_id|escape|default:'-'}</code></div></div>
                            <div class="row lic-row"><div class="col-xs-5 lic-prop-title">License Key</div><div class="col-xs-7 lic-prop-value"><code>{$license.license_key|escape|default:'-'}</code></div></div>
                            <div class="row lic-row"><div class="col-xs-5 lic-prop-title">Product</div><div class="col-xs-7 lic-prop-value">{$license.product_name|escape|default:'-'}</div></div>
                            <div class="row lic-row"><div class="col-xs-5 lic-prop-title">Licensed IP</div><div class="col-xs-7 lic-prop-value"><code>{$license.license_ip|escape|default:$license.nat_ip|default:'-'}</code></div></div>
                            <div class="row lic-row"><div class="col-xs-5 lic-prop-title">Status</div><div class="col-xs-7 lic-prop-value"><span class="label label-{$license.status_class|default:'default'}">{$license.status|escape|default:'Pending'}</span></div></div>
                            <div class="row lic-row"><div class="col-xs-5 lic-prop-title">Renew Date</div><div class="col-xs-7 lic-prop-value">{$license.renew_date|escape|default:'-'}</div></div>
                            <div class="row lic-row"><div class="col-xs-5 lic-prop-title">Auto Renew</div><div class="col-xs-7 lic-prop-value">{if $license.auto_renew}<span class="label label-success">Enabled</span>{else}<span class="label label-default">Disabled</span>{/if}</div></div>
                            <div class="row lic-row"><div class="col-xs-5 lic-prop-title">IP Changes Used</div><div class="col-xs-7 lic-prop-value">{$license.change_ip_count|default:0} / {if $license.max_ip_changes == 0}Unlimited{else}{$license.max_ip_changes|default:0}{/if}</div></div>
                            <div class="row lic-row"><div class="col-xs-5 lic-prop-title">Remote IP Limit</div><div class="col-xs-7 lic-prop-value">{$license.remote_change_ip_limit|default:0}</div></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="panel panel-default">
                    <div class="panel-heading"><strong>Admin Debug Info</strong></div>
                    <div class="panel-body">
                        <div class="sharedlicense-grid">
                            <div class="row lic-row"><div class="col-xs-5 lic-prop-title">Last Action</div><div class="col-xs-7 lic-prop-value">{$license.last_action|escape|default:'-'}</div></div>
                            <div class="row lic-row"><div class="col-xs-5 lic-prop-title">Last Remote Action</div><div class="col-xs-7 lic-prop-value">{$license.last_remote_action|escape|default:'-'}</div></div>
                            <div class="row lic-row"><div class="col-xs-5 lic-prop-title">Registration Date</div><div class="col-xs-7 lic-prop-value">{$license.reg_date|escape|default:'-'}</div></div>
                            <div class="row lic-row"><div class="col-xs-5 lic-prop-title">Suspended Reason</div><div class="col-xs-7 lic-prop-value">{$license.suspended_reason|escape|default:'-'}</div></div>
                            <div class="row lic-row"><div class="col-xs-5 lic-prop-title">Last Message</div><div class="col-xs-7 lic-prop-value">{$license.last_message|escape|default:'-'}</div></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {if $license.commands}
            <h4>Installation Commands</h4>
            {foreach from=$license.commands item=command}
                <div class="well well-sm sl-command-card" style="margin-bottom: 10px;">
                    <div style="font-weight: 600; margin-bottom: 5px;">
                        {$command.title|escape}
                        <a href="#copy" class="btn btn-xs btn-default pull-right sl-copy-command">Copy</a>
                    </div>
                    <pre style="white-space: pre-wrap; word-break: break-word; margin: 0;"><code>{$command.command|escape}</code></pre>
                    <div class="text-success sl-copy-feedback" style="display:none; margin-top: 6px;">Command copied.</div>
                </div>
            {/foreach}
        {/if}
    </div>

    <div id="sharedlicense-changeip-form" style="display:none;" bootbox
         data-title="Change license IP"
         data-btntitle="Change IP"
         data-formaction="?cmd=sharedlicense&action=changeip&id={$details.id}">
        {if $license.can_change_ip}
            <div class="form-group">
                <label>New IP Address</label>
                <input type="text" name="newip" class="form-control" value=""/>
            </div>
        {else}
            <p>This product does not support remote IP change.</p>
        {/if}
        {securitytoken}
    </div>

    <div id="sharedlicense-renew-form" style="display:none;" bootbox
         data-title="Renew license"
         data-btntitle="Renew now"
         data-btnclass="btn-success"
         data-formaction="?cmd=sharedlicense&action=renew&id={$details.id}">
        <p>This will call the remote <code>renew</code> action for the current service.</p>
        {securitytoken}
    </div>

    <div id="sharedlicense-reset-ip-count-form" style="display:none;" bootbox
         data-title="Reset IP change counter"
         data-btntitle="Reset counter"
         data-btnclass="btn-warning"
         data-formaction="?cmd=sharedlicense&action=resetipcount&id={$details.id}">
        <p>This only resets the HostBill-side <code>change_ip_count</code> value for this service. It does not call the remote API.</p>
        {securitytoken}
    </div>
{else}
    Failed to load license data.
{/if}
