# Upgrade Guide

This module is designed to be upgraded in-place.

## Before you upgrade

- Back up your HostBill database.
- Back up the module directory (this repository folder).
- If possible, test upgrades in a staging HostBill instance.

## Upgrade steps

1. Replace the module files in your HostBill installation with the new version.
2. In HostBill Admin, trigger module upgrade (or simply load the module; HostBill will call module upgrade hooks when applicable).
3. Verify:
   - **Test Connection** works.
   - Product catalog loads (or falls back to `products.json`).
   - Admin service panel loads via AJAX.
   - Client widgets render correctly.

## Notes by version

### 1.0.1

- Documentation, licensing files, SPDX headers.
- API client hardening (TLS verification, connect timeout).
- Added GET-only retry with exponential backoff.
- Product catalog cache writes are now atomic to reduce corruption risk.

No breaking changes are expected.
