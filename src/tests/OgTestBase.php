<?php

/**
 * @file
 * Contains \Drupal\og\Tests\OgTestBase.
 */

namespace Drupal\og\Tests;

use Drupal\node\NodeInterface;
use Drupal\og\Controller\OG;
use Drupal\simpletest\WebTestBase;

/**
 * Set up the essential for OG testing.
 */
abstract class OgTestBase extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['og', 'node'];

  /**
   * @var NodeInterface
   */
  protected $groupNodeType;

  /**
   * @var NodeInterface
   */
  protected $groupContentNodeType;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Creating the content type.
    $this->groupNodeType = $this->drupalCreateContentType();
    $this->groupContentNodeType = $this->drupalCreateContentType();

    // Define content type as group and group content.
    OG::CreateField(OG_GROUP_FIELD, 'node', $this->groupNodeType->id());
    OG::CreateField(OG_AUDIENCE_FIELD, 'node', $this->groupNodeType->id());
  }

}
