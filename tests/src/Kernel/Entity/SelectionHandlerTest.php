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
    $this->selectionHandler = Og::getSelectionHandler('node', $group_content_type, OG_AUDIENCE_FIELD);
  }

  /**
   * Testing the OG manager selection handler.
   *
   * We need to verify that the manager selection handler will use the default
   * selection manager of the entity which the audience field referencing to.
   *
   * i.e: When the field referencing to node, we need verify we got the default
   * node selection handler.
   *
   * @param mixed $match
   *   The input text to be checked.
   *
   * @dataProvider providerTestCases
   */
  public function testSelectionHandler(array $match) {
    $this->assertEquals(get_class($this->selectionHandler->getSelectionHandler()), $match[0]);
    $this->assertEquals($this->selectionHandler->getConfiguration('handler'), $match[1]);
    $this->assertEquals($this->selectionHandler->getConfiguration('target_type'), $match[2]);
  }

  /**
   * Provides test cases for ::testSelectionHandler() test.
   *
   * @return array[]
   */
  public function providerTestCases() {
    return [
      [['Drupal\node\Plugin\EntityReferenceSelection\NodeSelection', 'default:node', 'node']],
    ];
  }

}
