# Security Policy

## Supported versions

Only the latest version on the default branch is supported.

## Reporting a vulnerability

If you believe you have found a security vulnerability:

1. **Do not** open a public issue with sensitive details.
2. Use **GitHub Security Advisories** (preferred) to report privately.
3. If advisories are not available, open a minimal issue and ask maintainers for a private channel.

## Threat model (high level)

This module runs inside a HostBill environment and performs authenticated calls to the SharedLicense Reseller API.

Primary assets:

- SharedLicense **Bearer token** (server credential)
- Customer/service metadata stored in HostBill extra details

Primary trust boundaries:

- HostBill admin actions (mutating operations)
- Remote API calls (network + third-party responses)
- HostBill templates/widgets (output rendering)

## Security design notes

### Token handling

- The Bearer token is provided via HostBill server configuration.
- Debug logs mask the Authorization header as `Bearer ***`.
- Do not hard-code tokens in the repository.

### Admin action authorization

Mutating admin actions (renew/change IP/reset counter) require HostBill security token validation (`token_valid`) via the controller.

### Network and TLS

- API requests use cURL with TLS verification enabled.
- Use a valid CA bundle on the HostBill server.

### Data validation

- IP addresses are validated using `FILTER_VALIDATE_IP`.

## Operational recommendations

- Rotate Bearer tokens periodically.
- Restrict which staff can access HostBill server credentials.
- Ensure HostBill and PHP are kept up to date with security patches.
