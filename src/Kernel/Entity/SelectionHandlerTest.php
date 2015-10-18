<?php

/**
 * @file
 * Contains \Drupal\Tests\og\Kernel\Entity\SelectionHandlerTest.
 */

namespace Drupal\Tests\og\Kernel\Entity;

use Drupal\Component\Utility\Unicode;
use Drupal\node\Entity\NodeType;
use Drupal\KernelTests\KernelTestBase;
use Drupal\og\Og;

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
   * @var \Drupal\Core\Entity\EntityReferenceSelection\SelectionInterface.
   */
  protected $selectionHandler;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['system', 'user', 'field', 'entity_reference', 'node', 'og'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Add membership and config schema.
    $this->installConfig(['og']);
    $this->installEntitySchema('og_membership');

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

    // Add og audience field to group conetent.
    \Drupal\og\Controller\OG::CreateField(OG_AUDIENCE_FIELD, 'node', $group_content_type);
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
//      ['other_groups', ['group 3']],
    ];
  }

}
