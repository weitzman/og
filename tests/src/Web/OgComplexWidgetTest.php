<?php
/**
 * @file
 * Contains \Drupal\og\Tests\OgComplexWidgetTest.
 */

namespace Drupal\og\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Tests the complex widget.
 *
 * @group og
 */
class OgComplexWidgetTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['og'];

  /**
   * {@inheritdoc}
   */
  function setUp() {
    // Add OG group field to a the node's "group" bundle.
    $this->drupalCreateContentType(array('type' => 'group'));
    og_create_field(OG_GROUP_FIELD, 'node', 'group');

    // Add OG audience field to the node's "post" bundle.
    $this->drupalCreateContentType(array('type' => 'post'));
    $og_field = og_fields_info(OG_AUDIENCE_FIELD);
    $og_field['instance']['required'] = TRUE;
    og_create_field(OG_AUDIENCE_FIELD, 'node', 'post', $og_field);
  }

  /**
   * Tests "field modes" of the OG reference widget.
   */
  function testFieldModes() {
    $user1 = $this->drupalCreateUser(['administer group', 'access content', 'create post content']);
    $user2 = $this->drupalCreateUser(['access content', 'create post content']);

    // Create group nodes.
    $settings = [
      'type' => 'group',
      OG_GROUP_FIELD . '[und][0][value]' => 1,
    ];
    $settings['uid'] = $user1->uid;
    $group1 = $this->drupalCreateNode($settings);

    $settings['uid'] = $user2->uid;
    $group2 = $this->drupalCreateNode($settings);

    $settings = [
      'type' => 'post',
    ];
    $settings['uid'] = $user1->uid;
    $post1 = $this->drupalCreateNode($settings);
    og_group('node', $group1->nid, array('entity_type' => 'node', 'entity' => $post1));

    $settings['uid'] = $user2->uid;
    $post2 = $this->drupalCreateNode($settings);
    og_group('node', $group2->nid, array('entity_type' => 'node', 'entity' => $post2));

    $this->drupalLogin($user1);
    $this->drupalGet("node/$post1->nid/edit");

    $fields = $this->xpath('//*[@id="edit-og-group-ref-und-0-default"]');
    $this->assertEqual($fields[0]->option['value'], '_none', '"Default" field mode is not required for administrator.');

    $fields = $this->xpath('//*[@id="edit-og-group-ref-und-0-admin-0-target-id"]');
    $this->assertTrue(strpos($fields[0]->attributes()->class[0], 'form-autocomplete'), '"Administrator field more is an autocomplete widget type."');

    $this->drupalLogin($user2);
    $this->drupalGet("node/$post2->nid/edit");

    $fields = $this->xpath('//*[@id="edit-og-group-ref-und-0-default"]');
    $this->assertEqual($fields[0]->option['value'], $group2->nid, '"Default" field mode is required.');
  }

  /**
   * Test non-accessible group IDs are saved, upon form submit.
   */
  function testHiddenGroupIds() {
    $user1 = $this->drupalCreateUser(['administer group', 'access content', 'create post content']);
    $user2 = $this->drupalCreateUser(['access content', 'create post content']);

    // Create group nodes.
    $settings = [
      'type' => 'group',
      OG_GROUP_FIELD . '[und][0][value]' => 1,
    ];
    $settings['uid'] = $user1->uid;
    $group1 = $this->drupalCreateNode($settings);

    $settings['uid'] = $user2->uid;
    $group2 = $this->drupalCreateNode($settings);

    $settings = [
      'type' => 'post',
    ];
    $settings['uid'] = $user1->uid;
    $post1 = $this->drupalCreateNode($settings);
    og_group('node', $group1->nid, ['entity_type' => 'node', 'entity' => $post1]);
    og_group('node', $group2->nid, ['entity_type' => 'node', 'entity' => $post1]);

    $this->drupalLogin($user2);
    $this->drupalPost("node/$post1->nid/edit", [], 'Save');

    // Assert post still belongs to both groups, although user was able
    // to select only one.
    $gids = og_get_entity_groups('node', $post1);
    $this->assertEqual(count($gids['node']), 2, 'Hidden groups remained.');
  }

  /**
   * Test a non "administer group" user with pending membership, re-saving
   * user edit.
   */
  function testUserEdit() {
    $user1 = $this->drupalCreateUser();
    $user2 = $this->drupalCreateUser();

    $settings = [
      'type' => 'group',
      OG_GROUP_FIELD . '[und][0][value]' => 1,
    ];
    $settings['uid'] = $user1->uid;
    $group1 = $this->drupalCreateNode($settings);

    og_group('node', $group1->nid, ['entity' => $user2, 'state' => OG_STATE_PENDING]);

    $this->drupalLogin($user2);
    $this->drupalPost("user/$user2->uid/edit", [], 'Save');

    $this->assertTrue(og_get_entity_groups('user', $user2, [OG_STATE_PENDING]), 'User membership was retained after user save.');
  }

  /**
   * Test multiple group-audience fields.
   */
  function testMultipleFields() {
    // Add another group-audience field.
    $og_field = og_fields_info(OG_AUDIENCE_FIELD);
    og_create_field('another_field', 'node', 'post', $og_field);

    $user1 = $this->drupalCreateUser();

    // Create a group.
    $settings = [
      'type' => 'group',
      OG_GROUP_FIELD . '[und][0][value]' => 1,
      'uid' => $user1->uid,
    ];
    $group1 = $this->drupalCreateNode($settings);
    $group2 = $this->drupalCreateNode($settings);

    // Create group content.
    $settings = [
      'type' => 'post',
      'uid' => $user1->uid,
    ];
    $post1 = $this->drupalCreateNode($settings);

    og_group('node', $group1->nid, ['entity_type' => 'node', 'entity' => $post1, 'field_name' => OG_AUDIENCE_FIELD]);
    og_group('node', $group2->nid, ['entity_type' => 'node', 'entity' => $post1, 'field_name' => 'another_field']);

    $this->drupalLogin($user1);
    $this->drupalGet("node/$post1->nid/edit");

    // Assert correct selection in both fields.
    $this->assertOptionSelected('edit-og-group-ref-und-0-default', $group1->nid);
    $this->assertOptionSelected('edit-another-field-und-0-default', $group2->nid);
  }

}
