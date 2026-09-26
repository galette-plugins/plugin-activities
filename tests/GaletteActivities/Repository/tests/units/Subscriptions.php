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
        $subscriptions = new \GaletteActivities\Repository\Subscriptions($this->zdb, $filters);
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

        $subscriptions = new \GaletteActivities\Repository\Subscriptions($this->zdb);
        $list = $subscriptions->getList();
        $this->assertCount(1, $list);
        $this->assertSame('Subscription comment', $list[0]->getComment());
        $this->assertSame((new \DateTime('2026-01-15'))->format(__('Y-m-d')), $list[0]->getCreationDate());
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
        $subscriptions = new \GaletteActivities\Repository\Subscriptions($this->zdb, $filters);
        $subscriptions->getList();
        $this->assertSame(15.5, $subscriptions->getSum());

        $filters->paid_filter = \GaletteActivities\Repository\Subscriptions::FILTER_NOT_PAID;
        $this->assertSame([$not_paid], $this->getListIds($filters));
    }
}
