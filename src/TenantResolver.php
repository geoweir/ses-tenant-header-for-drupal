<?php

namespace Drupal\ses_tenant_header;

use Drupal\Core\Site\Settings;

/**
 * Reads and validates the SES tenant name from settings.php.
 */
class TenantResolver {

  const HEADER = 'X-SES-TENANT';
  const SETTING = 'ses_tenant';
  const STATUS_OK = 'ok';
  const STATUS_DISABLED = 'disabled';
  const STATUS_ERROR = 'error';

  /**
   * AWS SES tenant names: 1-64 characters of letters, digits, _ and -.
   */
  const PATTERN = '/^[A-Za-z0-9_-]{1,64}$/D';

  /**
   * Resolves the configured tenant.
   *
   * @return array
   *   Keys: status (ok|disabled|error), tenant (string|null), reason
   *   (string|null; a fixed, safe description, never the raw value).
   */
  public function resolve(): array {
    return $this->validate(Settings::get(self::SETTING));
  }

  /**
   * Validates a raw setting value.
   */
  public function validate(mixed $value): array {
    if ($value === NULL || $value === '') {
      return ['status' => self::STATUS_DISABLED, 'tenant' => NULL, 'reason' => NULL];
    }
    if (!is_string($value)) {
      return $this->error('value is not a string');
    }
    if (preg_match('/[\s\x00-\x1F\x7F]/', $value)) {
      return $this->error('value contains whitespace or control characters');
    }
    if (!preg_match(self::PATTERN, $value)) {
      return $this->error('value must be 1-64 characters of letters, digits, hyphens and underscores');
    }
    return ['status' => self::STATUS_OK, 'tenant' => $value, 'reason' => NULL];
  }

  /**
   * Returns headers with exactly one X-SES-TENANT set to the tenant.
   */
  public function applyHeader(array $headers, string $tenant): array {
    foreach (array_keys($headers) as $key) {
      if (is_string($key) && strcasecmp($key, self::HEADER) === 0) {
        unset($headers[$key]);
      }
    }
    $headers[self::HEADER] = $tenant;
    return $headers;
  }

  private function error(string $reason): array {
    return ['status' => self::STATUS_ERROR, 'tenant' => NULL, 'reason' => $reason];
  }

}
