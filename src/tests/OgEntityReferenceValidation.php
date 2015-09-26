<?php

/**
 * @file
 * Contains \Drupal\og\Tests\NodeTitleTest.
 */

namespace Drupal\og\Tests;
use Drupal\og\Controller\OG;

/**
 * Tests entity reference validation.
 *
 * @group og
 */
class OgEntityReferenceValidation extends OgTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['og', 'node'];

  /**
   *  Creates a group and a non group node and verify the validation.
   */
  function testEntityReference() {
    // Define content type as group and group content.
    $group1 = $this->drupalCreateNode(['type' => $this->groupNodeType->id()]);
    $group2 = $this->drupalCreateNode(['type' => $this->groupNodeType->id()]);

    debug('a');

  }
}
