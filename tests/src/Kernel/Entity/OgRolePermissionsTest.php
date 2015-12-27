<?php

/**
 * @file
 * Contains Drupal\Tests\og\Kernel\Entity\OgRolePermissionsTest.
 */

namespace Drupal\Tests\og\Kernel\Entity;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\KernelTests\KernelTestBase;
use Drupal\og\Entity\OgRole;

/**
 * Test OG overriding roles and permissions.
 *
 * @group og
 */
class OgRolePermissionsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['og'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Installing needed schema.
    $this->installConfig(['og']);
  }

  /**
   * Testing OG overriding permissions and roles field.
   */
  public function testFieldAttachment() {
  }

}
