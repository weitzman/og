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
    $this->selectionHandler = $this->container->get('og.selection_manager')->getSelectionHandler($field_config);
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
  public function testSelectionHandler(array $match) {
    $this->assertEquals(get_class($this->selectionHandler), $match[0]);
    $this->assertEquals(get_class($this->selectionHandler->getHandler()), $match[1]);
    $this->assertEquals($this->selectionHandler->getConfiguration('handler'), $match[2]);
    $this->assertEquals($this->selectionHandler->getConfiguration('target_type'), $match[3]);
  }

  /**
   * Provides test cases for ::testSelectionHandler() test.
   *
   * @return array[]
   */
  public function providerTestCases() {
    return [
      [['Drupal\og\Plugin\EntityReferenceSelection\OgSelection', 'Drupal\node\Plugin\EntityReferenceSelection\NodeSelection', 'default:node', 'node']],
    ];
  }

}
