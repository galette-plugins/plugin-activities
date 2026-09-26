<?php

/**
 * This file is part of Galette Activities plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2024-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteActivities\Controllers\Crud\tests\units;

use Galette\Tests\GaletteRoutingTestCase;
use GaletteActivities\tests\ActivitiesFixtures;

/**
 * Subscriptions controller tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class SubscriptionsController extends GaletteRoutingTestCase
{
    use ActivitiesFixtures;

    protected int $seed = 20260926172412;
    protected bool $load_plugins = true;

    /**
     * Cleanup after each test method
     */
    public function tearDown(): void
    {
        $this->login->logout();
        $this->cleanActivities();
        $this->cleanMembers();
        parent::tearDown();
    }

    /**
     * Post a new subscription
     *
     * @param int $activity Activity ID
     * @param int $member   Member ID
     */
    private function postSubscription(int $activity, int $member): \Psr\Http\Message\ResponseInterface
    {
        $request = $this->createRequest('activities_storesubscription_add', [], 'POST')
            ->withParsedBody([
                'activity'          => (string)$activity,
                'member'            => (string)$member,
                'payment_method'    => '6',
                'subscription_date' => date('Y-m-d'),
                'end_date'          => date('Y-m-d', strtotime('+1 year')),
                'comment'           => '',
                'save'              => '1',
            ]);
        return $this->app->handle($request);
    }

    /**
     * Visitors cannot subscribe members, nor add them to the activity group
     */
    public function testVisitorCannotSubscribe(): void
    {
        $member_one = $this->getMemberOne();
        $group = $this->createGroup('Activity group');
        $activity = $this->insertActivity('Climbing', $group->getId());

        $this->expectLogin($this->postSubscription($activity, $member_one->id));
        $this->assertSame(0, $this->countSubscriptions($activity));
        $this->assertFalse($this->isInGroup($group->getId(), $member_one->id));
    }

    /**
     * Members cannot subscribe anyone, even themselves
     */
    public function testMemberCannotSubscribe(): void
    {
        $member_one = $this->getMemberOne();
        $group = $this->createGroup('Activity group');
        $activity = $this->insertActivity('Climbing', $group->getId());

        $this->logMember($this->dataAdherentOne());
        $this->expectAuthMiddlewareRefused($this->postSubscription($activity, $member_one->id));
        $this->assertSame(0, $this->countSubscriptions($activity));
        $this->assertFalse($this->isInGroup($group->getId(), $member_one->id));
    }

    /**
     * Group managers cannot subscribe members, even to their own group activity
     */
    public function testManagerCannotSubscribe(): void
    {
        $member_one = $this->getMemberOne();
        $member_two = $this->getMemberTwo();
        $group = $this->createGroup('Activity group', [$member_one]);
        $activity = $this->insertActivity('Climbing', $group->getId());

        $this->logMember($this->dataAdherentOne());
        $this->assertTrue($this->login->isGroupManager());
        $this->expectAuthMiddlewareRefused($this->postSubscription($activity, $member_two->id));
        $this->assertSame(0, $this->countSubscriptions($activity));
        $this->assertFalse($this->isInGroup($group->getId(), $member_two->id));
    }

    /**
     * Staff members subscribe members, who join the activity group
     */
    public function testStaffSubscribes(): void
    {
        $staff = $this->getStaffMember($this->getMemberOne());
        $member_two = $this->getMemberTwo();
        $group = $this->createGroup('Activity group');
        $activity = $this->insertActivity('Climbing', $group->getId());

        $this->logMember($this->dataAdherentOne());
        $this->assertTrue($this->login->isStaff());
        $test_response = $this->postSubscription($activity, $member_two->id);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('activities_subscriptions')]],
            $test_response->getHeaders()
        );
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(['success_detected' => [_T('New subscription has been successfully added.', 'activities')]]);
        $this->assertSame(1, $this->countSubscriptions($activity));
        $this->assertTrue($this->isInGroup($group->getId(), $member_two->id));

        $this->resetStaffStatus($staff, $member_two);
    }
}
