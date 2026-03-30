<?php

/**
 * SharedLicense HostBill Module (Client Widget: License Docs)
 *
 * Copyright (C) 2026 Nguyen Thanh An by Pho Tue SoftWare Solutions JSC
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'class.sharedlicense_widget.php';

class Widget_sl_licensedocs extends SharedLicenseWidget
{
    protected $widgetfullname = 'License Docs';
    protected $description = 'Display installation commands returned by the remote license API.';

    public function controller($service, &$module, &$smarty, &$params)
    {
        $license = $this->loadLicenseData($service, $module, $smarty, $params, !empty($params['refresh']));
        $smarty->assign('commands_available', !empty($license['commands_available']));
    }
}

?>