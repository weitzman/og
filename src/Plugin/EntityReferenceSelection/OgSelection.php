<?php

/**
 * @file
 * Contains \Drupal\node\Plugin\EntityReferenceSelection\OgSelection.
 */

namespace Drupal\og\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\Plugin\EntityReferenceSelection\SelectionBase;
use Drupal\og\Controller\OG;

/**
 * Provide default OG selection handler.
 *
 * @EntityReferenceSelection(
 *   id = "default:og",
 *   label = @Translation("OG selection"),
 *   entity_types = {"node"},
 *   group = "default",
 *   weight = 1
 * )
 */
class OgSelection extends SelectionBase {

  private $targetType;

  /**
   * Overrides the basic entity query object. Return only group in the matching
   * results.
   *
   * @param string|null $match
   *   (Optional) Text to match the label against. Defaults to NULL.
   * @param string $match_operator
   *   (Optional) The operation the matching should be done with. Defaults
   *   to "CONTAINS".
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   The EntityQuery object with the basic conditions and sorting applied to
   *   it.
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    $query = parent::buildEntityQuery($match, $match_operator);
    $identifier_key = \Drupal::entityManager()->getDefinition($this->configuration['target_type'])->getKey('id');
    $this->targetType = $this->configuration['target_type'];
    $user_groups = $this->getUserGroups();

    $query->condition(OG_GROUP_FIELD, 1);

    if ($this->configuration['handler_settings']['other_groups'] && $user_groups) {
      $ids = [];

      // Don't include the groups, the user doesn't have create permission.
      foreach ($user_groups as $delta => $group) {
        if ($group->access('create')) {
          $ids[] = $group->id();
        }
      }

      if ($ids) {
        $query->condition($identifier_key, $ids, 'IN');
      }
    }
    else {
//      if ($user_groups && !empty($this->instance) && $this->instance['entity_type'] == 'node') {
//        // Determine which groups should be selectable.
//        $node = $this->entity;
//        $node_type = $this->instance['bundle'];
//        $ids = array();
//        foreach ($user_groups as $gid) {
//          // Check if user has "create" permissions on those groups.
//          // If the user doesn't have create permission, check if perhaps the
//          // content already exists and the user has edit permission.
//          if (og_user_access($group_type, $gid, "create $node_type content")) {
//            $ids[] = $gid;
//          }
//          elseif (!empty($node->nid) && (og_user_access($group_type, $gid, "update any $node_type content") || ($user->uid == $node->uid && og_user_access($group_type, $gid, "update own $node_type content")))) {
//            $node_groups = isset($node_groups) ? $node_groups : og_get_entity_groups('node', $node->nid);
//            if (in_array($gid, $node_groups[$group_type])) {
//              $ids[] = $gid;
//            }
//          }
//        }
//      }
//      else {
//        $ids = $user_groups;
//      }
//      if ($ids) {
//        $query->propertyCondition($entity_info['entity keys']['id'], $ids, 'IN');
//      }
//      else {
//        // User doesn't have permission to select any group so falsify this
//        // query.
//        $query->propertyCondition($entity_info['entity keys']['id'], -1, '=');
//      }
    }

    return $query;
  }

  /**
   * Get the user's groups.
   *
   * @return ContentEntityInterface[]
   */
  private function getUserGroups() {
    $other_groups = OG::getEntityGroups('user');
    return $other_groups[$this->configuration['target_type']];
  }

}
