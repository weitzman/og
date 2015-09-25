<?php

/**
 * @file
 * Contains \Drupal\node\Plugin\EntityReferenceSelection\OgSelection.
 */

namespace Drupal\og\Plugin\EntityReferenceSelection;

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
    $query->condition(OG_GROUP_FIELD, 1);

    $identifier_key = \Drupal::entityManager()->getDefinition($this->configuration['target_type'])->getKey('id');
    $user_groups = $this->getUserGroups();

    if ($this->configuration['handler_settings']['other_groups']) {
      $query->condition($identifier_key, $user_groups, 'IN');
    }
    else {

    }

    return $query;
  }

  /**
   * Get the user's groups.
   *
   * @return array
   */
  private function getUserGroups() {
    $ids = [];
    $other_groups = OG::getEntityGroups('user');

    foreach ($other_groups[$this->configuration['target_type']] as $group) {
      $ids[] = $group->id();
    }

    return $ids;
  }

}
