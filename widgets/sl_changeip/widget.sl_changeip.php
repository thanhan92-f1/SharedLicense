<?php

/**
 * SharedLicense HostBill Module (Client Widget: Change IP)
 *
 * Copyright (C) 2026 Nguyen Thanh An by Pho Tue SoftWare Solutions JSC
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'class.sharedlicense_widget.php';

class Widget_sl_changeip extends SharedLicenseWidget
{
    protected $widgetfullname = 'Change IP';
    protected $description = 'Allow the client to update the licensed IP address from client area.';

    public function controller($service, &$module, &$smarty, &$params)
    {
        $license = $this->loadLicenseData($service, $module, $smarty, $params);
        $submittedIp = !empty($params['new_ip']) ? trim((string) $params['new_ip']) : '';

        if (!empty($params['make']) && $params['make'] === 'submit' && !empty($params['token_valid'])) {
            if ($module instanceof SharedLicense && $module->LicenseChangeIp($submittedIp)) {
                $this->addInfo('Licensed IP updated successfully.');
            }
            $this->redirectToWidget($service, $params);
        }

        $limitReached = false;
        if (!empty($license)) {
            $maxChanges = isset($license['max_ip_changes']) ? (int) $license['max_ip_changes'] : 0;
            $usedChanges = isset($license['change_ip_count']) ? (int) $license['change_ip_count'] : 0;
            $limitReached = 0 < $maxChanges && $maxChanges <= $usedChanges;
        }

        $smarty->assign('submitted_new_ip', $submittedIp);
        $smarty->assign('change_ip_limit_reached', $limitReached);
    }
}

?>