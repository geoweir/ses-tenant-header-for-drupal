<?php

namespace Drupal\Tests\ses_tenant_header\Unit;

use Drupal\ses_tenant_header\TenantResolver;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\ses_tenant_header\TenantResolver
 * @group ses_tenant_header
 */
class TenantResolverTest extends TestCase {

  /**
   * @dataProvider values
   */
  public function testValidate(mixed $value, string $status): void {
    $this->assertSame($status, (new TenantResolver())->validate($value)['status']);
  }

  public static function values(): array {
    return [
      'null' => [NULL, 'disabled'],
      'empty' => ['', 'disabled'],
      'valid' => ['Newarthill', 'ok'],
      'valid punct' => ['a_b-c9', 'ok'],
      'space' => ['a b', 'error'],
      'newline' => ["a\r\nX-Evil: 1", 'error'],
      'trailing newline' => ["abc\n", 'error'],
      'int' => [5, 'error'],
      'array' => [['a'], 'error'],
      'too long' => [str_repeat('a', 65), 'error'],
      'max length' => [str_repeat('a', 64), 'ok'],
      'unicode' => ['tenänt', 'error'],
    ];
  }

  public function testApplyHeaderReplacesCaseInsensitively(): void {
    $headers = ['x-ses-tenant' => 'old', 'Subject' => 's', 'X-Ses-Tenant' => 'older'];
    $result = (new TenantResolver())->applyHeader($headers, 'New');
    $this->assertSame(['Subject' => 's', 'X-SES-TENANT' => 'New'], $result);
  }

}
