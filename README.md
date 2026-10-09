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

## Verifying it works

The delivered message will **not** contain `X-SES-TENANT`: SES consumes
`X-SES-*` headers and strips them. Verify the header leaves Drupal instead:

1. Enable debug logging in the SMTP module (`/admin/config/system/smtp`).
2. Send a test e-mail from the SMTP module settings page.
3. Find the debug entry and inspect it:

   ```bash
   drush watchdog:show --type=smtp --severity=Debug --count=5
   drush watchdog:show-one <ID> | grep -i -E "ses-tenant|DATA|Subject"
   ```

   The `DATA` section should include `x-ses-tenant: <your tenant>`. The SMTP
   module lowercases the header name; this is fine as header names are
   case-insensitive.
4. Turn debug logging off again, as it records the SMTP conversation.

You can also check the alter hook directly:

```bash
drush php:eval '$m=["headers"=>["Subject"=>"x"]]; \Drupal::moduleHandler()->alter("mail",$m); print_r($m["headers"]);'
```

Module log warnings (invalid tenant value): `drush watchdog:show --type=ses_tenant_header`.

## AWS console delay

Tenant activity and metrics in the AWS SES console can take several minutes
(sometimes longer) to appear after sending. If the header is present in the
SMTP debug log, wait and re-check before troubleshooting further. The tenant
must exist, and its identity and configuration set must be associated with it,
in the same region as your SMTP endpoint.
