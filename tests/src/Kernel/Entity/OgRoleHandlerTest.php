<?php

/**
 * @file
 * Contains \Drupal\Tests\og\Kernel\Entity\OgRoleHandlerTest.
 */

namespace Drupal\Tests\og\Kernel\Entity;

use Drupal\KernelTests\KernelTestBase;
use Drupal\og\Entity\OgRole;
use Drupal\og\Og;
use Drupal\user\Entity\User;

/**
 * @group og
 */
class OgRoleHandlerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['entity_test', 'user', 'field', 'entity_reference', 'og', 'system'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Add membership and config schema.
    $this->installConfig(['og']);
    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('og_membership');
    $this->installEntitySchema('user');
    $this->installEntitySchema('og_users_role');
    $this->installSchema('system', 'sequences');
  }

  /**
   * Testing getting all group audience fields.
   */
  public function testRoleHandler() {
    $og_role = OgRole::create();
    $og_role
      ->setId('content_editor')
      ->setLabel('Content editor')
      ->grantPermission('administer group')
      ->save();

    $user = User::create(['name' => $this->randomString()]);

    $og_users_role = Og::rolesHandler()->grantRole($user, $og_role);

    $this->assertEquals($og_role->id(), $og_users_role->getRid());
    $this->assertEquals($user->id(), $og_users_role->getUid());
  }

}
