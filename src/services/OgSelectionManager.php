<?php

/**
 * @file
 * Contains \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManager.
 */

namespace Drupal\og\services;

use Drupal\Component\Plugin\FallbackPluginManagerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManager;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\og\Og;

/**
 * Plugin type manager for Entity Reference Selection plugins.
 *
 * @see \Drupal\Core\Entity\Annotation\EntityReferenceSelection
 * @see \Drupal\Core\Entity\EntityReferenceSelection\SelectionInterface
 * @see plugin_api
 */
class OgSelectionManager extends SelectionPluginManager  {

  public function getSelectionHandler(FieldDefinitionInterface $field_definition, EntityInterface $entity = NULL) {
    $parent = parent::getSelectionHandler($field_definition, $entity);

    if (!Og::isAudienceField($field_definition->getFieldStorageDefinition()->getName())) {
      return $parent;
    }

    $options = array(
      'target_type' => $field_definition->getFieldStorageDefinition()->getSetting('target_type'),
      'handler' => $field_definition->getSetting('handler'),
      'handler_settings' => $field_definition->getSetting('handler_settings') ?: array(),
      'entity' => $entity,
    );

    return $this->createInstance('og:default', $options);
  }

}
