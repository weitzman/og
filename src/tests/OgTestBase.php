<?php

/**
 * @file
 * Contains \Drupal\og\Tests\OgTestBase.
 */

namespace Drupal\og\Tests;

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
  public static $modules = ['og'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // todo: set up things.
  }

}
