<?php

/**
 * @file
 * Contains \Drupal\Tests\og\Unit\GetMatchingFieldTest.
 */

namespace Drupal\Tests\og\Unit;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\og\OgGroupAudienceHelper;

/**
 * Tests the OgGroupAudienceHelper::getMatchingField method.
 *
 * @group og
 *
 * @coversDefaultClass \Drupal\og\OgGroupAudienceHelper
 */
class GetMatchingFieldTest extends UnitTestCase {

  /**
   * @covers ::checkFieldCardinality
   */
  public function testFieldCardinality() {
    $field_name = 'test_field';

    $field_storage_definition_prophecy = $this->prophesize(FieldStorageDefinitionInterface::class);
    $field_storage_definition_prophecy->getCardinality()
      ->willReturn(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->shouldBeCalled();

    $field_definition_prophecy = $this->prophesize(FieldDefinitionInterface::class);
    $field_definition_prophecy->getFieldStorageDefinition()
      ->willReturn($field_storage_definition_prophecy->reveal())
      ->shouldBeCalled();

    $field_definition_prophecy->getType()
      ->willReturn('og_membership_reference')
      ->shouldBeCalled();

    $field_definition_prophecy->getSetting('target_type')
      ->willReturn('entity_test')
      ->shouldBeCalled();
    

    $entity_prophecy = $this->prophesize(ContentEntityInterface::class);

    $entity_prophecy->getFieldDefinition($field_name)
      ->willReturn($field_definition_prophecy->reveal());

    $entity_prophecy->bundle()
      ->shouldBeCalled();
    $entity_prophecy->getEntityTypeId()
      ->shouldBeCalled();

    // If the cardinality is unlimited getting a count of the field items is
    // never expected, so just check it's not called.
    $entity_prophecy->get($field_name)
      ->shouldNotBeCalled();

    $group_type_id = 'entity_test';
    $group_bundle = 'test1';


    $this->assertSame(OgGroupAudienceHelper::getMatchingField($entity_prophecy->reveal(), $group_type_id, $group_bundle), $expected);
  }

  /**
   * Data provider for testFieldCardinality.
   *
   * @return array
   *   The values to test which correspond to:
   *     - The count of existing items in the field.
   *     - Field cardinality.
   *     - The expected result where TRUE signifies the field may be populated
   *       by another value.
   */
  public function providerTestFieldCardinality() {
    return [
      [0, 1, TRUE],
      [1, 1, FALSE],
      [2, 1, FALSE],
      [1, 2, TRUE],
      [2, 2, FALSE],
      [3, 2, FALSE],
      [0, FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED, TRUE],
      [1, FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED, TRUE],
      [10, FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED, TRUE],
    ];
  }

}
