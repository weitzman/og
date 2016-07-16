<?php

/**
 * @file
 * Contains \Drupal\og_ui\Tests\PeopleTabTest.
 */

namespace Drupal\Tests\og_ui\Tests\Functional;

use Drupal\KernelTests\AssertLegacyTrait;
use Drupal\og\Entity\OgMembership;
use Drupal\og\Entity\OgRole;
use Drupal\og\Og;
use Drupal\og\OgMembershipInterface;
use Drupal\simpletest\AssertContentTrait;
use Drupal\Tests\BrowserTestBase;

/**
 * Test making a bundle a group and a group content.
 *
 * @group og
 */
class PeopleTabTest extends BrowserTestBase {

  use AssertContentTrait;
  use AssertLegacyTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['node', 'og', 'og_ui', 'user'];

  /**
   * An administrator user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $adminUser;

  /**
   * @var \Drupal\user\Entity\User
   */
  protected $standardUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var OgRole
   */
  protected $ogRole;

  /**
   * @var \Drupal\node\Entity\Node
   */
  protected $group;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->entityTypeManager = \Drupal::entityTypeManager();

    // Log in as an administrator that can manage blocks and content types.
    $this->adminUser = $this->drupalCreateUser([
      'bypass node access',
      'administer group',
      'access user profiles',
    ]);

    // Keep a standard user to verify the access logic.
    $this->standardUser = $this->drupalCreateUser(['access content']);

    $this->drupalLogin($this->adminUser);

    // Set up a group.
    $node_type = $this->drupalCreateContentType();
    $this->group = $this->createNode(['type' => $node_type->id(), 'uid' => $this->adminUser->id()]);
    Og::groupManager()->addGroup('node', $this->group->bundle());

    // Create a role and assign the appropriate permission to access to the
    // group tab.
    /** @var OgRole $og_permission */
    $this->ogRole = OgRole::create();
    $this->ogRole
      ->setName($this->randomMachineName())
      ->setLabel($this->randomString())
      ->setGroupType($this->group->getEntityTypeId())
      ->setGroupBundle($this->group->bundle())
      ->grantPermission('administer group')
      ->save();

    /** @var OgMembership $membership */
    $membership = OgMembership::create(['type' => OgMembershipInterface::TYPE_DEFAULT]);
    $membership
      ->setUser($this->adminUser->id())
      ->setEntityId($this->adminUser->id())
      ->setGroupEntityType($this->group->getEntityTypeId())
      ->addRole($this->ogRole->id())
      ->save();
  }

  /**
   * Verifying the people page exists.
   */
  public function testPeopleTab() {

    $base_url = 'node/' . $this->group->id();

    // todo: check tab visibility in the node page.
    // Verify first the tab exits. Can't work form some reason.
    $this->drupalGet($base_url);
    $this->assertSession()->statusCodeEquals(200);

    $this->drupalGet($base_url . '/group/people/manage');
    $this->assertSession()->statusCodeEquals(200);
  }

}