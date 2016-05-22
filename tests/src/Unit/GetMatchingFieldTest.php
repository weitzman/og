<?php

/**
 * @file
 * Contains \Drupal\Tests\og\Unit\GetMatchingFieldTest.
 */

namespace Drupal\Tests\og\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\BaseFieldDefinition;
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
  protected $entityManager;

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
   * @var
   */
  protected $groupContent;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
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


    $this->groupContent = $this->prophesize(ContentEntityInterface::class);

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

    $this->entityTypeId = $this->randomMachineName();
    $this->bundleId = $this->randomMachineName();

    $this->entityType = $this->getMock('\Drupal\Core\Entity\EntityTypeInterface');
    $this->entityType->expects($this->any())
      ->method('getProvider')
      ->will($this->returnValue('entity_test'));

    $this->entityManager = $this->getMock('\Drupal\Core\Entity\EntityManagerInterface');
    $this->entityManager->expects($this->any())
      ->method('getDefinition')
      ->with($this->entityTypeId)
      ->will($this->returnValue($this->entityType));

    $container = new ContainerBuilder();
    $container->set('entity.manager', $this->entityManager);
    \Drupal::setContainer($container);

  }

  /**
   * @covers ::getMatchingField
   */
  public function testGetMatchingField() {
    $this->assertSame(OgGroupAudienceHelper::getMatchingField($this->groupContent->reveal(), $this->entityTypeId, $this->bundleId), 'test_field');
  }

}
