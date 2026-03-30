<?php

/**
 * SharedLicense HostBill Module (Client Widget: License Details)
 *
 * Copyright (C) 2026 Nguyen Thanh An by Pho Tue SoftWare Solutions JSC
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'class.sharedlicense_widget.php';

class Widget_sl_licensedetails extends SharedLicenseWidget
{
    protected $widgetfullname = 'License Details';
    protected $description = 'Show core license information and allow the client to renew the license.';

    public function controller($service, &$module, &$smarty, &$params)
    {
        if (!empty($params['make']) && $params['make'] === 'renew' && !empty($params['token_valid'])) {
            if ($module instanceof SharedLicense && $module->RenewNow()) {
                $this->addInfo('License renewed successfully.');
            }
            $this->redirectToWidget($service, $params);
        }

        $license = $this->loadLicenseData($service, $module, $smarty, $params, !empty($params['refresh']));
        $smarty->assign('can_renew_license', !empty($license['can_renew']));
    }
}

?>