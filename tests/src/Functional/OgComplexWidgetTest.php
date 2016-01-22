<?php

/**
 * @file
 * Contains \Drupal\Tests\og\Functional\OgComplexWidgetTest.
 */

namespace Drupal\Tests\og\Functional;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\og\Entity\OgMembership;
use Drupal\og\Og;
use Drupal\og\OgGroupAudienceHelper;
use Drupal\simpletest\BrowserTestBase;

/**
 * Tests the complex widget.
 *
 * @group og
 */
class OgComplexWidgetTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   * @todo Remove field_ui
   */
  public static $modules = ['field_ui', 'node', 'og'];

  /**
   * {@inheritdoc}
   */
  function setUp() {
    parent::setUp();

    // Create a "group" node type and turn it into a group type.
    $this->drupalCreateContentType(['type' => 'group']);
    Og::groupManager()->addGroup('node', 'group');

    // Add a group audience field to the "post" node type, turning it into a
    // group content type.
    $this->drupalCreateContentType(['type' => 'post']);
    Og::createField(OgGroupAudienceHelper::DEFAULT_FIELD, 'node', 'post');

    // Make the group audience field visible in the default form display.
    // @todo Remove this once issue #144 is in.
    // @see https://github.com/amitaibu/og/issues/144
    /** @var EntityFormDisplayInterface $form_display */
    $form_display = \Drupal::entityManager()->getStorage('entity_form_display')->load('node.post.default');
    $widget = $form_display->getComponent('og_group_ref');
    $widget['type'] = 'og_complex';
    $widget['settings'] = [
      'match_operator' => 'CONTAINS',
      'size' => 60,
      'placeholder' => '',
    ];
    $form_display->setComponent('og_group_ref', $widget);
    $form_display->save();
  }

  /**
   * Tests "field modes" of the OG reference widget.
   */
  function testFieldModes() {
    $user1 = $this->drupalCreateUser(['administer group', 'access content', 'create post content',
      // @todo Remove these.
    'administer display modes', 'administer content types', 'administer node fields', 'administer node form display', 'administer node display', 'administer users', 'administer account settings', 'administer user display', 'bypass node access'
    ]);
    $user2 = $this->drupalCreateUser(['access content', 'create post content']);

    // Create two group nodes, one for each user.
    $settings = [
      'type' => 'group',
//      OG_GROUP_FIELD . '[und][0][value]' => 1,
    ];
    $settings['uid'] = $user1->uid;
    $group1 = $this->drupalCreateNode($settings);

    $settings['uid'] = $user2->uid;
    $group2 = $this->drupalCreateNode($settings);

    // Create a post in each group.
    $settings = [
      'type' => 'post',
    ];
    $settings['uid'] = $user1->uid;
    $post1 = $this->drupalCreateNode($settings);
    $this->addContentToGroup($post1, $group1);

    $settings['uid'] = $user2->uid;
    $post2 = $this->drupalCreateNode($settings);
    $this->addContentToGroup($post2, $group2);

    $this->drupalLogin($user1);
    $this->drupalGet("node/{$post1->id()}/edit");
    $this->drupalGet('admin/structure/types/manage/post/form-display');

    $fields = $this->xpath('//*[@id="edit-og-group-ref-und-0-default"]');
    $this->assertEqual($fields[0]->option['value'], '_none', '"Default" field mode is not required for administrator.');

    $fields = $this->xpath('//*[@id="edit-og-group-ref-und-0-admin-0-target-id"]');
    $this->assertTrue(strpos($fields[0]->attributes()->class[0], 'form-autocomplete'), '"Administrator field more is an autocomplete widget type."');

    $this->drupalLogin($user2);
    $this->drupalGet("node/$post2->id()/edit");

    $fields = $this->xpath('//*[@id="edit-og-group-ref-und-0-default"]');
    $this->assertEqual($fields[0]->option['value'], $group2->id(), '"Default" field mode is required.');
  }

  /**
   * Test non-accessible group IDs are saved, upon form submit.
   */
  function _testHiddenGroupIds() {
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
    og_group('node', $group1->id(), ['entity_type' => 'node', 'entity' => $post1]);
    og_group('node', $group2->id(), ['entity_type' => 'node', 'entity' => $post1]);

    $this->drupalLogin($user2);
    $this->drupalPost("node/$post1->id()/edit", [], 'Save');

    // Assert post still belongs to both groups, although user was able
    // to select only one.
    $gids = og_get_entity_groups('node', $post1);
    $this->assertEqual(count($gids['node']), 2, 'Hidden groups remained.');
  }

  /**
   * Test a non "administer group" user with pending membership, re-saving
   * user edit.
   */
  function _testUserEdit() {
    $user1 = $this->drupalCreateUser();
    $user2 = $this->drupalCreateUser();

    $settings = [
      'type' => 'group',
      OG_GROUP_FIELD . '[und][0][value]' => 1,
    ];
    $settings['uid'] = $user1->uid;
    $group1 = $this->drupalCreateNode($settings);

    og_group('node', $group1->id(), ['entity' => $user2, 'state' => OG_STATE_PENDING]);

    $this->drupalLogin($user2);
    $this->drupalPost("user/$user2->uid/edit", [], 'Save');

    $this->assertTrue(og_get_entity_groups('user', $user2, [OG_STATE_PENDING]), 'User membership was retained after user save.');
  }

  /**
   * Test multiple group-audience fields.
   */
  function _testMultipleFields() {
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

    og_group('node', $group1->id(), ['entity_type' => 'node', 'entity' => $post1, 'field_name' => OG_AUDIENCE_FIELD]);
    og_group('node', $group2->id(), ['entity_type' => 'node', 'entity' => $post1, 'field_name' => 'another_field']);

    $this->drupalLogin($user1);
    $this->drupalGet("node/$post1->id()/edit");

    // Assert correct selection in both fields.
    $this->assertOptionSelected('edit-og-group-ref-und-0-default', $group1->id());
    $this->assertOptionSelected('edit-another-field-und-0-default', $group2->id());
  }

  /**
   * Makes the given group content entity a member of the given group.
   *
   * @param ContentEntityInterface $member_entity
   *   The content entity to make a member.
   * @param ContentEntityInterface $group_entity
   *   The group entity that will host the content entity.
   */
  protected function addContentToGroup(ContentEntityInterface $member_entity, ContentEntityInterface $group_entity) {
    /** @var OgMembership $membership */
    Og::membershipStorage()->create(Og::membershipDefault())
      ->setFieldName(OgGroupAudienceHelper::DEFAULT_FIELD)
      ->setMemberEntityType($member_entity->getEntityTypeId())
      ->setMemberEntityId($member_entity->id())
      ->setGroupEntityType($group_entity->getEntityTypeId())
      ->setGroupEntityid($group_entity->id())
      ->save();
  }

}
