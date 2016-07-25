<?php

namespace Drupal\og;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\FieldStorageConfigInterface;

/**
 * A static helper class for OG.
 */
class Og {

  /**
   * Static cache for heavy queries.
   *
   * @var array
   */
  protected static $cache = [];

  /**
   * Create an organic groups field in a bundle.
   *
   * @param string $plugin_id
   *   The OG field plugin ID, which is also the default field name.
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle
   *   The bundle name.
   * @param array $settings
   *   (Optional) allow overriding the default definitions of the field storage
   *   config and field config.
   *   Allowed values:
   *   - field_storage_config: Array with values to override the field storage
   *     config definitions. Values should comply with
   *     FieldStorageConfig::create().
   *   - field_config: Array with values to override the field config
   *     definitions. Values should comply with FieldConfig::create()
   *   - form_display: Array with values to override the form display
   *     definitions.
   *   - view_display: Array with values to override the view display
   *     definitions.
   *
   * @return \Drupal\Core\Field\FieldConfigInterface
   *   The created or existing field config.
   */
  public static function createField($plugin_id, $entity_type, $bundle, array $settings = []) {
    $settings = $settings + [
      'field_storage_config' => [],
      'field_config' => [],
      'form_display' => [],
      'view_display' => [],
    ];

    $field_name = !empty($settings['field_name']) ? $settings['field_name'] : $plugin_id;

    // Get the field definition and add the entity info to it. By doing so
    // we validate the the field can be attached to the entity. For example,
    // the OG access module's field can be attached only to node entities, so
    // any other entity will throw an exception.
    /** @var \Drupal\og\OgFieldBase $og_field */
    $og_field = static::getFieldBaseDefinition($plugin_id)
      ->setFieldName($field_name)
      ->setBundle($bundle)
      ->setEntityType($entity_type);

    if (!FieldStorageConfig::loadByName($entity_type, $field_name)) {
      $field_storage_config = NestedArray::mergeDeep($og_field->getFieldStorageBaseDefinition(), $settings['field_storage_config']);
      FieldStorageConfig::create($field_storage_config)->save();
    }

    if (!$field_definition = FieldConfig::loadByName($entity_type, $bundle, $field_name)) {
      $field_config = NestedArray::mergeDeep($og_field->getFieldBaseDefinition(), $settings['field_config']);

      $field_definition = FieldConfig::create($field_config);
      $field_definition->save();

      // @todo: Verify this is still needed here.
      static::invalidateCache();
    }

    // Make the field visible in the default form display.
    /** @var EntityFormDisplayInterface $form_display */
    $form_display = \Drupal::entityTypeManager()->getStorage('entity_form_display')->load("$entity_type.$bundle.default");

    // If not found, create a fresh form display object. This is by design,
    // configuration entries are only created when an entity form display is
    // explicitly configured and saved.
    if (!$form_display) {
      $form_display = \Drupal::entityTypeManager()->getStorage('entity_form_display')->create([
        'targetEntityType' => $entity_type,
        'bundle' => $bundle,
        'mode' => 'default',
        'status' => TRUE,
      ]);
    }

    $form_display_definition = $og_field->getFormDisplayDefinition($settings['form_display']);

    $form_display->setComponent($plugin_id, $form_display_definition);
    $form_display->save();

    // Set the view display for the "default" view display.
    $view_display_definition = $og_field->getViewDisplayDefinition($settings['view_display']);

    /** @var EntityDisplayInterface $view_display */
    $view_display = \Drupal::entityTypeManager()->getStorage('entity_view_display')->load("$entity_type.$bundle.default");

    if (!$view_display) {
      $view_display = \Drupal::entityTypeManager()->getStorage('entity_view_display')->create([
        'targetEntityType' => $entity_type,
        'bundle' => $bundle,
        'mode' => 'default',
        'status' => TRUE,
      ]);
    }

    $view_display->setComponent($plugin_id, $view_display_definition);
    $view_display->save();

    // Refresh the group manager data, we have added a group type.
    static::groupManager()->resetGroupRelationMap();

    return $field_definition;
  }

  /**
   * Returns all group IDs associated with the given user.
   *
   * This is similar to \Drupal\og\Og::getGroupIds() but for users. The reason
   * there is a separate method for user entities is because the storage is
   * handled differently. For group content the relation to the group is stored
   * on a field attached to the content entity, while user memberships are
   * tracked in OgMembership entities.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user to get groups for.
   * @param array $states
   *   (optional) Array with the state to return. Defaults to active.
   *
   * @return array
   *   An associative array, keyed by group entity type, each item an array of
   *   group entity IDs.
   *
   * @see \Drupal\og\Og::getGroupIds()
   */
  public static function getUserGroupIds(AccountInterface $user, array $states = [OgMembershipInterface::STATE_ACTIVE]) {
    $group_ids = [];

    /** @var \Drupal\og\Entity\OgMembership[] $memberships */
    $memberships = static::getMemberships($user, $states);
    foreach ($memberships as $membership) {
      $group_ids[$membership->getGroupEntityType()][] = $membership->getGroupId();
    }

    return $group_ids;
  }

  /**
   * Returns all groups associated with the given user.
   *
   * This is similar to \Drupal\og\Og::getGroups() but for users. The reason
   * there is a separate method for user entities is because the storage is
   * handled differently. For group content the relation to the group is stored
   * on a field attached to the content entity, while user memberships are
   * tracked in OgMembership entities.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user to get groups for.
   * @param array $states
   *   (optional) Array with the states to return. Defaults to active.
   *
   * @return \Drupal\Core\Entity\EntityInterface[][]
   *   An associative array, keyed by group entity type, each item an array of
   *   group entities.
   *
   * @see \Drupal\og\Og::getGroups()
   * @see \Drupal\og\Og::getMemberships()
   */
  public static function getUserGroups(AccountInterface $user, array $states = [OgMembershipInterface::STATE_ACTIVE]) {
    $groups = [];

    foreach (static::getUserGroupIds($user, $states) as $entity_type => $entity_ids) {
      $groups[$entity_type] = \Drupal::entityTypeManager()->getStorage($entity_type)->loadMultiple($entity_ids);
    }

    return $groups;
  }

  /**
   * Returns the group membership for a given user and group.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user to get the membership for.
   * @param \Drupal\Core\Entity\EntityInterface $group
   *   The group to get the membership for.
   * @param array $states
   *   (optional) Array with the state to return. Defaults to active.
   * @param string $field_name
   *   (optional) The field name associated with the group.
   *
   * @return \Drupal\og\Entity\OgMembership|null
   *   The OgMembership entity, or NULL if the user is not a member of the
   *   group.
   */
  public static function getMembership(AccountInterface $user, EntityInterface $group, array $states = [OgMembershipInterface::STATE_ACTIVE], $field_name = NULL) {
    foreach (static::getMemberships($user, $states, $field_name) as $membership) {
      if ($membership->getGroupEntityType() === $group->getEntityTypeId() && $membership->getGroupId() === $group->id()) {
        return $membership;
      }
    }
  }

  /**
   * Returns the group memberships a user is associated with.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user to get groups for.
   * @param array $states
   *   (optional) Array with the state to return. Defaults to active.
   *
   * @return \Drupal\og\Entity\OgMembership[]
   *   An array of OgMembership entities, keyed by ID.
   */
  public static function getMemberships(AccountInterface $user, array $states = [OgMembershipInterface::STATE_ACTIVE]) {
    // Get a string identifier of the states, so we can retrieve it from cache.
    sort($states);
    $states_identifier = implode('|', array_unique($states));

    $identifier = [
      __METHOD__,
      $user->id(),
      $states_identifier,
    ];
    $identifier = implode(':', $identifier);

    // Return cached result if it exists.
    if (isset(static::$cache[$identifier])) {
      return static::$cache[$identifier];
    }

    $query = \Drupal::entityQuery('og_membership')
      ->condition('uid', $user->id());

    if ($states) {
      $query->condition('state', $states, 'IN');
    }

    $results = $query->execute();

    /** @var \Drupal\og\Entity\OgMembership[] $memberships */
    static::$cache[$identifier] = \Drupal::entityTypeManager()
      ->getStorage('og_membership')
      ->loadMultiple($results);

    return static::$cache[$identifier];
  }

  /**
   * Creates and an OG membership.
   *
   * @param \Drupal\Core\Entity\EntityInterface $group
   *   The group entity.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The group content entity.
   * @param string $membership_type
   *   (optional) The membership type. Defaults to OG_MEMBERSHIP_TYPE_DEFAULT.
   *
   * @return OgMembership
   *   The unsaved membership object.
   *
   * @throws \Drupal\og\OgException
   */
  public static function createMembership(EntityInterface $group, EntityInterface $entity, $membership_type = OG_MEMBERSHIP_TYPE_DEFAULT) {
    $group_entity_type_id = $group->getEntityTypeId();
    $group_bundle = $group->bundle();

    $entity_type_id = $entity->getEntityTypeId();
    $bundle = $entity->bundle();

    if ($group->isNew()) {
      throw new OgException(sprintf('Group of entity type %s is new, and cannot be used for creating membership.', $group_entity_type_id));
    }

    if ($group->isNew()) {
      throw new OgException(sprintf('Group content of entity type %s is new, and cannot be used for creating membership.', $entity_type_id));
    }

    // Validate entities are "group" and "group content"
    if (!self::isGroup($group_entity_type_id, $group_bundle)) {
      throw new OgException(sprintf('Entity type %s with ID %s is not an OG group.', $group_entity_type_id, $group->id()));
    }

    if (!self::isGroupContent($entity_type_id, $bundle)) {
      throw new OgException(sprintf('Entity type %s with ID %s is not a OG group content.', $group_entity_type_id, $group->id()));
    }

    /** @var OgMembershipInterface $membership */
    $membership = OgMembership::create(['type' => $membership_type]);
    $membership
      ->setEntity($entity)
      ->setGroup($group);

    return $membership;
  }

  /**
   * Returns all group IDs associated with the given group content entity.
   *
   * Do not use this to retrieve group IDs associated with a user entity. Use
   * Og::getUserGroups() instead.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The group content entity for which to return the associated groups.
   * @param string $group_type_id
   *   Filter results to only include group IDs of this entity type.
   * @param string $group_bundle
   *   Filter list to only include group IDs with this bundle.
   *
   * @return array
   *   An associative array, keyed by group entity type, each item an array of
   *   group entity IDs.
   *
   * @throws \InvalidArgumentException
   *   Thrown when a user entity is passed in.
   *
   * @see \Drupal\og\Og::getUserGroups()
   */
  public static function getGroupIds(EntityInterface $entity, $group_type_id = NULL, $group_bundle = NULL) {
    // This does not work for user entities.
    if ($entity->getEntityTypeId() === 'user') {
      throw new \InvalidArgumentException('\Drupal\og\Og::getGroupIds() cannot be used for user entities. Use \Drupal\og\Og::getUserGroups() instead.');
    }

    $identifier = [
      __METHOD__,
      $entity->id(),
      $group_type_id,
      $group_bundle,
    ];

    $identifier = implode(':', $identifier);

    if (isset(static::$cache[$identifier])) {
      // Return cached values.
      return static::$cache[$identifier];
    }

    $group_ids = [];

    $fields = OgGroupAudienceHelper::getAllGroupAudienceFields($entity->getEntityTypeId(), $entity->bundle(), $group_type_id, $group_bundle);
    foreach ($fields as $field) {
      $target_type = $field->getFieldStorageDefinition()->getSetting('target_type');

      // Optionally filter by group type.
      if (!empty($group_type_id) && $group_type_id !== $target_type) {
        continue;
      }

      // Compile a list of group target IDs.
      $target_ids = array_map(function ($value) {
        return $value['target_id'];
      }, $entity->get($field->getName())->getValue());

      if (empty($target_ids)) {
        continue;
      }

      // Query the database to get the actual list of groups. The target IDs may
      // contain groups that no longer exist. Entity reference doesn't clean up
      // orphaned target IDs.
      $entity_type = \Drupal::entityTypeManager()->getDefinition($target_type);
      $query = \Drupal::entityQuery($target_type)
        ->condition($entity_type->getKey('id'), $target_ids, 'IN');

      // Optionally filter by group bundle.
      if (!empty($group_bundle)) {
        $query->condition($entity_type->getKey('bundle'), $group_bundle);
      }

      $group_ids = NestedArray::mergeDeep($group_ids, [$target_type => $query->execute()]);
    }

    static::$cache[$identifier] = $group_ids;

    return $group_ids;
  }

  /**
   * Returns all groups that are associated with the given group content entity.
   *
   * Do not use this to retrieve group memberships for a user entity. Use
   * Og::getUserGroups() instead.
   *
   * The reason there are separate method for group content and user entities is
   * because the storage is handled differently. For group content the relation
   * to the group is stored on a field attached to the content entity, while
   * user memberships are tracked in OgMembership entities.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The group content entity for which to return the groups.
   * @param string $group_type_id
   *   Filter results to only include groups of this entity type.
   * @param string $group_bundle
   *   Filter results to only include groups of this bundle.
   *
   * @return \Drupal\Core\Entity\EntityInterface[][]
   *   An associative array, keyed by group entity type, each item an array of
   *   group entities.
   *
   * @see \Drupal\og\Og::getUserGroups()
   */
  public static function getGroups(EntityInterface $entity, $group_type_id = NULL, $group_bundle = NULL) {
    $groups = [];

    foreach (static::getGroupIds($entity, $group_type_id, $group_bundle) as $entity_type => $entity_ids) {
      $groups[$entity_type] = \Drupal::entityTypeManager()->getStorage($entity_type)->loadMultiple($entity_ids);
    }

    return $groups;
  }

  /**
   * Returns the number of groups associated with a given group content entity.
   *
   * Do not use this to retrieve the group membership count for a user entity.
   * Use count(Og::GetEntityGroups()) instead.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The group content entity for which to count the associated groups.
   * @param string $group_type_id
   *   Only count groups of this entity type.
   * @param string $group_bundle
   *   Only count groups of this bundle.
   *
   * @return int
   *   The number of associated groups.
   */
  public static function getGroupCount(EntityInterface $entity, $group_type_id = NULL, $group_bundle = NULL) {
    return array_reduce(static::getGroupIds($entity, $group_type_id, $group_bundle), function ($carry, $item) {
      return $carry + count($item);
    }, 0);
  }

  /**
   * Returns all the group content IDs associated with a given group entity.
   *
   * This does not return information about users that are members of the given
   * group.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The group entity for which to return group content IDs.
   * @param array $entity_types
   *   Optional list of group content entity types for which to return results.
   *   If an empty array is passed, the group content is not filtered. Defaults
   *   to an empty array.
   *
   * @return array
   *   An associative array, keyed by group content entity type, each item an
   *   array of group content entity IDs.
   */
  public static function getGroupContentIds(EntityInterface $entity, array $entity_types = []) {
    $group_content = [];

    // Retrieve the fields which reference our entity type and bundle.
    $query = \Drupal::entityQuery('field_storage_config')
      ->condition('type', OgGroupAudienceHelper::GROUP_REFERENCE);

    // Optionally filter group content entity types.
    if ($entity_types) {
      $query->condition('entity_type', $entity_types, 'IN');
    }

    /** @var \Drupal\field\FieldStorageConfigInterface[] $fields */
    $fields = array_filter(FieldStorageConfig::loadMultiple($query->execute()), function (FieldStorageConfigInterface $field) use ($entity) {
      $type_matches = $field->getSetting('target_type') === $entity->getEntityTypeId();
      // If the list of target bundles is empty, it targets all bundles.
      $bundle_matches = empty($field->getSetting('target_bundles')) || in_array($entity->bundle(), $field->getSetting('target_bundles'));
      return $type_matches && $bundle_matches;
    });

    // Compile the group content.
    foreach ($fields as $field) {
      $group_content_entity_type = $field->getTargetEntityTypeId();

      // Group the group content per entity type.
      if (!isset($group_content[$group_content_entity_type])) {
        $group_content[$group_content_entity_type] = [];
      }

      // Query all group content that references the group through this field.
      $results = \Drupal::entityQuery($group_content_entity_type)
        ->condition($field->getName() . '.target_id', $entity->id())
        ->execute();

      $group_content[$group_content_entity_type] = array_merge($group_content[$group_content_entity_type], $results);
    }

    return $group_content;
  }

  /**
   * Returns whether a user belongs to a group.
   *
   * @param \Drupal\Core\Entity\EntityInterface $group
   *   The group entity.
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user to test the membership for.
   * @param array $states
   *   (optional) Array with the membership states to check the membership.
   *   Defaults to active memberships.
   *
   * @return bool
   *   TRUE if the entity (e.g. the user or node) belongs to a group with
   *   a certain state.
   */
  public static function isMember(EntityInterface $group, AccountInterface $user, $states = [OgMembershipInterface::STATE_ACTIVE]) {
    $group_ids = static::getUserGroupIds($user, $states);
    $entity_type_id = $group->getEntityTypeId();
    return !empty($group_ids[$entity_type_id]) && in_array($group->id(), $group_ids[$entity_type_id]);
  }

  /**
   * Returns whether a user belongs to a group with a pending status.
   *
   * @param \Drupal\Core\Entity\EntityInterface $group
   *   The group entity.
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user entity.
   *
   * @return bool
   *   True if the membership is pending.
   *
   * @see \Drupal\og\Og::isMember
   */
  public static function isMemberPending(EntityInterface $group, AccountInterface $user) {
    return static::isMember($group, $user, [OgMembershipInterface::STATE_PENDING]);
  }

  /**
   * Returns whether an entity belongs to a group with a blocked status.
   *
   * @param \Drupal\Core\Entity\EntityInterface $group
   *   The group entity.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to test the membership for.
   *
   * @return bool
   *   True if the membership is blocked.
   *
   * @see \Drupal\og\Og::isMember
   */
  public static function isMemberBlocked(EntityInterface $group, EntityInterface $entity) {
    return static::isMember($group, $entity, [OgMembershipInterface::STATE_BLOCKED]);
  }

  /**
   * Check if the given entity type and bundle is a group.
   *
   * @param string $entity_type_id
   *   The entity type.
   * @param string $bundle_id
   *   The bundle name.
   *
   * @return bool
   *   True or false if the given entity is group.
   */
  public static function isGroup($entity_type_id, $bundle_id) {
    return static::groupManager()->isGroup($entity_type_id, $bundle_id);
  }

  /**
   * Check if the given entity type and bundle is a group content.
   *
   * This is just a convenience wrapper around Og::getAllGroupAudienceFields().
   *
   * @param string $entity_type_id
   *   The entity type.
   * @param string $bundle_id
   *   The bundle name.
   *
   * @return bool
   *   True or false if the given entity is group content.
   */
  public static function isGroupContent($entity_type_id, $bundle_id) {
    return (bool) OgGroupAudienceHelper::getAllGroupAudienceFields($entity_type_id, $bundle_id);
  }

  /**
   * Sets an entity type instance as being an OG group.
   *
   * @param string $entity_type_id
   *   The entity type.
   * @param string $bundle_id
   *   The bundle name.
   */
  public static function addGroup($entity_type_id, $bundle_id) {
    static::groupManager()->addGroup($entity_type_id, $bundle_id);
  }

  /**
   * Removes an entity type instance as being an OG group.
   *
   * @param string $entity_type_id
   *   The entity type.
   * @param string $bundle_id
   *   The bundle name.
   *
   * @return bool
   *   True or false if the action succeeded.
   */
  public static function removeGroup($entity_type_id, $bundle_id) {
    return static::groupManager()->removeGroup($entity_type_id, $bundle_id);
  }

  /**
   * Returns the group manager instance.
   *
   * @return \Drupal\og\GroupManager
   *   Returns the group manager.
   */
  public static function groupManager() {
    // @todo store static reference for this?
    return \Drupal::service('og.group.manager');
  }

  /**
   * Return the og permission handler instance.
   *
   * @return \Drupal\og\OgPermissionHandler
   *   Returns the OG permissions handler.
   */
  public static function permissionHandler() {
    return \Drupal::service('og.permissions');
  }

  /**
   * Invalidate cache.
   *
   * @param array $group_ids
   *   Array with group IDs that their cache should be invalidated.
   */
  public static function invalidateCache(array $group_ids = array()) {
    // @todo We should not be using drupal_static() review and remove.
    // Reset static cache.
    $caches = array(
      'og_user_access',
      'og_user_access_alter',
      'og_role_permissions',
      'og_get_user_roles',
      'og_get_permissions',
      'og_get_entity_groups',
      'og_get_membership',
      'og_get_field_og_membership_properties',
      'og_get_user_roles',
    );

    foreach ($caches as $cache) {
      drupal_static_reset($cache);
    }

    // @todo Consider using a reset() method.
    static::$cache = [];

    // Invalidate the entity property cache.
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();

    // Let other OG modules know we invalidate cache.
    \Drupal::moduleHandler()->invokeAll('og_invalidate_cache', $group_ids);
  }

  /**
   * Get an OG field base definition.
   *
   * @param string $plugin_id
   *   The plugin ID, which is also the default field name.
   *
   * @throws \Exception
   *
   * @return OgFieldBase|bool
   *   An array with the field storage config and field config definitions, or
   *   FALSE if none found.
   */
  protected static function getFieldBaseDefinition($plugin_id) {
    /** @var OgFieldsPluginManager $plugin_manager */
    $plugin_manager = \Drupal::service('plugin.manager.og.fields');
    if (!$field_config = $plugin_manager->getDefinition($plugin_id)) {
      throw new \Exception("The Organic Groups field with plugin ID $plugin_id is not a valid plugin.");
    }

    return $plugin_manager->createInstance($plugin_id);
  }

  /**
   * Get the selection handler for an audience field attached to entity.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   * @param array $options
   *   Overriding the default options of the selection handler.
   *
   * @return OgSelection
   *   Returns the OG selection handler.
   *
   * @throws \Exception
   */
  public static function getSelectionHandler(FieldDefinitionInterface $field_definition, array $options = []) {
    if (!OgGroupAudienceHelper::isGroupAudienceField($field_definition)) {
      $field_name = $field_definition->getName();
      throw new \Exception("The field $field_name is not an audience field.");
    }

    $options = NestedArray::mergeDeep([
      'target_type' => $field_definition->getFieldStorageDefinition()->getSetting('target_type'),
      'handler' => $field_definition->getSetting('handler'),
      'handler_settings' => [
        'field_mode' => 'default',
      ],
    ], $options);

    // Deep merge the handler settings.
    $options['handler_settings'] = NestedArray::mergeDeep($field_definition->getSetting('handler_settings'), $options['handler_settings']);

    return \Drupal::service('plugin.manager.entity_reference_selection')->createInstance('og:default', $options);
  }

  /**
   * Resets the static cache.
   */
  public static function reset() {
    static::$cache = [];
  }

}
