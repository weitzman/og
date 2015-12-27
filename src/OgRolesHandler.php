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
  public function grantRole(AccountInterface $account, OgRole $role) {
    $users_role = OgUsersRole::create();
    $users_role
      ->setUid($account)
      ->setRid($role)
      ->save();

    return $users_role;
  }

  public function revokeRole() {

  }

}
