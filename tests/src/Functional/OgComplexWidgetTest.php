<?php

/**
 * @file
 * Contains \Drupal\Tests\og\Functional\OgComplexWidgetTest.
 */

namespace Drupal\Tests\og\Functional;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\KernelTests\AssertLegacyTrait;
use Drupal\og\Entity\OgMembership;
use Drupal\og\Og;
use Drupal\og\OgGroupAudienceHelper;
use Drupal\og\OgMembershipInterface;
use Drupal\simpletest\AssertContentTrait;
use Drupal\simpletest\BrowserTestBase;
use Drupal\simpletest\ContentTypeCreationTrait;
use Drupal\simpletest\NodeCreationTrait;

/**
 * Tests the complex widget.
 *
 * @group og
 */
class OgComplexWidgetTest extends BrowserTestBase {

  use AssertContentTrait;
  // @todo Convert legacy asserts and remove legacy trait.
  use AssertLegacyTrait;
  use ContentTypeCreationTrait;
  use NodeCreationTrait;

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
    $this->createContentType(['type' => 'group']);
    Og::groupManager()->addGroup('node', 'group');

    // Add a group audience field to the "post" node type, turning it into a
    // group content type.
    $this->createContentType(['type' => 'post']);
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
    $user1 = $this->drupalCreateUser(['administer group', 'access content', 'create post content']);
    $user2 = $this->drupalCreateUser(['access content', 'create post content']);

    // Create two group nodes, one for each user.
    $settings = [
      'type' => 'group',
      // @todo I think this is obsolete.
      // OG_GROUP_FIELD . '[und][0][value]' => 1,
      'uid' => $user1->id(),
    ];
    $group1 = $this->createNode($settings);

    $settings['uid'] = $user2->id();
    $group2 = $this->createNode($settings);

    // Create a post in each group.
    $settings = [
      'type' => 'post',
      // @todo I think this is obsolete.
      // OG_GROUP_FIELD . '[und][0][value]' => 1,
      'uid' => $user1->id(),
    ];
    $post1 = $this->createNode($settings);
    $this->addContentToGroup($post1, $group1);

    $settings['uid'] = $user2->id();
    $post2 = $this->createNode($settings);
    $this->addContentToGroup($post2, $group2);

    $this->drupalLogin($user1);
    $this->drupalGet("node/{$post1->id()}/edit");

    // @todo Not sure what this is supposed to be testing. What is "default
    // field mode"?
    // $fields = $this->xpath('//*[@id="edit-og-group-ref-und-0-default"]');
    // $this->assertEqual($fields[0]->option['value'], '_none', '"Default" field mode is not required for administrator.');

    // @todo Update selector.
    // $fields = $this->xpath('//*[@id="edit-og-group-ref-und-0-admin-0-target-id"]');
    // $this->assertTrue(strpos($fields[0]->attributes()->class[0], 'form-autocomplete'), '"Administrator field more is an autocomplete widget type."');

    $this->drupalLogin($user2);
    // @todo: Seems to be a bug here, the page returns access denied.
    $this->drupalGet("node/{$post2->id()}/edit");

    // @todo When this page is visible, figure out what default field mode is.
    // $fields = $this->xpath('//*[@id="edit-og-group-ref-und-0-default"]');
    // $this->assertEqual($fields[0]->option['value'], $group2->id(), '"Default" field mode is required.');
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
      // @todo I think this is obsolete.
      // OG_GROUP_FIELD . '[und][0][value]' => 1,
      'uid' => $user1->id(),
    ];
    $group1 = $this->createNode($settings);

    $settings['uid'] = $user2->id();
    $group2 = $this->createNode($settings);

    $settings = [
      'type' => 'post',
      // @todo I think this is obsolete.
      // OG_GROUP_FIELD . '[und][0][value]' => 1,
      'uid' => $user1->id(),
    ];
    $post1 = $this->createNode($settings);

    $this->addContentToGroup($post1, $group1);
    $this->addContentToGroup($post1, $group2);

    $this->drupalLogin($user2);
    $this->drupalGet("node/$post1->id()/edit");
    $this->submitForm([], 'Save');

    // Assert post still belongs to both groups, although user was able
    // to select only one.
    $gids = Og::getEntityGroups('node', $post1);
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
      // @todo I think this is obsolete.
      // OG_GROUP_FIELD . '[und][0][value]' => 1,
      'uid' => $user1->id(),
    ];
    $group1 = $this->createNode($settings);

    og_group('node', $group1->id(), ['entity' => $user2, 'state' => OgMembershipInterface::STATE_PENDING]);

    $this->drupalLogin($user2);
    $this->submitForm("user/{$user2->id()}/edit", [], 'Save');

    $this->assertTrue(Og::getEntityGroups('user', $user2, [OgMembershipInterface::STATE_PENDING]), 'User membership was retained after user save.');
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
      'uid' => $user1->id(),
    ];
    $group1 = $this->createNode($settings);
    $group2 = $this->createNode($settings);

    // Create group content.
    $settings = [
      'type' => 'post',
      'uid' => $user1->id(),
    ];
    $post1 = $this->createNode($settings);

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
   * @param int $state
   *   The activation state of the membership. Can be one of the following:
   *   - OgMembershipInterface::STATE_ACTIVE
   *   - OgMembershipInterface::STATE_BLOCKED
   *   - OgMembershipInterface::STATE_PENDING
   *   Defaults to OgMembershipInterface::STATE_ACTIVE.
   */
  protected function addContentToGroup(ContentEntityInterface $member_entity, ContentEntityInterface $group_entity, $state = OgMembershipInterface::STATE_ACTIVE) {
    /** @var OgMembership $membership */
    $membership = Og::membershipStorage()->create(Og::membershipDefault());
    $membership
      ->setFieldName(OgGroupAudienceHelper::DEFAULT_FIELD)
      ->setMemberEntityType($member_entity->getEntityTypeId())
      ->setMemberEntityId($member_entity->id())
      ->setGroupEntityType($group_entity->getEntityTypeId())
      ->setGroupEntityid($group_entity->id())
      ->setState($state)
      ->save();
  }

}
