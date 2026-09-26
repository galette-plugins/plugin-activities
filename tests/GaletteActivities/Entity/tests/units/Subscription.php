<?php

/**
 * This file is part of Galette Activities plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2024-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteActivities\tests\units;

use Galette\Tests\GaletteTestCase;
use GaletteActivities\tests\ActivitiesFixtures;

/**
 * Subscription tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Subscription extends GaletteTestCase
{
    use ActivitiesFixtures;

    protected int $seed = 20240817102541;

    /**
     * Cleanup after each test method
     */
    public function tearDown(): void
    {
        $delete = $this->zdb->delete(ACTIVITIES_PREFIX . \GaletteActivities\Entity\Subscription::TABLE);
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(ACTIVITIES_PREFIX . \GaletteActivities\Entity\Activity::TABLE);
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(\Galette\Entity\Group::GROUPSUSERS_TABLE);
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(\Galette\Entity\Group::TABLE);
        $this->zdb->execute($delete);

        parent::tearDown();
    }

    /**
     * Test empty
     */
    public function testEmpty(): void
    {
        $subscription = new \GaletteActivities\Entity\Subscription($this->zdb);

        $this->assertNull($subscription->getId());
        $this->assertNull($subscription->getActivityId());
        $this->assertNull($subscription->getActivity());
        $this->assertNull($subscription->getMemberId());
        $this->assertNull($subscription->getMember());
        $this->assertTrue($subscription->isPaid());
        $this->assertNull($subscription->getAmount());
        $this->assertSame(6, $subscription->getPaymentMethod());
        $this->assertSame('Other', $subscription->getPaymentMethodName());
        $this->assertNotSame('', $subscription->getCreationDate());
        $this->assertSame('', $subscription->getSubscriptionDate());
        $this->assertSame('', $subscription->getEndDate());
        $this->assertSame('', $subscription->getComment());
        $this->assertSame('subscription-paid', $subscription->getRowClass());
    }

    /**
     * Test add and update
     */
    public function testCrud(): void
    {
        $subscription = new \GaletteActivities\Entity\Subscription($this->zdb);
        $subscriptions = new \GaletteActivities\Repository\Activities($this->zdb, $this->login, $this->preferences);

        //ensure the table is empty
        $this->assertCount(0, $subscriptions->getList());

        //bootstrap data
        $activity = new \GaletteActivities\Entity\Activity($this->zdb);
        $data = [
            'name' => 'Activity for subscriptions',
            'comment' => 'Comment ' . $this->seed,
            'price' => 42.0,
        ];
        $this->assertTrue($activity->check($data));
        $this->assertTrue($activity->store());

        $group = new \Galette\Entity\Group();
        $group->setName('Subscribed group');
        $this->assertTrue($group->store());

        $gactivity = new \GaletteActivities\Entity\Activity($this->zdb);
        $data = [
            'name' => 'Activity with a group',
            'comment' => 'Comment for group/activity ' . $this->seed,
            'price' => 5.0,
            \Galette\Entity\Group::PK => $group->getId()
        ];
        $this->assertTrue($gactivity->check($data));
        $this->assertTrue($gactivity->store());

        $activity_id = $activity->getId();
        $gactivity_id = $gactivity->getId();
        $member_one = $this->getMemberOne();

        //Missing required data
        $data = [
        ];
        $this->assertFalse($subscription->check($data));
        $this->assertSame(
            [
                'Activity is mandatory',
                'Member is mandatory',
                'Subscription date is mandatory',
                'End date is mandatory'
            ],
            $subscription->getErrors()
        );
        $this->expectLogEntry(
            \Analog::ERROR,
            'Activity is mandatory',
        );

        $data = [
            'activity' => $activity_id,
        ];
        $this->assertFalse($subscription->check($data));
        $this->assertSame(
            [
                'Member is mandatory',
                'Subscription date is mandatory',
                'End date is mandatory'
            ],
            $subscription->getErrors()
        );
        $this->expectLogEntry(
            \Analog::ERROR,
            'Member is mandatory',
        );

        $data = [
            'activity' => $activity_id,
            'member' => $member_one->id,
        ];
        $this->assertFalse($subscription->check($data));
        $this->assertSame(
            [
                'Subscription date is mandatory',
                'End date is mandatory'
            ],
            $subscription->getErrors()
        );
        $this->expectLogEntry(
            \Analog::ERROR,
            'Subscription date is mandatory',
        );

        $data = [
            'activity' => $activity_id,
            'member' => $member_one->id,
            'subscription_date' => 'notadate',
        ];
        $this->assertFalse($subscription->check($data));
        $this->assertSame(
            [
                '- Wrong date format (Y-m-d) for Subscription date!',
                'End date is mandatory'
            ],
            $subscription->getErrors()
        );
        $this->expectLogEntry(
            \Analog::ERROR,
            '- Wrong date format (Y-m-d) for Subscription date!',
        );

        $data = [
            'activity' => $activity_id,
            'member' => $member_one->id,
            'subscription_date' => '2024-08-17',
            'end_date' => '2025-08-17',
            'comment' => 'Comment ' . $this->seed,
            'save' => 1,
        ];
        $this->assertTrue($subscription->check($data));
        $this->assertSame(42.0, $subscription->getAmount());
        $this->assertSame(42.0, $subscription->getAmountFromActivity());
        $this->assertSame([], $subscription->getErrors());
        $this->assertTrue($subscription->store());
        $subscription_id = $subscription->getId();

        //member is not part of any group
        $this->assertCount(0, $member_one->getGroups());

        $this->assertFalse($subscription->isPaid());
        //by default, amount is set to activity price
        $this->assertSame(42.0, $subscription->getAmount());
        $this->assertSame('2024-08-17', $subscription->getSubscriptionDate());
        $this->assertSame('2025-08-17', $subscription->getEndDate());
        $this->i18n->changeLanguage('fr_FR');
        $this->assertSame('17/08/2024', $subscription->getSubscriptionDate());
        $this->assertSame('17/08/2025', $subscription->getEndDate());
        $this->i18n->changeLanguage('en_US');
        $this->assertSame('subscription-notpaid', $subscription->getRowClass());
        $this->assertSame($activity_id, $subscription->getActivityId());
        $this->assertSame($member_one->id, $subscription->getMemberId());
        $this->assertEquals($activity, $subscription->getActivity());
        $this->assertSame($member_one->id, $subscription->getMember()->id);

        //reload
        $subscription = new \GaletteActivities\Entity\Subscription($this->zdb, $subscription_id);
        $data += [
            'paid' => 0,
            'payment_amount' => 21.0,
            'payment_method' => 1,
        ];
        $this->assertTrue($subscription->check($data));
        $this->assertSame(21.0, $subscription->getAmount());
        $this->assertSame(42.0, $subscription->getAmountFromActivity());
        $this->assertTrue($subscription->store());

        $this->assertFalse($subscription->isPaid());
        $this->assertSame(21.0, $subscription->getAmount());
        $this->assertSame('subscription-notpaid', $subscription->getRowClass());
        $this->assertSame(1, $subscription->getPaymentMethod());
        $this->assertSame('Cash', $subscription->getPaymentMethodName());

        //remove subscription
        $this->assertTrue($subscription->remove());
        $this->assertFalse($activity->load($subscription_id));

        //create a subscription with a group
        $subscription = new \GaletteActivities\Entity\Subscription($this->zdb);
        $data = [
            'activity' => $gactivity_id,
            'member' => $member_one->id,
            'subscription_date' => (new \DateTime())->format('Y-m-d'),
            'end_date' => (new \DateTime())->modify('+1 year')->format('Y-m-d'),
            'comment' => 'Comment ' . $this->seed,
        ];
        $this->assertTrue($subscription->check($data));
        $this->assertTrue($subscription->store());

        //member is part activity linked group
        $member_one->loadGroups();
        $groups = $member_one->getGroups();
        $this->assertCount(1, $groups);

        //no duplicate on subscriptions
        $subscription = new \GaletteActivities\Entity\Subscription($this->zdb);
        $data = [
            'activity' => $gactivity_id,
            'member' => $member_one->id,
            'subscription_date' => (new \DateTime())->format('Y-m-d'),
            'end_date' => (new \DateTime())->modify('+1 year')->format('Y-m-d'),
            'comment' => 'Comment ' . $this->seed,
        ];
        $this->assertTrue($subscription->check($data));
        $this->assertFalse($subscription->store());
        $this->assertSame(
            [
                'Subscription already exists for this member and activity'
            ],
            $subscription->getErrors()
        );
    }

    /**
     * Test load error
     */
    public function testLoadError(): void
    {
        $subscription = new \GaletteActivities\Entity\Subscription($this->zdb);
        $this->assertFalse($subscription->load(999));
    }

    /**
     * Check and store a subscription
     *
     * @param int                                     $activity     Activity ID
     * @param int                                     $member       Member ID
     * @param ?\GaletteActivities\Entity\Subscription $subscription Subscription to change, a new one if null
     */
    private function storeSubscription(
        int $activity,
        int $member,
        ?\GaletteActivities\Entity\Subscription $subscription = null
    ): \GaletteActivities\Entity\Subscription {
        $subscription ??= new \GaletteActivities\Entity\Subscription($this->zdb);
        $this->assertTrue($subscription->check([
            'activity' => $activity,
            'member' => $member,
            'subscription_date' => date('Y-m-d'),
            'end_date' => date('Y-m-d', strtotime('+1 year')),
        ]));
        $this->assertTrue($subscription->store());
        return $subscription;
    }

    /**
     * Members already in the activity group can subscribe
     */
    public function testStoreMemberAlreadyInGroup(): void
    {
        $member_one = $this->getMemberOne();
        $group = $this->createGroup('Activity group', [], [$member_one]);
        $activity = $this->insertActivity('Climbing', $group->getId());

        $this->storeSubscription($activity, $member_one->id);
        $this->assertSame(1, $this->countSubscriptions($activity));
        $this->assertTrue($this->isInGroup($group->getId(), $member_one->id));
    }

    /**
     * Duplicates are refused without breaking the transaction opened by the caller
     */
    public function testDuplicateInTransaction(): void
    {
        $member_one = $this->getMemberOne();
        $activity = $this->insertActivity('Climbing');
        $this->insertSubscription($activity, $member_one->id);

        $this->zdb->connection->beginTransaction();
        $subscription = new \GaletteActivities\Entity\Subscription($this->zdb);
        $this->assertTrue($subscription->check([
            'activity' => $activity,
            'member' => $member_one->id,
            'subscription_date' => date('Y-m-d'),
            'end_date' => date('Y-m-d', strtotime('+1 year')),
        ]));
        $this->assertFalse($subscription->store());
        $this->assertSame(['Subscription already exists for this member and activity'], $subscription->getErrors());

        //transaction is still usable
        $this->insertActivity('Hiking');
        $this->zdb->connection->commit();
        $this->assertSame(1, $this->countSubscriptions($activity));
    }

    /**
     * Members join the group of their new activity, and stay in the previous one
     */
    public function testChangeActivityJoinsGroup(): void
    {
        $member_one = $this->getMemberOne();
        $climbing_group = $this->createGroup('Climbing group');
        $hiking_group = $this->createGroup('Hiking group');
        $climbing = $this->insertActivity('Climbing', $climbing_group->getId());
        $hiking = $this->insertActivity('Hiking', $hiking_group->getId());

        $subscription = $this->storeSubscription($climbing, $member_one->id);
        $this->assertTrue($this->isInGroup($climbing_group->getId(), $member_one->id));
        $this->assertFalse($this->isInGroup($hiking_group->getId(), $member_one->id));

        $subscription = new \GaletteActivities\Entity\Subscription($this->zdb, (int)$subscription->getId());
        $this->storeSubscription($hiking, $member_one->id, $subscription);
        $this->assertTrue($this->isInGroup($hiking_group->getId(), $member_one->id));
        $this->assertTrue($this->isInGroup($climbing_group->getId(), $member_one->id));
        $this->assertSame(0, $this->countSubscriptions($climbing));

        //storing again without changing activity does not join the group again
        $this->zdb->execute($this->zdb->delete(\Galette\Entity\Group::GROUPSUSERS_TABLE));
        $subscription = new \GaletteActivities\Entity\Subscription($this->zdb, (int)$subscription->getId());
        $this->storeSubscription($hiking, $member_one->id, $subscription);
        $this->assertFalse($this->isInGroup($hiking_group->getId(), $member_one->id));
        $this->assertSame(1, $this->countSubscriptions($hiking));
    }
}
