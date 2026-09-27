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
     * Get subscriptions list filters from session
     */
    private function getSubscriptionsFilters(): \GaletteActivities\Filters\SubscriptionsList
    {
        $controller = $this->container->get(\GaletteActivities\Controllers\Crud\SubscriptionsController::class);
        return $this->session->{$controller->getFilterName($controller::getDefaultFilterName())};
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
        $this->assertSame(303, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(['success_detected' => [_T('New subscription has been successfully added.', 'activities')]]);
        //list shows subscriptions of the stored activity
        $this->assertSame($activity, $this->getSubscriptionsFilters()->activity_filter);
        $this->assertSame(1, $this->countSubscriptions($activity));
        $this->assertTrue($this->isInGroup($group->getId(), $member_two->id));

        $this->resetStaffStatus($staff, $member_two);
    }

    /**
     * Unknown subscriptions are not edited
     */
    public function testEditUnknownSubscription(): void
    {
        $this->logSuperAdmin();
        $id = $this->insertSubscription($this->insertActivity('Climbing'), $this->getMemberOne()->id) + 1000;

        $test_response = $this->app->handle($this->createRequest('activities_subscription_edit', ['id' => (string)$id]));
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('activities_subscriptions')]],
            $test_response->getHeaders()
        );
        $this->assertSame(302, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(['error_detected' => ['No subscription #' . $id . '.']]);
    }

    /**
     * Subscriptions are removed from their route ID, as activities
     */
    public function testRemoveSubscription(): void
    {
        $this->logSuperAdmin();
        $activity = $this->insertActivity('Climbing');
        $id = $this->insertSubscription($activity, $this->getMemberOne()->id);

        $request = $this->createRequest('activities_do_remove_subscription', ['id' => (string)$id], 'POST')
            ->withParsedBody(['confirm' => '1']);
        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('activities_subscriptions')]],
            $test_response->getHeaders()
        );
        $this->expectNoLogEntry();
        $this->expectFlashData(['success_detected' => ['Successfully deleted!']]);
        $this->assertSame(0, $this->countSubscriptions($activity));
    }

    /**
     * Removal of an unknown subscription is not confirmed
     */
    public function testConfirmRemoveUnknownSubscription(): void
    {
        $this->logSuperAdmin();
        $id = $this->insertSubscription($this->insertActivity('Climbing'), $this->getMemberOne()->id) + 1000;

        $test_response = $this->app->handle(
            $this->createRequest('activities_remove_subscription', ['id' => (string)$id])
        );
        $this->assertSame(200, $test_response->getStatusCode());
        $this->assertStringContainsString('No subscription #' . $id . '.', (string)$test_response->getBody());
        $this->expectNoLogEntry();
    }

    /**
     * Member filter is optional, and can be cleared
     */
    public function testMemberFilter(): void
    {
        $this->logSuperAdmin();
        $member_one = $this->getMemberOne();
        $activity = $this->insertActivity('Climbing');

        $filter = function (array $data): void {
            $request = $this->createRequest('activities_filter-subscriptionslist', [], 'POST')
                ->withParsedBody($data);
            $test_response = $this->app->handle($request);
            $this->assertSame(303, $test_response->getStatusCode());
            $this->expectNoLogEntry();
        };

        //activity alone
        $filter(['activity_filter' => (string)$activity, 'member_filter' => '']);
        $this->assertSame($activity, $this->getSubscriptionsFilters()->activity_filter);
        $this->assertNull($this->getSubscriptionsFilters()->member_filter);

        $filter(['activity_filter' => (string)$activity, 'member_filter' => (string)$member_one->id]);
        $this->assertSame($member_one->id, $this->getSubscriptionsFilters()->member_filter);

        //cleared
        $filter(['activity_filter' => (string)$activity, 'member_filter' => '']);
        $this->assertNull($this->getSubscriptionsFilters()->member_filter);

        $this->getSubscriptionsFilters()->reinit();
    }

    /**
     * Subscriptions list
     */
    public function testList(): void
    {
        $this->logSuperAdmin();
        $member_one = $this->getMemberOne();
        $climbing = $this->insertActivity('Climbing');
        $this->insertSubscription($climbing, $member_one->id, ['payment_amount' => 12.5, 'is_paid' => true]);
        $this->insertSubscription($this->insertActivity('Hiking'), $member_one->id, ['payment_amount' => 5]);

        $test_response = $this->app->handle($this->createRequest('activities_subscriptions'));
        $this->assertSame(200, $test_response->getStatusCode());
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('2 subscriptions', $body);
        $this->assertStringContainsString('Climbing', $body);
        $this->assertStringContainsString('Hiking', $body);
        $this->assertStringContainsString($member_one->sfullname, $body);
        $this->assertStringContainsString('<td class="subscription-paid" data-col-label="Amount">12.50</td>', $body);
        $this->assertStringContainsString('Found subscriptions total 17.50', $body);
        //removal modal
        $this->assertStringContainsString('_removeItems', $body);
        $this->expectNoLogEntry();
    }

    /**
     * Every filter is stored, and can be reset
     */
    public function testFilters(): void
    {
        $this->logSuperAdmin();
        $member_one = $this->getMemberOne();
        $activity = $this->insertActivity('Climbing');

        $request = $this->createRequest('activities_filter-subscriptionslist', [], 'POST');
        $test_response = $this->app->handle($request->withParsedBody([
            'nbshow'                => '20',
            'paid_filter'           => (string)\GaletteActivities\Repository\Subscriptions::FILTER_NOT_PAID,
            'payment_type_filter'   => (string)\Galette\Entity\PaymentType::CASH,
            'activity_filter'       => (string)$activity,
            'member_filter'         => (string)$member_one->id,
            'date_field'            => (string)\GaletteActivities\Filters\SubscriptionsList::DATE_SUBSCRIPTION,
            'start_date_filter'     => '2026-01-01',
            'end_date_filter'       => '2026-12-31',
        ]));
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('activities_subscriptions')]],
            $test_response->getHeaders()
        );
        $this->assertSame(303, $test_response->getStatusCode());
        $this->expectNoLogEntry();

        $filters = $this->getSubscriptionsFilters();
        $this->assertSame(20, $filters->show);
        $this->assertSame(\GaletteActivities\Repository\Subscriptions::FILTER_NOT_PAID, (int)$filters->paid_filter);
        $this->assertSame(\Galette\Entity\PaymentType::CASH, $filters->payment_type_filter);
        $this->assertSame($activity, $filters->activity_filter);
        $this->assertSame($member_one->id, $filters->member_filter);
        $this->assertSame(\GaletteActivities\Filters\SubscriptionsList::DATE_SUBSCRIPTION, $filters->date_field);
        $this->assertSame('2026-01-01', $filters->start_date_filter);
        $this->assertSame('2026-12-31', $filters->end_date_filter);

        $this->app->handle($request->withParsedBody(['clear_filter' => '1']));
        $filters = $this->getSubscriptionsFilters();
        $this->assertNull($filters->activity_filter);
        $this->assertNull($filters->member_filter);
        $this->assertSame(-1, $filters->payment_type_filter);
        $this->assertNull($filters->start_date_filter);
        $this->expectNoLogEntry();
    }

    /**
     * Creation form is reloaded with selected activity values, without storing
     */
    public function testReloadForm(): void
    {
        $this->logSuperAdmin();
        $member_one = $this->getMemberOne();
        $activity = $this->insertActivity('Climbing', null, ['price' => 12.5]);

        $test_response = $this->app->handle($this->createRequest('activities_subscription_add', ['id_adh' => (string)$member_one->id]));
        $this->assertSame(200, $test_response->getStatusCode());
        //form is reloaded by the plugin script, not by inline code
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('activities.js"', $body);
        $this->assertStringNotContainsString("$('#activity').on('change'", $body);
        $this->expectNoLogEntry();

        //activity change posts the form without save
        $request = $this->createRequest('activities_storesubscription_add', [], 'POST')
            ->withParsedBody(['activity' => (string)$activity, 'member' => (string)$member_one->id, 'id' => '']);
        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('activities_subscription_add')]],
            $test_response->getHeaders()
        );
        $this->expectNoLogEntry();
        $this->expectFlashData(['warning_detected' => ['Do not forget to store the subscription']]);
        $this->assertSame(0, $this->countSubscriptions($activity));

        $test_response = $this->app->handle($this->createRequest('activities_subscription_add'));
        $this->assertSame(200, $test_response->getStatusCode());
        $body = (string)$test_response->getBody();
        $this->assertMatchesRegularExpression('/<option\s+value="' . $activity . '"\s+selected="selected"/', $body);
        $this->assertStringContainsString('<input type="number" step="0.01" name="payment_amount" id="payment_amount" value="" placeholder="12.5"/>', $body);
        $this->assertFalse(isset($this->session->plugin_activities_subscription));
        //reloaded form is checked, not stored
        $this->expectLogEntry(\Analog\Analog::ERROR, 'Subscription date is mandatory');
        $this->expectNoLogEntry();
    }

    /**
     * Subscriptions are edited
     */
    public function testEdit(): void
    {
        $this->logSuperAdmin();
        $member_one = $this->getMemberOne();
        $activity = $this->insertActivity('Climbing');
        $id = $this->insertSubscription($activity, $member_one->id, ['comment' => 'First comment']);

        $test_response = $this->app->handle($this->createRequest('activities_subscription_edit', ['id' => (string)$id]));
        $this->assertSame(200, $test_response->getStatusCode());
        $this->assertStringContainsString('First comment', (string)$test_response->getBody());
        $this->assertStringNotContainsString('autocomplete="1"', (string)$test_response->getBody());
        $this->expectNoLogEntry();

        $request = $this->createRequest('activities_storesubscription_edit', [], 'POST')
            ->withParsedBody([
                'id'                => (string)$id,
                'activity'          => (string)$activity,
                'member'            => (string)$member_one->id,
                'subscription_date' => date('Y-m-d'),
                'end_date'          => date('Y-m-d', strtotime('+1 year')),
                'payment_amount'    => '7',
                'paid'              => '1',
                'comment'           => 'Changed comment',
                'save'              => '1',
            ]);
        $test_response = $this->app->handle($request);
        $this->assertSame(303, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(['success_detected' => ['Subscription has been modified.']]);

        $subscription = new \GaletteActivities\Entity\Subscription($this->zdb, $this->history, $id);
        $this->assertSame('Changed comment', $subscription->getComment());
        $this->assertSame(7.0, $subscription->getAmount());
        $this->assertTrue($subscription->isPaid());
        $this->assertSame(1, $this->countSubscriptions($activity));
    }
}
