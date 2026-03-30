# Contributing

This repository contains a HostBill provisioning module for the SharedLicense Reseller API.

## Scope

- **Bug fixes**: stability, error handling, compatibility with HostBill.
- **Documentation**: README improvements, examples (sanitized), troubleshooting.
- **UX**: admin panel and client widgets.

## Local development

1. Work on a copy of the module in a HostBill dev instance.
2. Keep changes backward compatible whenever possible.

### Coding style

- PHP: keep code compatible with typical HostBill deployments (avoid new language features unless required).
- Prefer clear, defensive code and explicit error messages.
- Do not log secrets (Bearer tokens).

### Testing checklist

Before submitting:

- Test **Test Connection**.
- Verify product list loads (`GET /products`) and cache fallback works.
- Test Create with a **non-billable test product**.
- Test actions: suspend/unsuspend/cancel/renew.
- Test Change IP with valid and invalid IP values.
- Confirm admin actions require HostBill security token validation (`token_valid`).

## Security

- Never commit real Bearer tokens.
- If providing API samples, redact IDs, emails, tokens, and any customer data.

## Release process (suggested)

- Update module version in `class.sharedlicense.php`.
- Update `CHANGELOG.md`.
- Tag release in Git.
