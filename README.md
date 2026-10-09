# SES Tenant Header

Adds the Amazon SES `X-SES-TENANT` header to outgoing Drupal mail via
`hook_mail_alter()`. Drupal 10/11, GPL-2.0-or-later.

## Configuration

In `settings.php`:

```php
$settings['ses_tenant'] = 'Newarthill';
```

Absent/empty: mail is untouched. Invalid (not 1-64 chars of `A-Za-z0-9_-`):
header omitted, a warning is logged, and mail is sent anyway (fail-open).
A configured valid value replaces any existing `X-SES-TENANT` header.

## Drush

```
drush ses-tenant:status [--format=text|value|json]
```

Exit code 0 when valid or unconfigured, 1 when invalid. JSON schema_version 1.

## Compatibility

Works with transports that honour Drupal's message headers; tested target is
SMTP Authentication Support with Amazon SES SMTP. Mail sent outside Drupal's
Mail Manager is out of scope.
