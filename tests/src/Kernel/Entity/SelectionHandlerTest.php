<?php

/**
 * @file
 * Contains \Drupal\Tests\og\Kernel\Entity\SelectionHandlerTest.
 */

namespace Drupal\Tests\og\Kernel\Entity;

use Drupal\Component\Utility\Unicode;
use Drupal\field\Entity\FieldConfig;
use Drupal\node\Entity\NodeType;
use Drupal\KernelTests\KernelTestBase;
use Drupal\og\Og;
use Drupal\user\Entity\User;

/**
 * Tests entity reference selection plugins.
 *
 * @group og
 */
class SelectionHandlerTest extends KernelTestBase {

  /**
   * Bundle of 'entity_test_no_label' entity.
   *
   * @var string
   */
  protected $bundle;

  /**
   * The selection handler.
   *
   * @var \Drupal\og\Plugin\EntityReferenceSelection\OgSelection.
   */
  protected $selectionHandler;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['system', 'user', 'field', 'entity_reference', 'node', 'og'];

  /**
   * @var User
   */
  protected $user1;

  /**
   * @var User
   */
  protected $user2;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Add membership and config schema.
    $this->installConfig(['og']);
    $this->installEntitySchema('og_membership');
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('system', 'sequences');

    // Create a group.
    NodeType::create([
      'type' => $group_type = Unicode::strtolower($this->randomMachineName()),
      'name' => $this->randomString(),
    ])->save();

    // Create a group content type.
    NodeType::create([
      'type' => $group_content_type = Unicode::strtolower($this->randomMachineName()),
      'name' => $this->randomString(),
    ])->save();

    // Define the group content as group.
    Og::groupManager()->addGroup('bundles', $group_type);

    // Add og audience field to group content.
    Og::CreateField(OG_AUDIENCE_FIELD, 'node', $group_content_type);

    // Get the storage of the field.
    $field_config = FieldConfig::loadByName('node', $group_content_type, OG_AUDIENCE_FIELD);
//    var_dump($field_config);
//    throw new \Exception('a');
    $this->selectionHandler = $this->container->get('plugin.manager.entity_reference_selection')->getSelectionHandler($field_config);

    // Creating the users accounts.
    $this->user1 = User::create(['name' => $this->randomString()])->save();
    $this->user2 = User::create(['name' => $this->randomString()])->save();

    // Create the groups.
    $storage = \Drupal::entityManager()->getStorage('node');

    $nodes = [
      [
        'title' => $this->randomString(),
        'type' => $group_type,
        'user' => $this->user1,
      ],
      [
        'title' => $this->randomString(),
        'type' => $group_type,
        'user' => $this->user1,
      ],
      [
        'title' => $this->randomString(),
        'type' => $group_type,
        'user' => $this->user2,
      ],
    ];

    foreach ($nodes as $node) {
      $storage->create($node)->save();
    }
  }

  /**
   * Tests values returned by SelectionInterface::getReferenceableEntities()
   * when the target entity type has no 'label' key.
   *
   * @param mixed $match
   *   The input text to be checked.
   *
   * @dataProvider providerTestCases
   */
  public function testSelectionHandler($field_status, array $match) {
    $Rfoo = $this->selectionHandler->setAccount($this->user1)->getReferenceableEntities();
    if ($field_status == 'my_group') {
      $this->assertSame(['group 1' , 'group 2'], $match);
    }
  }

  /**
   * Provides test cases for ::testSelectionHandler() test.
   *
   * @return array[]
   */
  public function providerTestCases() {
    return [
      ['my_group', ['group 1' , 'group 2']],
      ['other_groups', ['group 3']],
    ];
  }

}
