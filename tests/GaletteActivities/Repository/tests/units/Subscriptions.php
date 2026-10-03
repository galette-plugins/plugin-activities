<?php

/**
 * This file is part of Galette Activities plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2024-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteActivities\Repository\tests\units;

use Galette\Tests\GaletteTestCase;
use GaletteActivities\Filters\SubscriptionsList;
use GaletteActivities\tests\ActivitiesFixtures;

/**
 * Subscriptions repository tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Subscriptions extends GaletteTestCase
{
    use ActivitiesFixtures;

    protected int $seed = 20260926190512;

    /**
     * Cleanup after each test method
     */
    public function tearDown(): void
    {
        $this->cleanActivities();
        parent::tearDown();
    }

    /**
     * Get subscriptions IDs, in list order
     *
     * @param SubscriptionsList $filters Filters
     *
     * @return array<int>
     */
    private function getListIds(SubscriptionsList $filters): array
    {
        $subscriptions = new \GaletteActivities\Repository\Subscriptions($this->zdb, $this->login, $this->history, $this->preferences, $filters);
        $ids = [];
        foreach ($subscriptions->getList() as $subscription) {
            $ids[] = $subscription->getId();
        }
        return $ids;
    }

    /**
     * Subscriptions keep their own values, not their activity ones
     */
    public function testListKeepsSubscriptionValues(): void
    {
        $activity = $this->insertActivity(
            'Climbing',
            null,
            ['comment' => 'Activity comment', 'creation_date' => '2020-01-01']
        );
        $this->insertSubscription(
            $activity,
            $this->getMemberOne()->id,
            ['comment' => 'Subscription comment', 'creation_date' => '2026-01-15']
        );

        $subscriptions = new \GaletteActivities\Repository\Subscriptions($this->zdb, $this->login, $this->history, $this->preferences);
        $list = $subscriptions->getList();
        $this->assertCount(1, $list);
        $this->assertSame('Subscription comment', $list[0]->getComment());
        $this->assertSame('2026-01-15', $list[0]->getCreationDate());
    }

    /**
     * Listed subscriptions share their activities and members, loaded once
     */
    public function testListSharesActivitiesAndMembers(): void
    {
        $member_one = $this->getMemberOne();
        $climbing = $this->insertActivity('Climbing');
        $this->insertSubscription($climbing, $member_one->id, ['end_date' => '2027-01-01']);
        $this->insertSubscription($climbing, $this->getMemberTwo()->id, ['end_date' => '2026-01-01']);
        $this->insertSubscription($this->insertActivity('Hiking'), $member_one->id, ['end_date' => '2025-01-01']);

        $list = (new \GaletteActivities\Repository\Subscriptions($this->zdb, $this->login, $this->history, $this->preferences))->getList();
        $this->assertCount(3, $list);
        $this->assertSame('Climbing', $list[0]->getActivity()?->getName());
        $this->assertSame($list[0]->getActivity(), $list[0]->getActivity());
        $this->assertSame($list[0]->getActivity(), $list[1]->getActivity());
        $this->assertSame($member_one->id, $list[0]->getMember()?->id);
        $this->assertSame($member_one->sfullname, $list[0]->getMember()->sfullname);
        $this->assertSame($list[0]->getMember(), $list[2]->getMember());
    }

    /**
     * Subscriptions are ordered and filtered by payment
     */
    public function testPaid(): void
    {
        $member = $this->getMemberOne()->id;
        $paid_one = $this->insertSubscription($this->insertActivity('Climbing'), $member, [
            'is_paid'           => true,
            'payment_amount'    => 10,
            'end_date'          => date('Y-m-d', strtotime('+1 year')),
        ]);
        $not_paid = $this->insertSubscription($this->insertActivity('Hiking'), $member, [
            'end_date'          => date('Y-m-d', strtotime('+2 years')),
        ]);
        $paid_two = $this->insertSubscription($this->insertActivity('Diving'), $member, [
            'is_paid'           => true,
            'payment_amount'    => 5.5,
            'end_date'          => date('Y-m-d', strtotime('+3 years')),
        ]);

        //default order is on end date
        $filters = new SubscriptionsList();
        $this->assertSame([$paid_two, $not_paid, $paid_one], $this->getListIds($filters));

        //paid ones first
        $filters->orderby = \GaletteActivities\Repository\Subscriptions::ORDERBY_PAID;
        $ids = $this->getListIds($filters);
        $this->assertCount(3, $ids);
        $this->assertSame($not_paid, $ids[2]);

        $filters = new SubscriptionsList();
        $filters->paid_filter = \GaletteActivities\Repository\Subscriptions::FILTER_PAID;
        $this->assertSame([$paid_two, $paid_one], $this->getListIds($filters));
        $subscriptions = new \GaletteActivities\Repository\Subscriptions($this->zdb, $this->login, $this->history, $this->preferences, $filters);
        $subscriptions->getList();
        $this->assertSame(15.5, $subscriptions->getSum());

        $filters->paid_filter = \GaletteActivities\Repository\Subscriptions::FILTER_NOT_PAID;
        $this->assertSame([$not_paid], $this->getListIds($filters));
    }

    /**
     * Subscriptions are filtered by activity, member, payment type, dates and selection
     */
    public function testFilters(): void
    {
        $member_one = $this->getMemberOne()->id;
        $member_two = $this->getMemberTwo()->id;
        $climbing = $this->insertActivity('Climbing');
        $hiking = $this->insertActivity('Hiking');
        $first = $this->insertSubscription($climbing, $member_one, [
            'payment_method'    => \Galette\Entity\PaymentType::CASH,
            'creation_date'     => '2026-01-01',
            'subscription_date' => '2026-01-10',
            'end_date'          => '2026-06-30',
        ]);
        $second = $this->insertSubscription($climbing, $member_two, [
            'payment_method'    => \Galette\Entity\PaymentType::OTHER,
            'creation_date'     => '2026-02-01',
            'subscription_date' => '2026-03-01',
            'end_date'          => '2026-12-31',
        ]);
        $third = $this->insertSubscription($hiking, $member_one, [
            'payment_method'    => \Galette\Entity\PaymentType::OTHER,
            'creation_date'     => '2026-04-15',
            'subscription_date' => '2026-05-01',
            'end_date'          => '2027-04-30',
        ]);

        //no filter, latest end date first
        $filters = new SubscriptionsList();
        $this->assertSame([$third, $second, $first], $this->getListIds($filters));

        $filters->activity_filter = $climbing;
        $this->assertSame([$second, $first], $this->getListIds($filters));
        $subscriptions = new \GaletteActivities\Repository\Subscriptions($this->zdb, $this->login, $this->history, $this->preferences, $filters);
        $subscriptions->getList();
        $this->assertSame(2, $subscriptions->getCount());

        $filters = new SubscriptionsList();
        $filters->member_filter = $member_one;
        $this->assertSame([$third, $first], $this->getListIds($filters));

        $filters = new SubscriptionsList();
        $filters->payment_type_filter = \Galette\Entity\PaymentType::CASH;
        $this->assertSame([$first], $this->getListIds($filters));

        $filters = new SubscriptionsList();
        $filters->date_field = SubscriptionsList::DATE_SUBSCRIPTION;
        $filters->start_date_filter = '2026-02-01';
        $this->assertSame([$third, $second], $this->getListIds($filters));

        $filters = new SubscriptionsList();
        $filters->date_field = SubscriptionsList::DATE_END;
        $filters->end_date_filter = '2026-12-31';
        $this->assertSame([$second, $first], $this->getListIds($filters));

        $filters = new SubscriptionsList();
        $filters->date_field = SubscriptionsList::DATE_CREATION;
        $filters->start_date_filter = '2026-02-01';
        $filters->end_date_filter = '2026-03-01';
        $this->assertSame([$second], $this->getListIds($filters));

        $filters = new SubscriptionsList();
        $filters->selected = [$first, $third];
        $this->assertSame([$third, $first], $this->getListIds($filters));

        //filters are reset
        $filters->reinit();
        $this->assertSame([$third, $second, $first], $this->getListIds($filters));
    }

    /**
     * Subscriptions are ordered on every column
     */
    public function testOrders(): void
    {
        $member_one = $this->getMemberOne();
        $member_two = $this->getMemberTwo();
        $climbing = $this->insertActivity('Climbing');
        $hiking = $this->insertActivity('Hiking');
        $first = $this->insertSubscription($climbing, $member_one->id, [
            'payment_amount'    => 30,
            'subscription_date' => '2026-03-01',
            'end_date'          => '2026-06-30',
        ]);
        $second = $this->insertSubscription($hiking, $member_two->id, [
            'payment_amount'    => 10,
            'subscription_date' => '2026-01-01',
            'end_date'          => '2026-12-31',
        ]);

        $by_name = strcmp($member_one->name . ' ' . $member_one->surname, $member_two->name . ' ' . $member_two->surname) > 0
            ? [$first, $second]
            : [$second, $first];
        $expected = [
            \GaletteActivities\Repository\Subscriptions::ORDERBY_ACTIVITY => [$second, $first],
            \GaletteActivities\Repository\Subscriptions::ORDERBY_MEMBER => $by_name,
            \GaletteActivities\Repository\Subscriptions::ORDERBY_SUBSCRIPTIONDATE => [$first, $second],
            \GaletteActivities\Repository\Subscriptions::ORDERBY_ENDDATE => [$second, $first],
            \GaletteActivities\Repository\Subscriptions::ORDERBY_AMOUNT => [$first, $second],
        ];
        foreach ($expected as $orderby => $ids) {
            //default direction is descending
            $filters = new SubscriptionsList();
            $filters->orderby = $orderby;
            $filters->setDirection(\Galette\Enums\SQLOrder::DESC);
            $this->assertSame($ids, $this->getListIds($filters), 'order ' . $orderby);
            $filters->invertorder();
            $this->assertSame(array_reverse($ids), $this->getListIds($filters), 'order ' . $orderby . ' ascending');
        }
    }
}
