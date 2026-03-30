{if $details.status == 'Active' || $details.status == 'Suspended' || $details.status == 'Pending' || $details.status == 'Terminated' || $details.status == 'Cancelled'}
    <ul class="accor">
        <li>
            <a href="#">SharedLicense</a>
            <div class="sor" id="sharedlicense-data" style="padding: 15px 0;">
                <div style="text-align: center">
                    <img src="{$template_dir}img/ajax-loading.gif"/>
                </div>
            </div>
        </li>
    </ul>
    <script type="text/javascript" src="{$module_tpldir}license.js"></script>
{/if}
