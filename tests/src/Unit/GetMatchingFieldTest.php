<?php

/**
 * @file
 * Contains \Drupal\Tests\og\Unit\GetMatchingFieldTest.
 */

namespace Drupal\Tests\og\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\og\OgGroupAudienceHelper;
use Prophecy\Argument;

/**
 * Tests the OgGroupAudienceHelper::getMatchingField method.
 *
 * @group og
 *
 * @coversDefaultClass \Drupal\og\OgGroupAudienceHelper
 */
class GetMatchingFieldTest extends UnitTestCase {


  /**
   * The entity type used for testing.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $entityType;

  /**
   * The entity manager used for testing.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $entityTypeManager;

  /**
   * The ID of the type of the entity under test.
   *
   * @var string
   */
  protected $entityTypeId;

  /**
   * The ID of the type of the bundle under test.
   *
   * @var string
   */
  protected $bundleId;

  /**
   * The group content entity.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface|\Prophecy\Prophecy\ProphecyInterface
   */
  protected $groupContent;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface|\Prophecy\Prophecy\ProphecyInterface
   */
  protected $entityFieldManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    $field_name = 'test_field';

    $this->entityTypeId = $this->randomMachineName();
    $this->bundleId = $this->randomMachineName();
    $this->provider = $this->randomMachineName();

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
      ->willReturn($this->entityTypeId)
      ->shouldBeCalled();


    $this->groupContent = $this->prophesize(ContentEntityInterface::class);
    $this->groupContent->getEntityTypeId()->willReturn($this->entityTypeId);
//    $this->groupContent->bundle()->willReturn($this->bundleId);

    $this->groupContent->getFieldDefinition($field_name)
      ->willReturn($field_definition_prophecy->reveal());

    $this->groupContent->bundle()
      ->shouldBeCalled();
    $this->groupContent->getEntityTypeId()
      ->shouldBeCalled();

    // If the cardinality is unlimited getting a count of the field items is
    // never expected, so just check it's not called.
    $this->groupContent->get($field_name)
      ->shouldNotBeCalled();

    $this->entityType = $this->prophesize(EntityTypeInterface::class);
    $this->entityType->isSubclassOf(FieldableEntityInterface::class)->willReturn(TRUE);


    $this->entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $this->entityTypeManager->getDefinition($this->entityTypeId)
      ->willReturn($this->entityType);

    $this->entityFieldManager = $this->prophesize(EntityFieldManagerInterface::class);
    // @todo: Return the correct array.
    $this->entityFieldManager->getFieldDefinitions($this->entityTypeId, $this->bundleId)->willReturn([]);

    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $this->entityTypeManager->reveal());
    $container->set('entity_field.manager', $this->entityFieldManager->reveal());

    \Drupal::setContainer($container);

  }

  /**
   * @covers ::getMatchingField
   */
  public function testGetMatchingField() {
    $this->assertSame(OgGroupAudienceHelper::getMatchingField($this->groupContent->reveal(), $this->entityTypeId, $this->bundleId), 'test_field');
  }

}
