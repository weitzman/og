<?php

/**
 * @file
 * Contains \Drupal\og\GroupRepository.
 */

namespace Drupal\og;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\Query\QueryFactoryInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

class GroupRepository {

  public function __construct(EntityFieldManagerInterface $entity_field_manager, QueryFactoryInterface $query_factory) {
    $this->entityFieldManager = $entity_field_manager;
    $this->queryFactory = $query_factory;
  }

  public function getAllGroupAudienceFields($entity_type_id, $bundle, $group_type_id = NULL, $group_bundle = NULL) {
    $filter = function (FieldDefinitionInterface $field_definition) use ($group_type_id, $group_bundle) {
      if (!Og::isGroupAudienceField($field_definition)) {
        // Not a group audience field.
        return FALSE;
      }
      $target_type = $field_definition->getFieldStorageDefinition()
        ->getSetting('target_type');

      if (isset($group_type_id) && $target_type != $group_type_id) {
        // Field doesn't reference this group type.
        return FALSE;
      }

      $handler_settings = $field_definition->getSetting('handler_settings');
      if (isset($group_bundle) && !empty($handler_settings['target_bundles']) && !in_array($group_bundle, $handler_settings['target_bundles'])) {
        return FALSE;
      }
      return TRUE;
    };
    return array_filter($this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle), $filter);
  }

}
