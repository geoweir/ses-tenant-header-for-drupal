# SES Tenant Header for Drupal

**Project:** SES Tenant Header  
**Machine name:** `ses_tenant_header`  
**Type:** Drupal contributed module  
**Proposed version:** 1.0.0  
**Licence:** GPL-2.0-or-later  
**Drupal compatibility:** Drupal 10 and 11  
**Drush compatibility:** Supported versions of Drush for the target Drupal releases  
**Status:** Initial specification

## 1. Project overview

### Purpose

SES Tenant Header is a lightweight Drupal module that enables Amazon Simple Email Service (SES) tenant attribution for outgoing email.

Amazon SES supports tenant-based sending, allowing organisations operating multiple applications, websites or customer environments to distinguish email activity, monitor individual tenant reputation and manage sending behaviour independently.

When email is sent using Amazon SES SMTP, the tenant is identified using the `X-SES-TENANT` email header.

This module automatically adds that header to outgoing Drupal emails when a tenant name has been configured in the site's `settings.php` file.

The module also provides a Drush command for inspecting and validating the configured tenant name, supporting operational monitoring with systems such as Zabbix.

### Design philosophy

The module should be:

- **Small:** One clearly defined responsibility.
- **Optional:** No change to email when a tenant is not configured.
- **Transport-independent:** Operate on Drupal's mail message rather than integrating directly with an SMTP library.
- **Non-invasive:** Require no modifications to existing mail modules.
- **Configuration-driven:** Use `settings.php` rather than Drupal's configuration database.
- **Observable:** Provide predictable machine-readable status through Drush.
- **Safe:** Reject malformed tenant values without introducing header-injection vulnerabilities.
- **Open source:** Suitable for publication and maintenance within the Drupal community.

### Scope

The module is responsible for adding the SES tenant identification header.

It is not responsible for SMTP connectivity, credentials, email composition, SES tenant creation or AWS identity verification.

## 2. Background

Amazon SES introduced tenant management in August 2025.

For SMTP sending, AWS supports the following email header:

```text
X-SES-TENANT: ExampleTenant
```

When SES receives an email containing this header, the sending operation is associated with the specified tenant, subject to AWS resource association and authorisation requirements.

Existing Drupal email modules do not necessarily expose tenant-specific configuration. This module provides that functionality through Drupal's established mail-alter mechanism.

## 3. Functional requirements

### FR-001: Read tenant from Drupal settings

The module MUST read its configuration from Drupal's runtime settings.

Configuration example in `settings.php`:

```php
$settings['ses_tenant'] = 'Newarthill';
```

Access MUST use Drupal's Settings API:

```php
\Drupal\Core\Site\Settings::get('ses_tenant');
```

The implementation MUST NOT parse or include `settings.php` directly.

The setting MUST NOT be stored in the Drupal configuration database.

The module MUST NOT require an administrative configuration form.

### FR-002: Inject the SES header

When a valid, non-empty tenant name is configured, the module MUST add:

```text
X-SES-TENANT: Newarthill
```

Implementation MUST use Drupal's mail alteration mechanism:

```php
hook_mail_alter()
```

The header MUST be added to the message's `headers` array.

The module MUST preserve all unrelated headers and message properties, including sender, recipients, subject and body.

Header injection MUST happen before the configured mail backend processes the message.

### FR-003: Optional configuration

When the `ses_tenant` setting is absent, null or an empty string, the module MUST leave outgoing email unchanged.

It MUST NOT:

- Add an empty SES tenant header.
- Prevent email delivery.
- Generate an error for an intentionally unconfigured tenant.
- Require the SMTP module or an AWS connection.

The module must be safe to install and enable on sites that do not use Amazon SES.

### FR-004: Tenant validation

Before injecting the header, the module MUST validate the configured tenant value.

Validation requirements:

- Value must be a string.
- Value must not be empty.
- Value must not contain whitespace, carriage returns, line feeds or control characters.
- Value must conform to the tenant-name constraints published by AWS SES.
- The module must not silently alter or sanitise an invalid name into another tenant name.

For an invalid value, the module MUST:

1. Omit the SES header.
2. Record a warning through Drupal's logging system.
3. Allow ordinary email processing to continue.

This fail-open behaviour is intentional for version 1.0 to avoid disrupting existing mail delivery. Operators must understand that such emails may be sent without tenant attribution.

Validation should be implemented in a reusable service used by both mail injection and the Drush command.

### FR-005: Existing header handling

If the email already contains `X-SES-TENANT`, the module MUST avoid producing duplicate headers.

When a valid tenant setting is configured, the configured value MUST take precedence over an existing header value.

Header keys MUST be compared case-insensitively to avoid duplicates with different capitalisation.

The resulting message MUST contain exactly one `X-SES-TENANT` header.

When no tenant is configured, existing headers MUST be left unchanged.

### FR-006: Mail transport compatibility

The module MUST NOT directly depend on:

- Drupal SMTP Authentication Support.
- PHPMailer.
- Amazon SES Drupal integration.
- AWS SDK for PHP.
- An external HTTP service.

The module should work with mail delivery implementations that honour Drupal's message headers.

Version 1.0 MUST be tested with Drupal SMTP Authentication Support and Amazon SES SMTP.

Compatibility with other mail transports must be documented rather than assumed.

Emails sent outside Drupal's Mail Manager are explicitly outside scope.

## 4. Drush verification command

### FR-007: Provide a Drush command

The module MUST register a Drush command:

```bash
drush ses-tenant:status
```

Alias:

```bash
drush ses-tenant
```

The command MUST read the same Drupal setting and use the same validation logic as email injection.

It MUST NOT send email, contact AWS, alter configuration or require network access.

### FR-008: Human-readable output

When configured correctly:

```text
SES tenant: Newarthill
Status: OK
```

When not configured:

```text
SES tenant: Not configured
Status: DISABLED
```

When invalid:

```text
SES tenant: Invalid configuration
Status: ERROR
```

Diagnostic details for invalid values may be provided without echoing control characters or unsafe input.

### FR-009: Machine-readable monitoring output

Provide a `--format` option supporting:

- `text` — default human-readable output.
- `value` — tenant name only, suitable for simple monitoring.
- `json` — structured, versioned monitoring output.

Example:

```bash
drush ses-tenant:status --format=json
```

Successful result:

```json
{
  "schema_version": 1,
  "status": "ok",
  "configured": true,
  "valid": true,
  "tenant": "Newarthill",
  "header": "X-SES-TENANT"
}
```

Missing configuration:

```json
{
  "schema_version": 1,
  "status": "disabled",
  "configured": false,
  "valid": false,
  "tenant": null,
  "header": "X-SES-TENANT"
}
```

Invalid configuration:

```json
{
  "schema_version": 1,
  "status": "error",
  "configured": true,
  "valid": false,
  "tenant": null,
  "header": "X-SES-TENANT"
}
```

JSON output MUST be a single valid JSON object on stdout.

No Drush informational banners, success messages or diagnostic output may be mixed into JSON stdout.

Errors and diagnostics may be written to stderr.

The output schema MUST remain backward-compatible within the 1.x release series.

### FR-010: Monitoring exit codes

The command MUST use the following exit codes:

| Exit code | Meaning |
|---|---|
| 0 | Configuration valid, or intentionally
