<?php

namespace Drupal\ses_tenant_header\Drush\Commands;

use Drupal\ses_tenant_header\TenantResolver;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for the SES Tenant Header module.
 */
class SesTenantHeaderCommands extends DrushCommands {

  public function __construct(protected TenantResolver $resolver) {
    parent::__construct();
  }

  /**
   * Shows the configured SES tenant and whether it is valid.
   *
   * Exit codes: 0 valid or intentionally unconfigured, 1 invalid.
   */
  #[CLI\Command(name: 'ses-tenant:status', aliases: ['ses-tenant'])]
  #[CLI\Option(name: 'format', description: 'Output format: text, value or json.')]
  #[CLI\Usage(name: 'drush ses-tenant:status --format=json', description: 'Machine-readable status for monitoring.')]
  public function status(array $options = ['format' => 'text']): int {
    $format = $options['format'] ?? 'text';
    if (!in_array($format, ['text', 'value', 'json'], TRUE)) {
      $this->io()->getErrorStyle()->error('Unsupported format. Use text, value or json.');
      return self::EXIT_FAILURE;
    }

    $result = $this->resolver->resolve();
    $status = $result['status'];
    $out = $this->output();

    switch ($format) {
      case 'json':
        $out->writeln(json_encode([
          'schema_version' => 1,
          'status' => $status,
          'configured' => $status !== TenantResolver::STATUS_DISABLED,
          'valid' => $status === TenantResolver::STATUS_OK,
          'tenant' => $result['tenant'],
          'header' => TenantResolver::HEADER,
        ], JSON_UNESCAPED_SLASHES));
        break;

      case 'value':
        if ($status === TenantResolver::STATUS_OK) {
          $out->writeln($result['tenant']);
        }
        break;

      default:
        $label = match ($status) {
          TenantResolver::STATUS_OK => $result['tenant'],
          TenantResolver::STATUS_DISABLED => 'Not configured',
          default => 'Invalid configuration',
        };
        $out->writeln('SES tenant: ' . $label);
        $out->writeln('Status: ' . strtoupper($status));
        if ($status === TenantResolver::STATUS_ERROR) {
          $this->io()->getErrorStyle()->writeln('Reason: ' . $result['reason']);
        }
    }

    return $status === TenantResolver::STATUS_ERROR ? self::EXIT_FAILURE : self::EXIT_SUCCESS;
  }

}
