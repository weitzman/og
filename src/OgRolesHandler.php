<?php

/**
 * @file
 * Contains /Drupal/og/OgRolesHandler
 */

namespace Drupal\og;

use Drupal\Core\Session\AccountInterface;
use Drupal\og\Entity\OgRole;
use Drupal\og\Entity\OgUsersRole;

class OgRolesHandler {

  /**
   * Grant role to user.
   *
   * @param $account
   *   The account instance.
   * @param $role
   *   The role instance.
   *
   * @return OgUsersRole
   */
  public function assignRole(AccountInterface $account, OgRole $role) {
    $users_role = OgUsersRole::create();
    $users_role
      ->setUid($account)
      ->setRid($role)
      ->save();

    return $users_role;
  }

  public function revokeRole() {

  }

  /**
   * Get the assigned roles to a user.
   *
   * @param $uid
   *   The user ID.
   *
   * @return OgUsersRole[]
   *   Array of assigned roles to a user.
   */
  public function getAssignedRoles($uid) {
    $query = \Drupal::entityQuery('og_users_role');
    $results = $query
      ->condition('uid', $uid)
      ->execute();

    if (empty($results)) {
      return [];
    }

    return \Drupal::entityTypeManager()->getStorage('og_users_role')->loadMultiple($results);
  }

}
