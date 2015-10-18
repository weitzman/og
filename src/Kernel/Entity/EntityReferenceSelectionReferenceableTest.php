<?php

/**
 * @file
 * Contains \Drupal\Tests\system\Kernel\Entity\EntityReferenceSelectionReferenceableTest.
 */

namespace Drupal\Tests\og\Kernel\Entity;

use Drupal\Component\Utility\Unicode;
use Drupal\field\Tests\EntityReference\EntityReferenceTestTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\node\Entity\NodeType;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests entity reference selection plugins.
 *
 * @group og
 */
class OgAudienceField extends KernelTestBase {

  use EntityReferenceTestTrait;

  /**
   * Bundle of 'entity_test_no_label' entity.
   *
   * @var string
   */
  protected $bundle;

  /**
   * Labels to be tested.
   *
   * @var array
   */
  protected static $labels = ['abc', 'Xyz_', 'xyabz_', 'foo_', 'bar_', 'baz_', 'șz_', NULL, '<strong>'];

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

    $this->installEntitySchema('og_membership');

    // Create a new node-type.
    NodeType::create([
      'type' => $node_type = Unicode::strtolower($this->randomMachineName()),
      'name' => $this->randomString(),
    ])->save();
    return;
    // Create an entity reference field targeting 'entity_test_no_label'
    // entities.
    $field_name = Unicode::strtolower($this->randomMachineName());
    $this->createEntityReferenceField('node', $node_type, $field_name, $this->randomString(), 'og_membership');
    $field_config = FieldConfig::loadByName('node', $node_type, $field_name);
    $this->selectionHandler = $this->container->get('plugin.manager.entity_reference_selection')->getSelectionHandler($field_config);

    // Generate a bundle name to be used with 'entity_test_no_label'.
    $this->bundle = Unicode::strtolower($this->randomMachineName());

    // Create 6 entities to be referenced by the field.
    foreach (static::$labels as $name) {
      $storage->create([
        'id' => Unicode::strtolower($this->randomMachineName()),
        'name' => $name,
        'type' => $this->bundle,
      ])->save();
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
  public function testReferenceablesWithNoLabelKey($match) {
    $this->assertTrue($match == ['foo' , 'bar', 'a']);
    // Test ::getReferenceableEntities().
//    $referenceables = $this->selectionHandler->getReferenceableEntities($match, $match_operator, $limit);
//
//    // Number of returned items.
//    if (empty($count_limited)) {
//      $this->assertTrue(empty($referenceables[$this->bundle]));
//    }
//    else {
//      $this->assertSame(count($referenceables[$this->bundle]), $count_limited);
//    }
//
//    // Test returned items.
//    foreach ($items as $item) {
//      // SelectionInterface::getReferenceableEntities() always return escaped
//      // entity labels.
//      // @see \Drupal\Core\Entity\EntityReferenceSelection\SelectionInterface::getReferenceableEntities()
//      $item = is_string($item) ? Html::escape($item) : $item;
//      $this->assertTrue(array_search($item, $referenceables[$this->bundle]) !== FALSE);
//    }
//
//    // Test ::countReferenceableEntities().
//    $count_referenceables = $this->selectionHandler->countReferenceableEntities($match, $match_operator);
//    $this->assertSame($count_referenceables, $count_all);
  }

  /**
   * Provides test cases for ::testReferenceablesWithNoLabelKey() test.
   *
   * @return array[]
   */
  public function providerTestCases() {
    return [
      [['foo' , 'bar']],
    ];
  }

}
