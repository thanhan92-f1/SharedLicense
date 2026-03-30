<?php

abstract class SharedLicenseWidget extends HostingWidget
{
    protected $info = ['appendtpl' => 'default.tpl', 'options' => 3];

    protected function buildWidgetUrl($service, $params)
    {
        $url = '?cmd=clientarea&action=services&service=' . (int) $service['id'] . '&widget=' . $this->getWidgetName();
        if (!empty($params['wid'])) {
            $url .= '&wid=' . $params['wid'];
        }
        return $url;
    }

    protected function redirectToWidget($service, $params, $suffix = '')
    {
        call_user_func(['Utilities', 'redirect'], $this->buildWidgetUrl($service, $params) . $suffix);
    }

    protected function getServiceExtraDetails($service)
    {
        if (empty($service['extra_details'])) {
            return [];
        }

        if (is_array($service['extra_details'])) {
            return $service['extra_details'];
        }

        if (function_exists('unserialize7')) {
            $details = call_user_func('unserialize7', $service['extra_details']);
        } else {
            $details = @unserialize($service['extra_details']);
        }

        return is_array($details) ? $details : [];
    }

    protected function loadLicenseData($service, &$module, &$smarty, $params, $refresh = false)
    {
        $widgetUrl = $this->buildWidgetUrl($service, $params);
        $smarty->assign('widget_url', $widgetUrl);
        $smarty->assign('service', $service);

        if (!$module instanceof SharedLicense) {
            return [];
        }

        try {
            $module->prepareDetails($this->getServiceExtraDetails($service));
            $license = $module->LicenseDetails($refresh);
            $smarty->assign('license', $license);
            return $license;
        } catch (Exception $exception) {
            $this->addError($exception->getMessage());
        }

        $smarty->assign('license', []);
        return [];
    }

    public function doesApply(&$module)
    {
        return $module instanceof SharedLicense && parent::doesApply($module);
    }
}

?>