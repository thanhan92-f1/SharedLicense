<?php

/**
 * SharedLicense HostBill Module
 *
 * Copyright (C) 2026 Nguyen Thanh An by Pho Tue SoftWare Solutions JSC
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

require_once __DIR__ . DIRECTORY_SEPARATOR . 'class.api.php';

class SharedLicense extends LicenseModule
{
    protected $version = '1.0.1';
    protected $_repository = 'hosting_sharedlicense';
    protected $description = 'SharedLicense provisioning module for HostBill.';
    protected $modname = 'SharedLicense';

    protected $serverFields = [
        self::CONNECTION_FIELD_HOSTNAME => true,
        self::CONNECTION_FIELD_IPADDRESS => false,
        self::CONNECTION_FIELD_USERNAME => true,
        self::CONNECTION_FIELD_PASSWORD => false,
        self::CONNECTION_FIELD_INPUT1 => false,
        self::CONNECTION_FIELD_INPUT2 => false,
        self::CONNECTION_FIELD_TEXTAREA => false,
        self::CONNECTION_FIELD_CHECKBOX => false,
        self::CONNECTION_FIELD_NAMESERVERS => false,
        self::CONNECTION_FIELD_MAXACCOUNTS => false,
        self::CONNECTION_FIELD_STATUSURL => false,
    ];

    protected $serverFieldsDescription = [
        self::CONNECTION_FIELD_HOSTNAME => 'API Base URL',
        self::CONNECTION_FIELD_USERNAME => 'Bearer Token',
    ];

    protected $options = [
        'product' => [
            'name' => 'Product',
            'value' => false,
            'type' => 'loadable',
            'default' => 'loadPackageProducts',
            'variable' => 'product',
        ],
        'ip' => [
            'name' => 'Licensed IP',
            'value' => false,
            'type' => 'input',
            'forms' => 'input',
            'variable' => 'ip',
            'description' => 'Licensed IP from Client Area or Admin Area fields, used in the order payload.',
        ],
        'new_ip' => [
            'name' => 'New IP Address',
            'value' => false,
            'type' => 'input',
            'forms' => 'input',
            'variable' => 'new_ip',
        ],
        'max_ip_changes' => [
            'name' => 'Max IP changes',
            'value' => '0',
            'type' => 'input',
            'variable' => 'max_ip_changes',
            'description' => 'Set 0 to disable the HostBill-side limit.',
        ],
        'suspend_reason' => [
            'name' => 'Suspend reason',
            'value' => 'Suspended by HostBill',
            'type' => 'input',
            'variable' => 'suspend_reason',
        ],
    ];

    protected $details = [
        'remote_service_id' => ['name' => 'Remote Service ID', 'type' => 'input'],
        'license_key' => ['name' => 'License Key', 'type' => 'input'],
        'license_ip' => ['name' => 'Licensed IP', 'type' => 'input'],
        'nat_ip' => ['name' => 'NAT IP', 'type' => 'hidden'],
        'hostname' => ['name' => 'Hostname', 'type' => 'hidden'],
        'kernel' => ['name' => 'Kernel', 'type' => 'hidden'],
        'product_id' => ['name' => 'Product ID', 'type' => 'hidden'],
        'product_name' => ['name' => 'Product Name', 'type' => 'input'],
        'status' => ['name' => 'License Status', 'type' => 'select', 'default' => ['Pending', 'Active', 'Suspended', 'Terminated', 'Cancelled', 'Error']],
        'message' => ['name' => 'Last API Message', 'type' => 'hidden'],
        'change_ip_count' => ['name' => 'IP Change Count', 'type' => 'hidden', 'value' => '0'],
        'change_ip_limit' => ['name' => 'IP Change Limit', 'type' => 'hidden', 'value' => '0'],
        'auto_renew' => ['name' => 'Auto Renew', 'type' => 'hidden', 'value' => '0'],
        'renew_date' => ['name' => 'Renew Date', 'type' => 'hidden'],
        'reg_date' => ['name' => 'Registration Date', 'type' => 'hidden'],
        'suspended_reason' => ['name' => 'Suspended Reason', 'type' => 'hidden'],
        'commands_json' => ['name' => 'Commands JSON', 'type' => 'hidden'],
        'product_logo' => ['name' => 'Product Logo', 'type' => 'hidden'],
        'last_action' => ['name' => 'Last Action', 'type' => 'hidden'],
        'last_remote_action' => ['name' => 'Last Remote Action', 'type' => 'hidden'],
    ];

    protected $commands = ['RenewNow', 'LicenseChangeIp'];
    protected $api;
    protected $connect_data = [];
    protected $products;
    protected $productConfigOptions = [];

    const DEFAULT_API_BASE_URL = 'https://sharedlicense.com/client/modules/addons/LicReseller/api';
    const WIDGET_OPTIONS_DEFAULT = 155;
    const WIDGET_OPTIONS_ACTION = 27;
    const CUSTOM_FIELD_OPTION_PREFIX = 'sharedlicense_cf_';

    public function install()
    {
        $this->upgrade('0.0.0');
    }

    public function upgrade($old)
    {
        if (version_compare((string) $old, '1.0.0', '<')) {
            $this->registerClientWidgets();
        }
    }

    public function connect($server)
    {
        $this->connect_data = is_array($server) ? $server : [];
        $baseUrl = $this->serverValue(['host', 'hostname'], self::DEFAULT_API_BASE_URL);
        $token = $this->serverValue(['username', 'user', 'token'], '');
        $this->api = new Hosting\SharedLicense\Api($token, $baseUrl);
    }

    public function Prepare($data)
    {
        $productId = '';
        if (is_array($data) && !empty($data['product'])) {
            $productId = (string) $data['product'];
        }
        if ($productId === '') {
            $productId = (string) $this->resourceOrDefault('product', '');
        }
        $this->applyDynamicProductOptions($productId);
    }

    public function api()
    {
        if ($this->api instanceof Hosting\SharedLicense\Api) {
            return $this->api;
        }
        throw new RuntimeException('Api connection was not initialized, call "connect" first');
    }

    public function testConnection($log = NULL)
    {
        try {
            $account = $this->api()->account();
            if (is_callable($log)) {
                $level = class_exists('Monolog\\Logger') ? constant('Monolog\\Logger::INFO') : 200;
                $log($level, 'Connected to SharedLicense API account: ' . (!empty($account['email']) ? $account['email'] : 'ok'));
            }
            return true;
        } catch (Exception $ex) {
            $this->addError($ex->getMessage());
        }
        return false;
    }

    public function setAccountConfig($config)
    {
        parent::setAccountConfig($config);
        $this->applyDynamicProductOptions((string) $this->resourceOrDefault('product', ''));
        if (empty($this->account_config['ip'])) {
            $ip = $this->detailValue('license_ip', '');
            if ($ip === '' && !empty($this->account_details['domain']) && filter_var($this->account_details['domain'], FILTER_VALIDATE_IP)) {
                $ip = $this->account_details['domain'];
            }
            $this->details['ip'] = ['name' => 'License IP', 'value' => $ip, 'type' => 'input', 'variable' => 'ip'];
        }
    }

    public function Create($addon = false)
    {
        try {
            $product = $this->selectedProduct();
            $payload = $this->buildOrderPayload($product);
            $response = $this->api()->orderProduct($product['id'], $payload);
            $serviceId = $this->extractServiceId($response);
            if (!$serviceId) {
                throw new RuntimeException('Remote API did not return serviceId');
            }

            $this->details['remote_service_id']['value'] = (string) $serviceId;
            $this->details['product_id']['value'] = (string) $product['id'];
            $this->details['product_name']['value'] = $product['name'];
            $this->details['product_logo']['value'] = !empty($product['logoUrl']) ? (string) $product['logoUrl'] : '';
            $this->details['status']['value'] = 'Pending';
            $this->details['last_action']['value'] = 'Create';
            $this->details['last_remote_action']['value'] = 'order';
            $this->details['message']['value'] = 'Service ordered successfully';
            $this->persistDetails();

            $this->syncRemoteLicenseDetails($serviceId, true);
            return true;
        } catch (Exception $ex) {
            $this->details['status']['value'] = 'Error';
            $this->details['message']['value'] = $ex->getMessage();
            $this->persistDetails();
            $this->addError($ex->getMessage());
        }
        return false;
    }

    public function Suspend()
    {
        return $this->changeRemoteStatus('Suspend', 'suspend', 'Suspended', $this->resourceOrDefault('suspend_reason', 'Suspended by HostBill'));
    }

    public function Unsuspend()
    {
        return $this->changeRemoteStatus('Unsuspend', 'unsuspend', 'Active');
    }

    public function Terminate()
    {
        return $this->changeRemoteStatus('Terminate', 'cancel', 'Cancelled');
    }

    public function Renewal()
    {
        try {
            $licenseId = $this->remoteServiceId(true);
            $this->api()->renewLicense($licenseId);
            $this->details['last_action']['value'] = 'Renew';
            $this->details['last_remote_action']['value'] = 'renew';
            $this->details['message']['value'] = 'License renewed successfully';
            $this->syncRemoteLicenseDetails($licenseId, false);
            return true;
        } catch (Exception $ex) {
            $this->details['message']['value'] = $ex->getMessage();
            $this->persistDetails();
            $this->addError($ex->getMessage());
        }
        return false;
    }

    public function RenewNow()
    {
        return $this->Renewal();
    }

    public function ResetChangeIpCount()
    {
        $this->details['change_ip_count']['value'] = '0';
        $this->details['new_ip']['value'] = '';
        $this->details['message']['value'] = 'IP change counter was reset from HostBill admin area';
        $this->details['last_action']['value'] = 'Reset IP Counter';
        $this->details['last_remote_action']['value'] = 'Local Reset';
        $this->persistDetails();
        return true;
    }

    public function LicenseChangeIp($newIp = null, $oldIp = null)
    {
        $newIp = $this->resolveChangeIpNewIp($newIp);
        if (!filter_var($newIp, FILTER_VALIDATE_IP)) {
            $this->addError('New IP address is invalid');
            return false;
        }

        $currentCount = (int) $this->detailValue('change_ip_count', 0);
        $maxChanges = (int) $this->resourceOrDefault('max_ip_changes', 0);
        if (0 < $maxChanges && $currentCount >= $maxChanges) {
            $this->addError('This license has already used the maximum number of IP changes allowed by HostBill');
            return false;
        }

        try {
            $licenseId = $this->remoteServiceId(true);
            $this->api()->changeIp($licenseId, $newIp);
            $this->details['last_remote_action']['value'] = 'change-ip';
            $this->details['change_ip_count']['value'] = (string) ($currentCount + 1);
            $this->details['last_action']['value'] = 'Change IP';
            $this->details['message']['value'] = 'Licensed IP updated successfully';
            $this->persistCurrentIp($newIp);
            $this->syncRemoteLicenseDetails($licenseId, false);
            return true;
        } catch (Exception $ex) {
            $this->details['message']['value'] = $ex->getMessage();
            $this->persistDetails();
            $this->addError($ex->getMessage());
        }
        return false;
    }

    public function LicenseId()
    {
        return $this->detailValue('remote_service_id', '');
    }

    public function LicenseDetails($refresh = false)
    {
        $product = [];
        try {
            $product = $this->selectedProduct(!$refresh ? false : true);
        } catch (Exception $ignored) {
        }

        $licenseId = $this->detailValue('remote_service_id', '');
        if ($refresh && $licenseId !== '') {
            try {
                $this->syncRemoteLicenseDetails($licenseId, false);
            } catch (Exception $ignored) {
            }
        }

        $commands = $this->commandsList();
        $status = $this->detailValue('status', 'Pending');

        return [
            'remote_service_id' => $licenseId,
            'license_key' => $this->detailValue('license_key', ''),
            'license_ip' => $this->currentIp(false),
            'nat_ip' => $this->detailValue('nat_ip', ''),
            'hostname' => $this->detailValue('hostname', ''),
            'kernel' => $this->detailValue('kernel', ''),
            'product_id' => !empty($product['id']) ? $product['id'] : $this->detailValue('product_id', ''),
            'product_name' => !empty($product['name']) ? $product['name'] : $this->detailValue('product_name', ''),
            'product_logo' => !empty($product['logoUrl']) ? $product['logoUrl'] : $this->detailValue('product_logo', ''),
            'status' => $status,
            'status_class' => $this->statusClass($status),
            'change_ip_count' => (int) $this->detailValue('change_ip_count', 0),
            'max_ip_changes' => (int) $this->resourceOrDefault('max_ip_changes', $this->detailValue('change_ip_limit', 0)),
            'remote_change_ip_limit' => (int) $this->detailValue('change_ip_limit', 0),
            'last_message' => $this->detailValue('message', ''),
            'last_action' => $this->detailValue('last_action', ''),
            'last_remote_action' => $this->detailValue('last_remote_action', ''),
            'renew_date' => $this->detailValue('renew_date', ''),
            'reg_date' => $this->detailValue('reg_date', ''),
            'auto_renew' => $this->detailValue('auto_renew', '0') === '1',
            'suspended_reason' => $this->detailValue('suspended_reason', ''),
            'commands' => $commands,
            'commands_available' => !empty($commands),
            'can_change_ip' => $licenseId !== '',
            'can_renew' => $licenseId !== '',
        ];
    }

    public function loadPackageProducts()
    {
        $ret = [];
        try {
            foreach ($this->products() as $id => $product) {
                $ret[] = [(string) $id, $product['name']];
            }
        } catch (Exception $ex) {
            $this->addError($ex->getMessage());
        }
        return $ret;
    }

    protected function changeRemoteStatus($actionLabel, $remoteAction, $status, $reason = '')
    {
        try {
            $licenseId = $this->remoteServiceId(true);
            switch ($remoteAction) {
                case 'suspend':
                    $this->api()->suspendLicense($licenseId, $reason);
                    break;
                case 'unsuspend':
                    $this->api()->unsuspendLicense($licenseId);
                    break;
                case 'cancel':
                    $this->api()->cancelLicense($licenseId);
                    break;
                default:
                    throw new RuntimeException('Unsupported remote action');
            }

            $this->details['status']['value'] = $status;
            $this->details['last_action']['value'] = $actionLabel;
            $this->details['last_remote_action']['value'] = $remoteAction;
            $this->details['message']['value'] = 'License ' . strtolower($actionLabel) . 'd successfully';
            $this->syncRemoteLicenseDetails($licenseId, false, false);
            $this->persistDetails();
            return true;
        } catch (Exception $ex) {
            $this->details['message']['value'] = $ex->getMessage();
            $this->persistDetails();
            $this->addError($ex->getMessage());
        }
        return false;
    }

    protected function selectedProduct($forceReload = false)
    {
        $productId = $this->detailValue('product_id', '');
        if ($productId === '') {
            $productId = (string) $this->resourceOrDefault('product', '');
        }

        $products = $this->products($forceReload);
        if ($productId === '' || !isset($products[$productId])) {
            throw new RuntimeException('Selected product does not exist in SharedLicense product catalog');
        }

        return $products[$productId];
    }

    protected function products($forceReload = false)
    {
        if (!$forceReload && is_array($this->products)) {
            return $this->products;
        }

        if (!$forceReload) {
            $cached = $this->loadProductsCache();
            if (!empty($cached)) {
                $this->products = $cached;
                return $this->products;
            }
        }

        try {
            $response = $this->api()->products();
            $this->products = $this->normalizeProductsResponse($response);
            $this->cacheProducts($response);
            return $this->products;
        } catch (Exception $ex) {
            $cached = $this->loadProductsCache();
            if (!empty($cached)) {
                $this->products = $cached;
                return $this->products;
            }
            throw $ex;
        }
    }

    protected function normalizeProductsResponse(array $response)
    {
        $products = [];
        $list = isset($response['products']) ? $response['products'] : $response;
        if (!is_array($list)) {
            throw new RuntimeException('Unexpected product catalog response from remote API');
        }

        $this->productConfigOptions = [];
        if (!empty($response['configOptions']) && is_array($response['configOptions'])) {
            foreach ($response['configOptions'] as $optionId => $option) {
                $option = is_array($option) ? $option : [];
                $pids = !empty($option['pids']) && is_array($option['pids']) ? $option['pids'] : [];
                foreach ($pids as $pid) {
                    $this->productConfigOptions[(string) $pid][] = $option;
                }
            }
        }

        foreach ($list as $id => $row) {
            if (!is_array($row)) {
                continue;
            }
            $row['id'] = isset($row['id']) ? (string) $row['id'] : (string) $id;
            $row['name'] = !empty($row['name']) ? (string) $row['name'] : ('Product #' . $row['id']);
            $row['logoUrl'] = !empty($row['logoUrl']) ? (string) $row['logoUrl'] : '';
            $row['customfields'] = $this->normalizeProductCustomFields(!empty($row['customfields']) && is_array($row['customfields']) ? $row['customfields'] : []);
            $row['configOptions'] = !empty($this->productConfigOptions[$row['id']]) ? $this->productConfigOptions[$row['id']] : [];
            $products[$row['id']] = $row;
        }

        if (empty($products)) {
            throw new RuntimeException('No products were returned by the SharedLicense API');
        }

        return $products;
    }

    protected function loadProductsCache()
    {
        $file = __DIR__ . DIRECTORY_SEPARATOR . 'products.json';
        if (!file_exists($file)) {
            return [];
        }
        $raw = file_get_contents($file);
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded) || empty($decoded)) {
            return [];
        }
        return $this->normalizeProductsResponse($decoded);
    }

    protected function cacheProducts(array $response)
    {
        $file = __DIR__ . DIRECTORY_SEPARATOR . 'products.json';
        $tmp = $file . '.tmp';
        $json = json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return;
        }
        if (@file_put_contents($tmp, $json) !== false) {
            @rename($tmp, $file);
        }
    }

    protected function buildOrderPayload(array $product)
    {
        $payload = [
            'customfields' => (object) $this->buildCustomFieldValues($product),
            'configoptions' => (object) $this->buildConfigOptionValues($product),
        ];
        return $payload;
    }

    protected function buildCustomFieldValues(array $product)
    {
        $values = [];
        foreach ($product['customfields'] as $fieldId => $field) {
            if (!is_array($field)) {
                continue;
            }
            $fieldId = isset($field['id']) ? (string) $field['id'] : (string) $fieldId;
            $value = $this->configuredCustomFieldValue($field);
            if ($value === '') {
                $value = $this->guessCustomFieldValue($field);
            }
            if ($value === '' && !empty($field['required']) && !$this->fieldAllowsEmpty($field)) {
                throw new RuntimeException('Required custom field is missing value: ' . (!empty($field['name']) ? $field['name'] : $fieldId));
            }
            if ($value !== '') {
                $values[$fieldId] = $value;
            }
        }
        return $values;
    }

    protected function buildConfigOptionValues(array $product)
    {
        $values = [];
        foreach ($product['configOptions'] as $option) {
            if (!is_array($option) || empty($option['id'])) {
                continue;
            }
            $optionId = (string) $option['id'];
            $default = isset($option['default']) ? (string) $option['default'] : '';
            if ($default !== '') {
                $values[$optionId] = $default;
                continue;
            }
            if (!empty($option['options']) && is_array($option['options'])) {
                foreach ($option['options'] as $item) {
                    if (is_array($item) && isset($item['id'])) {
                        $values[$optionId] = (string) $item['id'];
                        break;
                    }
                }
            }
        }
        return $values;
    }

    protected function applyDynamicProductOptions($productId)
    {
        $productId = trim((string) $productId);
        if ($productId === '') {
            return;
        }

        try {
            $products = $this->products();
        } catch (Exception $ignored) {
            return;
        }

        if (empty($products[$productId]) || !is_array($products[$productId])) {
            return;
        }

        foreach ($products[$productId]['customfields'] as $fieldId => $field) {
            if (!is_array($field)) {
                continue;
            }

            $fieldId = isset($field['id']) ? (string) $field['id'] : (string) $fieldId;
            $optionKey = $this->customFieldOptionKey($fieldId);
            $currentValue = isset($this->account_config[$optionKey]['value']) ? $this->account_config[$optionKey]['value'] : false;

            $this->options[$optionKey] = [
                'name' => 'Custom field #' . $fieldId . (!empty($field['name']) ? ' - ' . (string) $field['name'] : ''),
                'value' => $currentValue,
                'type' => 'input',
                'forms' => 'input',
                'variable' => $optionKey,
                'description' => !empty($field['description']) ? (string) $field['description'] : 'Remote product field ID: ' . $fieldId,
            ];
        }
    }

    protected function customFieldOptionKey($fieldId)
    {
        return self::CUSTOM_FIELD_OPTION_PREFIX . trim((string) $fieldId);
    }

    protected function configuredCustomFieldValue(array $field)
    {
        $fieldId = isset($field['id']) ? (string) $field['id'] : '';
        if ($fieldId === '') {
            return '';
        }

        $optionKey = $this->customFieldOptionKey($fieldId);
        $value = trim((string) $this->resourceOrDefault($optionKey, ''));
        if ($value !== '') {
            return $value;
        }

        if (!empty($this->account_config[$optionKey]['value'])) {
            return trim((string) $this->account_config[$optionKey]['value']);
        }

        return '';
    }

    protected function guessCustomFieldValue(array $field)
    {
        $name = !empty($field['name']) ? strtolower(trim((string) $field['name'])) : '';
        $ip = $this->currentIp(false);
        $hostname = !empty($this->account_details['domain']) ? trim((string) $this->account_details['domain']) : '';
        $serviceId = !empty($this->account_details['id']) ? (string) $this->account_details['id'] : '';
        $role = !empty($field['_hb_role']) ? $field['_hb_role'] : $this->detectCustomFieldRole($field);

        if ($role === 'ip') {
            return $ip;
        }
        if ($role === 'hostname') {
            return $hostname;
        }
        if ($role === 'license_key') {
            return $this->detailValue('license_key', '') !== '' ? $this->detailValue('license_key', '') : ('HB-' . $serviceId);
        }
        if (strpos($name, 'ip') !== false) {
            return $ip;
        }
        if (!empty($field['options']) && is_array($field['options'])) {
            return (string) reset($field['options']);
        }
        return '';
    }

    protected function normalizeProductCustomFields(array $fields)
    {
        $normalized = [];
        foreach ($fields as $fieldId => $field) {
            if (!is_array($field)) {
                continue;
            }
            $field['id'] = isset($field['id']) ? (string) $field['id'] : (string) $fieldId;
            $field['_hb_role'] = $this->detectCustomFieldRole($field);
            $normalized[$field['id']] = $field;
        }
        return $normalized;
    }

    protected function detectCustomFieldRole(array $field)
    {
        $name = strtolower(trim(!empty($field['name']) ? (string) $field['name'] : ''));
        $description = strtolower(trim(!empty($field['description']) ? (string) $field['description'] : ''));
        $regex = strtolower(trim(!empty($field['regex']) ? (string) $field['regex'] : ''));
        $haystack = trim($name . ' ' . $description . ' ' . $regex);

        if ($haystack === '') {
            return 'generic';
        }

        if (
            strpos($haystack, 'ip') !== false ||
            strpos($haystack, 'ipv4') !== false ||
            strpos($haystack, 'ip address') !== false ||
            strpos($regex, '\\.\\d{1,3}\\.') !== false ||
            strpos($regex, 'ipv4') !== false
        ) {
            return 'ip';
        }

        if (
            strpos($haystack, 'hostname') !== false ||
            strpos($haystack, 'host name') !== false ||
            strpos($haystack, 'domain') !== false
        ) {
            return 'hostname';
        }

        if (
            strpos($haystack, 'license key') !== false ||
            strpos($haystack, 'key') !== false ||
            strpos($haystack, 'serial') !== false
        ) {
            return 'license_key';
        }

        return 'generic';
    }

    protected function fieldAllowsEmpty(array $field)
    {
        return empty($field['required']);
    }

    protected function extractServiceId(array $response)
    {
        if (isset($response['serviceId'])) {
            return (string) $response['serviceId'];
        }
        if (isset($response['id'])) {
            return (string) $response['id'];
        }
        return '';
    }

    protected function syncRemoteLicenseDetails($licenseId, $persist = true, $overwriteStatus = true)
    {
        $remote = $this->api()->getLicenseDetails($licenseId);
        if (!is_array($remote)) {
            throw new RuntimeException('Unexpected license details response from remote API');
        }

        $remoteLicenseIp = isset($remote['ip']) ? trim((string) $remote['ip']) : '';
        $mergedIp = $remoteLicenseIp !== '' ? $remoteLicenseIp : $this->currentIp(false);

        $status = isset($remote['status']) ? (string) $remote['status'] : $this->detailValue('status', 'Pending');
        if ($overwriteStatus || $this->detailValue('status', '') === '') {
            $this->details['status']['value'] = $status;
        }
        $this->details['remote_service_id']['value'] = isset($remote['id']) ? (string) $remote['id'] : (string) $licenseId;
        $this->details['product_id']['value'] = isset($remote['pid']) ? (string) $remote['pid'] : $this->detailValue('product_id', '');
        $this->details['product_name']['value'] = isset($remote['productName']) ? (string) $remote['productName'] : $this->detailValue('product_name', '');
        $this->details['license_key']['value'] = isset($remote['licenseKey']) ? (string) $remote['licenseKey'] : $this->detailValue('license_key', '');
        $this->details['license_ip']['value'] = $mergedIp;
        $this->details['nat_ip']['value'] = '';
        $this->details['hostname']['value'] = isset($remote['hostname']) ? (string) $remote['hostname'] : '';
        $this->details['kernel']['value'] = isset($remote['kernel']) ? (string) $remote['kernel'] : '';
        $this->details['change_ip_count']['value'] = isset($remote['changeIpCount']) ? (string) $remote['changeIpCount'] : $this->detailValue('change_ip_count', '0');
        $this->details['change_ip_limit']['value'] = isset($remote['changeIpLimit']) ? (string) $remote['changeIpLimit'] : $this->detailValue('change_ip_limit', '0');
        $this->details['auto_renew']['value'] = !empty($remote['autoRenew']) ? '1' : '0';
        $this->details['renew_date']['value'] = isset($remote['renewDate']) ? (string) $remote['renewDate'] : '';
        $this->details['reg_date']['value'] = isset($remote['regDate']) ? (string) $remote['regDate'] : '';
        $this->details['suspended_reason']['value'] = isset($remote['suspendedReason']) ? (string) $remote['suspendedReason'] : '';
        $this->details['commands_json']['value'] = $this->encodeCommands(isset($remote['commands']) ? $remote['commands'] : []);
        $this->persistCurrentIp($this->details['license_ip']['value']);
        if ($persist) {
            $this->persistDetails();
        }
        return $remote;
    }

    protected function commandsList()
    {
        $raw = $this->detailValue('commands_json', '');
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }

        $commands = [];
        if (is_string($decoded)) {
            $decoded = ['Command' => $decoded];
        }

        foreach ((array) $decoded as $title => $command) {
            if (is_array($command)) {
                foreach ($command as $subTitle => $subCommand) {
                    if ((string) $subCommand !== '') {
                        $commands[] = ['title' => is_string($subTitle) ? $subTitle : $title, 'command' => (string) $subCommand];
                    }
                }
                continue;
            }
            if ((string) $command !== '') {
                $commands[] = ['title' => is_string($title) ? $title : 'Command', 'command' => (string) $command];
            }
        }
        return $commands;
    }

    protected function encodeCommands($commands)
    {
        if (is_string($commands)) {
            return json_encode(['Command' => $commands]);
        }
        if (!is_array($commands)) {
            return json_encode([]);
        }
        return json_encode($commands);
    }

    protected function remoteServiceId($strict = false)
    {
        $id = trim((string) $this->detailValue('remote_service_id', ''));
        if ($strict && $id === '') {
            throw new RuntimeException('Remote service ID is missing');
        }
        return $id;
    }

    protected function currentIp($strict = false)
    {
        $ip = '';
        $resourceIp = trim((string) $this->resourceOrDefault('ip', ''));
        if ($resourceIp !== '') {
            $ip = $resourceIp;
        } else if (!empty($this->account_config['ip']['value'])) {
            $ip = $this->account_config['ip']['value'];
        } else if (!empty($this->details['license_ip']['value'])) {
            $ip = $this->details['license_ip']['value'];
        } else if (!empty($this->details['nat_ip']['value'])) {
            $ip = $this->details['nat_ip']['value'];
        } else if (!empty($this->details['ip']['value'])) {
            $ip = $this->details['ip']['value'];
        } else if (!empty($this->account_details['domain']) && filter_var($this->account_details['domain'], FILTER_VALIDATE_IP)) {
            $ip = $this->account_details['domain'];
        }

        $ip = trim((string) $ip);
        if ($strict && !filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new RuntimeException('Licensed IP is empty or invalid');
        }
        return $ip;
    }

    protected function resolveChangeIpNewIp($newIp = null)
    {
        $candidates = [
            $newIp,
            !empty($this->account_config['new_ip']['value']) ? $this->account_config['new_ip']['value'] : null,
            $this->resourceOrDefault('new_ip', ''),
        ];

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '') {
                return $candidate;
            }
        }
        return '';
    }

    protected function persistCurrentIp($ip)
    {
        $ip = trim((string) $ip);
        if ($ip === '') {
            return;
        }

        $this->details['license_ip']['value'] = $ip;
        if (!empty($this->details['ip'])) {
            $this->details['ip']['value'] = $ip;
        }

        if (!empty($this->account_config['ip']) && $this->updateAccountConfig('ip', $ip)) {
            $this->saveAccountConfig();
        }
    }

    protected function persistDetails()
    {
        if (empty($this->account_details['id'])) {
            return;
        }

        try {
            $accounts = HBLoader::LoadModel('Accounts');
            $accounts->updateExtraDetails($this->account_details['id'], $this->details);
        } catch (Exception $ignored) {
        }
    }

    protected function serverValue($keys, $default = '')
    {
        foreach ((array) $keys as $key) {
            if (isset($this->connect_data[$key]) && $this->connect_data[$key] !== '') {
                return $this->connect_data[$key];
            }
        }
        return $default;
    }

    protected function resourceOrDefault($name, $default = '')
    {
        try {
            $value = $this->resource($name);
            return $value === '' || $value === null ? $default : $value;
        } catch (Exception $ignored) {
        }
        return $default;
    }

    protected function detailValue($name, $default = '')
    {
        if (isset($this->details[$name]) && isset($this->details[$name]['value']) && $this->details[$name]['value'] !== '') {
            return $this->details[$name]['value'];
        }
        return $default;
    }

    protected function statusClass($status)
    {
        switch (strtolower((string) $status)) {
            case 'active':
                return 'success';
            case 'suspended':
                return 'warning';
            case 'terminated':
            case 'cancelled':
            case 'canceled':
                return 'danger';
            case 'pending':
                return 'info';
            default:
                return 'default';
        }
    }

    protected function registerClientWidgets()
    {
        if (!$this->getModuleId()) {
            return;
        }

        foreach ($this->clientWidgets() as $widget => $definition) {
            try {
                $widgetId = $this->upsertWidgetConfig($widget, $definition);
                if ($widgetId) {
                    $this->assignWidgetToProducts($widgetId);
                }
            } catch (Exception $ignored) {
            }
        }
    }

    protected function clientWidgets()
    {
        return [
            'sl_licensedetails' => [
                'name' => 'License Details',
                'group' => 'apps',
                'options' => self::WIDGET_OPTIONS_DEFAULT,
            ],
            'sl_changeip' => [
                'name' => 'Change IP',
                'group' => 'apps',
                'options' => self::WIDGET_OPTIONS_ACTION,
            ],
            'sl_licensedocs' => [
                'name' => 'License Docs',
                'group' => 'apps',
                'options' => self::WIDGET_OPTIONS_DEFAULT,
            ],
        ];
    }

    protected function upsertWidgetConfig($widget, array $definition)
    {
        $name = !empty($definition['name']) ? $definition['name'] : $widget;
        $group = !empty($definition['group']) ? $definition['group'] : 'apps';
        $options = isset($definition['options']) ? (int) $definition['options'] : self::WIDGET_OPTIONS_DEFAULT;
        $location = $this->widgetLocation($widget);
        $config = serialize([]);

        $query = $this->db->prepare('SELECT id FROM hb_widgets_config WHERE widget = ? LIMIT 1');
        $query->execute([$widget]);
        $widgetId = (int) $query->fetchColumn();
        $query->closeCursor();

        if ($widgetId) {
            $update = $this->db->prepare('UPDATE hb_widgets_config SET name = ?, location = ?, config = ?, options = ?, `group` = ? WHERE id = ?');
            $update->execute([$name, $location, $config, $options, $group, $widgetId]);
            return $widgetId;
        }

        $insert = $this->db->prepare('INSERT INTO hb_widgets_config (`widget`, `name`, `location`, `config`, `options`, `group`) VALUES (?, ?, ?, ?, ?, ?)');
        $insert->execute([$widget, $name, $location, $config, $options, $group]);
        return (int) $this->db->lastInsertId();
    }

    protected function assignWidgetToProducts($widgetId)
    {
        $insert = $this->db->prepare("INSERT INTO hb_widgets (`target_type`, `target_id`, `widget_id`, `name`, `config`, `group`)
            SELECT 'Product', pm.product_id, ?, '', '', ''
            FROM hb_products_modules pm
            WHERE pm.module = ?
            AND NOT EXISTS (
                SELECT 1 FROM hb_widgets hw
                WHERE hw.target_type = 'Product'
                AND hw.target_id = pm.product_id
                AND hw.widget_id = ?
            )");
        $insert->execute([$widgetId, $this->getModuleId(), $widgetId]);
    }

    protected function widgetLocation($widget)
    {
        $mainDir = defined('MAINDIR') ? constant('MAINDIR') : '';
        $base = defined('APPDIR_MODULES') ? constant('APPDIR_MODULES') : $mainDir . 'includes' . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR;
        $base = rtrim((string) $base, '\\/');
        if ($mainDir !== '' && stripos($base, $mainDir) === 0) {
            $base = substr($base, strlen($mainDir));
        }

        return $base . DIRECTORY_SEPARATOR . 'Hosting' . DIRECTORY_SEPARATOR . strtolower(get_class($this)) . DIRECTORY_SEPARATOR . 'widgets' . DIRECTORY_SEPARATOR . $widget . DIRECTORY_SEPARATOR;
    }
}

?>