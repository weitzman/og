<?php

namespace Drupal\Tests\og\Kernel\Entity;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\og\Entity\OgMembership;
use Drupal\og\Og;
use Drupal\og\OgMembershipInterface;
use Drupal\user\Entity\User;

/**
 * Tests retrieving OgMembership entities associated with a given user.
 *
 * @group og
 * @coversDefaultClass \Drupal\og\Og
 */
class GetMembershipsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'entity_test',
    'field',
    'node',
    'og',
    'system',
    'user',
  ];

  /**
   * Test groups.
   *
   * @var \Drupal\Core\Entity\EntityInterface[]
   */
  protected $groups = [];

  /**
   * Test users.
   *
   * @var \Drupal\user\UserInterface[]
   */
  protected $users;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installConfig(['og']);
    $this->installEntitySchema('node');
    $this->installEntitySchema('og_membership');
    $this->installEntitySchema('user');
    $this->installSchema('system', 'sequences');

    // Create group admin user.
    $group_admin = User::create(['name' => $this->randomString()]);
    $group_admin->save();

    // Create two groups.
    for ($i = 0; $i < 2; $i++) {
      $bundle = "node_$i";
      NodeType::create([
        'name' => $this->randomString(),
        'type' => $bundle,
      ])->save();
      Og::groupManager()->addGroup('node', $bundle);

      $group = Node::create([
        'title' => $this->randomString(),
        'type' => $bundle,
        'uid' => $group_admin->id(),
      ]);
      $group->save();
      $this->groups[] = $group;
    }

    // Create test users with different membership statuses in the two groups.
    $matrix = [
      // A user which is an active member of the first group.
      [OgMembershipInterface::STATE_ACTIVE, NULL],

      // A user which is a pending member of the second group.
      [NULL, OgMembershipInterface::STATE_PENDING],

      // A user which is an active member of both groups.
      [OgMembershipInterface::STATE_ACTIVE, OgMembershipInterface::STATE_ACTIVE],

      // A user which is a pending member of the first group and blocked in the
      // second group.
      [OgMembershipInterface::STATE_PENDING, OgMembershipInterface::STATE_BLOCKED],

      // A user which is not subscribed to either of the two groups.
      [NULL, NULL],
    ];

    foreach ($matrix as $user_key => $statuses) {
      $user = User::create(['name' => $this->randomString()]);
      $user->save();
      $this->users[$user_key] = $user;
      foreach ($statuses as $group_key => $status) {
        $group = $this->groups[$group_key];
        if ($status) {
          $membership = OgMembership::create();
          $membership
            ->setUser($user)
            ->setGroup($group)
            ->setState($status)
            ->save();
        }
      }
    }
  }

  /**
   * Tests retrieval of OG Membership entities associated with a given user.
   *
   * @param int $index
   *   The array index in the $this->users array of the user to test.
   * @param array $states
   *   Array with the states to retrieve.
   * @param string $field_name
   *   The field name associated with the group.
   * @param array $expected
   *   An array containing the expected results to be returned.
   *
   * @covers ::getMemberships
   * @dataProvider membershipDataProvider
   */
  public function testGetMemberships($index, array $states, $field_name, array $expected) {
    $result = Og::getMemberships($this->users[$index], $states, $field_name);

    // Check that the correct number of results is returned.
    $this->assertEquals(count($expected), count($result));

    // Inspect the results that were returned.
    foreach ($result as $key => $membership) {
      // Check that all result items are OgMembership objects.
      $this->assertInstanceOf('Drupal\og\OgMembershipInterface', $membership);
      // Check that the results are keyed by OgMembership ID.
      $this->assertEquals($membership->id(), $key);
    }

    // Check that all expected results are returned.
    foreach ($expected as $expected_group) {
      $expected_id = $this->groups[$expected_group]->id();
      foreach ($result as $membership) {
        if ($membership->getGroupId() === $expected_id) {
          // Test successful: the expected result was found.
          continue 2;
        }
      }
      $this->fail("The expected group with ID $expected_id was not found.");
    }
  }

  /**
   * Provides test data to test retrieval of memberships.
   *
   * @return array
   *   An array of test properties. Each property is an indexed array with the
   *   following items:
   *   - The key of the user in the $this->users array for which to retrieve
   *     memberships.
   *   - An array of membership states to filter on.
   *   - The field name to filter on.
   *   - An array containing the expected results to be returned.
   */
  public function membershipDataProvider() {
    return [
      // The first user is an active member of the first group.
      // Query default values. The group should be returned.
      [0, [], NULL, [0]],
      // Filter by active state.
      [0, [OgMembershipInterface::STATE_ACTIVE], NULL, [0]],
      // Filter by active + pending state.
      [0, [
        OgMembershipInterface::STATE_ACTIVE,
        OgMembershipInterface::STATE_PENDING,
      ], NULL, [0],
      ],
      // Filter by blocked + pending state. Since the user is active this should
      // not return any matches.
      [0, [
        OgMembershipInterface::STATE_BLOCKED,
        OgMembershipInterface::STATE_PENDING,
      ], NULL, [],
      ],
      // Filter by a non-existing field name. This should not return any
      // matches.
      [0, [], 'non_existing_field_name', []],

      // The second user is a pending member of the second group.
      // Query default values. The group should be returned.
      [1, [], NULL, [1]],
      // Filter by pending state.
      [1, [OgMembershipInterface::STATE_PENDING], NULL, [1]],
      // Filter by active state. The user is pending so this should not return
      // any matches.
      [1, [OgMembershipInterface::STATE_ACTIVE], NULL, []],

      // The third user is an active member of both groups.
      // Query default values. Both groups should be returned.
      [2, [], NULL, [0, 1]],
      // Filter by active state.
      [2, [OgMembershipInterface::STATE_ACTIVE], NULL, [0, 1]],
      // Filter by blocked state. This should not return any matches.
      [2, [OgMembershipInterface::STATE_BLOCKED], NULL, []],

      // The fourth user is a pending member of the first group and blocked in
      // the second group.
      // Query default values. Both groups should be returned.
      [3, [], NULL, [0, 1]],
      // Filter by active state. No results should be returned.
      [3, [OgMembershipInterface::STATE_ACTIVE], NULL, []],
      // Filter by pending state.
      [3, [OgMembershipInterface::STATE_PENDING], NULL, [0]],
      // Filter by blocked state.
      [3, [OgMembershipInterface::STATE_BLOCKED], NULL, [1]],
      // Filter by combinations of states.
      [3, [
        OgMembershipInterface::STATE_ACTIVE,
        OgMembershipInterface::STATE_PENDING,
      ], NULL, [0],
      ],
      [3, [
        OgMembershipInterface::STATE_ACTIVE,
        OgMembershipInterface::STATE_PENDING,
        OgMembershipInterface::STATE_BLOCKED,
      ], NULL, [0, 1],
      ],
      [3, [
        OgMembershipInterface::STATE_ACTIVE,
        OgMembershipInterface::STATE_BLOCKED,
      ], NULL, [1],
      ],
      [3, [
        OgMembershipInterface::STATE_PENDING,
        OgMembershipInterface::STATE_BLOCKED,
      ], NULL, [0, 1],
      ],

      // A user which is not subscribed to either of the two groups.
      [4, [], NULL, []],
      [4, [OgMembershipInterface::STATE_ACTIVE], NULL, []],
      [4, [OgMembershipInterface::STATE_BLOCKED], NULL, []],
      [4, [OgMembershipInterface::STATE_PENDING], NULL, []],
      [4, [
        OgMembershipInterface::STATE_ACTIVE,
        OgMembershipInterface::STATE_PENDING,
        OgMembershipInterface::STATE_BLOCKED,
      ], NULL, [],
      ],
    ];
  }

}
