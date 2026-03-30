# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html) where practical.

## [Unreleased]

## [1.0.1] - 2026-03-30

### Added

- Enterprise-grade English documentation in `README.md`.
- GPLv3 licensing files: `LICENSE`, `NOTICE`.
- SPDX headers across PHP/JS/Smarty templates.

### Changed

- Hardened API client settings (connect timeout + SSL verification).
- Added GET-only retry with exponential backoff for transient network/HTTP issues.

## [1.0.0] - 2026-03-30

### Added

- Initial SharedLicense provisioning module for HostBill.
- Product catalog loading from SharedLicense API with fallback cache `products.json`.
- License lifecycle actions: Create, Suspend, Unsuspend, Cancel (Terminate), Renew.
- Change IP action with HostBill-side limit tracking.
- Admin AJAX license panel with actions and install command copy.
- Client widgets: License Details, Change IP, License Docs.
