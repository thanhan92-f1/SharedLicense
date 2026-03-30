{* SharedLicense HostBill Module
 * Copyright (C) 2026 Nguyen Thanh An by Pho Tue SoftWare Solutions JSC
 * SPDX-License-Identifier: GPL-3.0-or-later
 *}

{if $license}
    <div class="sharedlicense-widget sharedlicense-widget-details">
        <div class="row" style="margin-bottom: 8px;">
            <div class="col-sm-4"><strong>Remote Service ID</strong></div>
            <div class="col-sm-8"><code>{$license.remote_service_id|escape|default:'-'}</code></div>
        </div>
        <div class="row" style="margin-bottom: 8px;">
            <div class="col-sm-4"><strong>License Key</strong></div>
            <div class="col-sm-8"><code>{$license.license_key|escape|default:'-'}</code></div>
        </div>
        <div class="row" style="margin-bottom: 8px;">
            <div class="col-sm-4"><strong>Product</strong></div>
            <div class="col-sm-8">{$license.product_name|escape|default:'-'}</div>
        </div>
        <div class="row" style="margin-bottom: 8px;">
            <div class="col-sm-4"><strong>Licensed IP</strong></div>
            <div class="col-sm-8"><code>{$license.license_ip|escape|default:$license.nat_ip|default:'-'}</code></div>
        </div>
        <div class="row" style="margin-bottom: 8px;">
            <div class="col-sm-4"><strong>Status</strong></div>
            <div class="col-sm-8">
                <span class="label label-{$license.status_class|default:'default'}">{$license.status|escape|default:'Pending'}</span>
            </div>
        </div>
        <div class="row" style="margin-bottom: 8px;">
            <div class="col-sm-4"><strong>Renew Date</strong></div>
            <div class="col-sm-8">{$license.renew_date|escape|default:'-'}</div>
        </div>
        <div class="row" style="margin-bottom: 8px;">
            <div class="col-sm-4"><strong>Auto Renew</strong></div>
            <div class="col-sm-8">{if $license.auto_renew}Enabled{else}Disabled{/if}</div>
        </div>
        <div class="row">
            <div class="col-sm-4"><strong>Last API Message</strong></div>
            <div class="col-sm-8">{$license.last_message|escape|default:'-'}</div>
        </div>

        {if $can_renew_license}
            <form method="post" action="{$widget_url}" style="margin-top: 15px;">
                <input type="hidden" name="make" value="renew" />
                {securitytoken}
                <button type="submit" class="btn btn-success btn-sm">Renew Now</button>
            </form>
        {/if}
    </div>
{else}
    <div class="alert alert-warning">Unable to load license details.</div>
{/if}
