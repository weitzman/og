<?php

// todo: remove after fixing tests.

/**
 * @file
 * Contains \Drupal\node\Tests\NodeAdminTest.
 */

namespace Drupal\og\Tests;

use Drupal\Component\Utility\Unicode;
use Drupal\field\Tests\EntityReference\EntityReferenceTestTrait;
use Drupal\node\Entity\NodeType;
use Drupal\og\Og;
use Drupal\simpletest\WebTestBase;

/**
 * Tests node administration page functionality.
 *
 * @group og
 */
class OgFoo extends WebTestBase {

  use EntityReferenceTestTrait;

  /**
   * A user with permission to bypass access content.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * A user with the 'access content overview' permission.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $baseUser1;

  /**
   * A normal user with permission to view own unpublished content.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $baseUser2;

  /**
   * A normal user with permission to bypass node access content.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $baseUser3;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('og', 'node');

  /**
   * Tests that the table sorting works on the content admin pages.
   */
  function testContentAdminSort() {
    NodeType::create([
      'type' => $group_content_type = Unicode::strtolower($this->randomMachineName()),
      'name' => $this->randomString(),
    ])->save();
    $this->createEntityReferenceField('node', $group_content_type, OG_AUDIENCE_FIELD, $this->randomString(), 'node');
//    Og::createField(OG_AUDIENCE_FIELD, 'node', $group_content_type);
    debug('a');
  }

}
