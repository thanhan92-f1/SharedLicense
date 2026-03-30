# SharedLicense Provisioning Module for HostBill

Provisioning module for HostBill that integrates with the **SharedLicense Reseller API** (Bearer token authentication). It supports automated ordering, lifecycle management, license synchronization, admin tooling, and client widgets.

> Important: **Creating an order may generate a billable license** on the SharedLicense side. Do not test `Create()` against paid products unless you intend to purchase.

## Table of contents

- [Key features](#key-features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
	- [Server connection fields](#server-connection-fields)
	- [Module options (resources)](#module-options-resources)
	- [Dynamic product custom fields](#dynamic-product-custom-fields)
	- [Product config options](#product-config-options)
- [Provisioning lifecycle](#provisioning-lifecycle)
- [Persisted service data (extra details)](#persisted-service-data-extra-details)
- [Admin UI (AJAX panel + actions)](#admin-ui-ajax-panel--actions)
- [Client widgets](#client-widgets)
- [Product catalog cache (`products.json`)](#product-catalog-cache-productsjson)
- [Logging and troubleshooting](#logging-and-troubleshooting)
- [Security notes](#security-notes)
- [License](#license)

Additional docs:

- `UPGRADE.md` — upgrade procedure and version notes
- `SECURITY.md` — reporting and threat model
- `SUPPORT.md` — support scope

## Compatibility

This module targets a standard HostBill module runtime and avoids non-portable dependencies.

- PHP: requires cURL + JSON.
- UI: uses HostBill admin templates, jQuery and Bootbox (as provided by HostBill).

## Quick start (operator)

1. Enable module in HostBill.
2. Configure a Server record (API Base URL + Bearer Token).
3. Assign the module to a product.
4. Select a SharedLicense `product` option and configure required custom fields (dynamic `sharedlicense_cf_*`).
5. Use Test Connection; then order using a non-billable product for validation.

## Key features

- Provisioning actions: **Create / Suspend / Unsuspend / Terminate (Cancel) / Renew**.
- **Change IP** action with an optional HostBill-side change limit.
- Automatic synchronization from API: status, license key, IP, renew date, installation commands.
- Admin service panel:
	- loads license info via AJAX
	- supports actions: Refresh, Change IP, Renew, Reset IP counter
	- copy-to-clipboard for install commands
- Client widgets (auto-registered & auto-assigned to products using the module):
	- License Details
	- Change IP
	- License Docs (installation commands)
- Product catalog is fetched from API (`GET /products`) with a fallback cache in `products.json`.

## Requirements

- HostBill module type: `LicenseModule`.
- PHP extensions:
	- `curl`
	- `json`
- Outbound HTTPS connectivity from your HostBill server to the configured API base URL.

## Repository layout

- `class.sharedlicense.php` — provisioning logic, payload building, synchronization, widget registration.
- `class.api.php` — API client (cURL + JSON) using `Authorization: Bearer <token>`.
- `admin/class.sharedlicense_controller.php` — admin controller for AJAX rendering and admin actions.
- `templates/`
	- `license.tpl` — injects the SharedLicense panel into the admin service view.
	- `ajax.license.tpl` — HTML partial with license data and Bootbox forms.
	- `license.js` — AJAX loader and UI handlers (refresh/copy/change IP/renew/reset).
- `widgets/`
	- `class.sharedlicense_widget.php` — base widget helper.
	- `sl_licensedetails/` — client widget: details + renew.
	- `sl_changeip/` — client widget: change IP + limit checks.
	- `sl_licensedocs/` — client widget: install commands.
- `products.json` — API response cache (generated automatically when API is reachable).

## API and authentication

All API calls are JSON over HTTP with:

- `Authorization: Bearer <token>`
- `Accept: application/json`

Default API base URL (can be overridden per HostBill server):

- `https://sharedlicense.com/client/modules/addons/LicReseller/api`

API endpoints used by this module (see `class.api.php`):

- `GET /account`
- `GET /products`
- `POST /products/{productId}/order`
- `GET /licenses/{licenseId}`
- `POST /licenses/{licenseId}/renew`
- `POST /licenses/{licenseId}/suspend`
- `POST /licenses/{licenseId}/unsuspend`
- `POST /licenses/{licenseId}/cancel`
- `POST /licenses/{licenseId}/change-ip`

## Installation

1. Copy the module folder into your HostBill modules directory, e.g.:
	 - `.../includes/modules/Hosting/sharedlicense/`

2. In HostBill Admin, enable the module **SharedLicense**.

3. Create/select a HostBill **Server** record for this module and fill the connection fields (see below).

4. Assign the module to a HostBill product like any other provisioning module.

5. Recommended: use **Test Connection** before placing real orders.

## Configuration

### Server connection fields

Configured in HostBill *Server* (see `SharedLicense::$serverFieldsDescription`):

- **Hostname** → API Base URL
- **Username** → Bearer Token

Notes:

- Password/IP Address fields are not used.
- Base URL is normalized (trailing `/` is removed).

### Module options (resources)

Core options (see `SharedLicense::$options`):

- `product` *(loadable)*: SharedLicense Product ID.
	- Populated from the API catalog (`GET /products`).
- `ip`: Licensed IP address.
	- Used in order payload and displayed in service details.
	- Resolution order (highest → lowest):
		1) module resource `ip`
		2) account config `ip`
		3) extra_details `license_ip`
		4) extra_details `nat_ip`
		5) extra_details `ip`
		6) service domain if it is a valid IP
- `new_ip`: New IP address (used by Change IP action).
- `max_ip_changes`: HostBill-side limit for IP changes.
	- `0` means unlimited (no HostBill-side enforcement).
- `suspend_reason`: Optional reason sent to the remote API during suspend.

### Dynamic product custom fields

When a product is selected, the module reads the product `customfields` from the API and dynamically exposes corresponding HostBill options:

- `sharedlicense_cf_<fieldId>`

Example:

- `sharedlicense_cf_12`

Custom field values sent during order are determined as follows:

1. If `sharedlicense_cf_<id>` is configured, that value is used.
2. Otherwise, the module attempts to infer values based on an internal role detection (`detectCustomFieldRole`):
	 - `ip` → the resolved licensed IP
	 - `hostname` → the service domain/hostname
	 - `license_key` → existing `license_key` if present, otherwise `HB-<serviceId>`

If the remote product marks a field as required and the module cannot provide a value, **Create will fail** with a clear error message.

### Product config options

If the API provides `configOptions` for a product, the module sends `configoptions` during order:

- Uses `default` if provided.
- Otherwise selects the first available option ID.

## Provisioning lifecycle

### Create (Order)

Implementation: `SharedLicense::Create()`

- Loads the selected product from the API catalog.
- Builds order payload:
	- `customfields` (object)
	- `configoptions` (object)
- Calls: `POST /products/{id}/order`
- Persists essential service data into extra_details.
- Calls `syncRemoteLicenseDetails()` to fetch and store the authoritative remote state.

Billing warning:

- Ordering creates a license on the reseller platform and **can be billable**.

### Suspend / Unsuspend / Terminate

Implementation: `SharedLicense::Suspend()`, `SharedLicense::Unsuspend()`, `SharedLicense::Terminate()`

- Suspend → `POST /licenses/{id}/suspend` (optional `reason`)
- Unsuspend → `POST /licenses/{id}/unsuspend`
- Terminate (cancel) → `POST /licenses/{id}/cancel`

The module updates `status`, `last_action`, `last_remote_action`, `message`, and usually syncs remote details.

### Renew

Implementation: `SharedLicense::Renewal()` / `SharedLicense::RenewNow()`

- Calls: `POST /licenses/{id}/renew`

### Change IP

Implementation: `SharedLicense::LicenseChangeIp()`

- Validates IPv4/IPv6 using `FILTER_VALIDATE_IP`.
- Enforces HostBill-side limit:
	- if `max_ip_changes > 0` and `change_ip_count >= max_ip_changes` → blocked.
- Calls: `POST /licenses/{id}/change-ip` with payload `{ "ip": "x.x.x.x" }`.
- Increments `change_ip_count` and persists the new IP to:
	- extra_details (`license_ip`)
	- account config `ip` (so future payloads remain consistent)

### Reset IP counter (local only)

Implementation: `SharedLicense::ResetChangeIpCount()`

- Resets only HostBill’s `change_ip_count` stored in extra_details.
- Does **not** call the remote API.

## Persisted service data (extra details)

The module stores and maintains service metadata in extra_details (see `SharedLicense::$details`), including:

- `remote_service_id`
- `license_key`
- `license_ip`
- `product_id`, `product_name`, `product_logo`
- `status`, `message`
- `change_ip_count`, `change_ip_limit`
- `auto_renew`, `renew_date`, `reg_date`
- `suspended_reason`
- `commands_json`
- `last_action`, `last_remote_action`

Most values are refreshed via `syncRemoteLicenseDetails()`.

## Admin UI (AJAX panel + actions)

Admin service page integration:

- Injection: `templates/license.tpl`
- AJAX partial: `templates/ajax.license.tpl`
- JS loader: `templates/license.js`

Admin actions provided:

- Refresh Data
- Change IP
- Reset IP Counter
- Renew Now
- Copy installation commands

Admin endpoints (controller: `admin/class.sharedlicense_controller.php`):

- `?cmd=sharedlicense&action=license&id=<serviceId>` — returns AJAX HTML.
- `?cmd=sharedlicense&action=changeip&id=<serviceId>`
- `?cmd=sharedlicense&action=renew&id=<serviceId>`
- `?cmd=sharedlicense&action=resetipcount&id=<serviceId>`

Note:

- Mutating actions require `token_valid`.

## Client widgets

Widgets are registered on upgrade via `registerClientWidgets()` and auto-assigned to products that use this module:

1. `sl_licensedetails` — shows key license fields and supports renew.
2. `sl_changeip` — allows clients to change IP (with HostBill-side limit checks).
3. `sl_licensedocs` — displays installation commands returned by the API.

## Product catalog cache (`products.json`)

- On successful catalog fetch, the raw API response is written to `products.json`.
- If the API is unreachable, the module attempts to load the cached catalog from `products.json`.

Operational guidance:

- Do not edit `products.json` manually unless you understand the response schema.
- Delete `products.json` to force a fresh catalog reload.

## Logging and troubleshooting

### Debug logging

If `HBDebug::debug` is available, the module logs API request/response metadata. Authorization is masked as `Bearer ***`.

### Common issues

#### “API token is empty”

- The Bearer token is missing in the HostBill server configuration.

#### “Selected product does not exist in SharedLicense product catalog”

- The configured product ID no longer exists, or catalog loading failed and no cache exists.

#### Create fails due to missing required custom field

- Configure the dynamic option `sharedlicense_cf_<id>` or ensure the module can infer it from IP/hostname/license_key.

#### Change IP is blocked

- HostBill-side limit (`max_ip_changes`) has been reached.
- Admin may use “Reset IP Counter” (local only) if your policy allows.

## FAQ

### Does this module store the Bearer token in the database?

No. The Bearer token is stored in HostBill's server configuration. The module masks it in debug logs.

### Why does the product list not load?

- Check network connectivity and firewall rules.
- Verify API base URL and token.
- If API is down but `products.json` exists, the module should fall back to the cache.

### Can I safely test Create?

Only against a non-billable or sandbox product. Ordering may create a billable license.

## Maintainer notes

- Dynamic options are generated per product based on API `customfields`.
- Extra details are persisted via `Accounts::updateExtraDetails()`.
- Admin actions are routed via `admin/class.sharedlicense_controller.php`.

## Security notes

- Treat the Bearer token as a secret; store it only in HostBill server config.
- Tokens are not written to logs (masked when debug logging is enabled).
- Mutating admin actions are protected by HostBill security token validation (`token_valid`).

For a fuller overview, see `SECURITY.md`.

## License

This project is licensed under:

- **GNU General Public License v3.0 or later** (`GPL-3.0-or-later`)

See `LICENSE` for the full text.

Copyright (C) 2026 **Nguyen Thanh An by Pho Tue SoftWare Solutions JSC**.

Trademarks:

- HostBill and SharedLicense may be trademarks of their respective owners.


