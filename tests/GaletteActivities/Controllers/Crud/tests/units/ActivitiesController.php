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
 * Activities controller tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ActivitiesController extends GaletteRoutingTestCase
{
    use ActivitiesFixtures;

    protected int $seed = 20260926194512;
    protected bool $load_plugins = true;

    /**
     * Cleanup after each test method
     */
    public function tearDown(): void
    {
        $this->login->logout();
        $this->cleanActivities();
        parent::tearDown();
    }

    /**
     * Unknown activities are not edited
     */
    public function testEditUnknownActivity(): void
    {
        $this->logSuperAdmin();
        $id = $this->insertActivity('Climbing') + 1000;

        $test_response = $this->app->handle($this->createRequest('activities_activity_edit', ['id' => (string)$id]));
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('activities_activities')]],
            $test_response->getHeaders()
        );
        $this->assertSame(302, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(['error_detected' => ['No activity #' . $id . '.']]);
    }

    /**
     * Storage errors are reported, not thrown
     */
    public function testStoreError(): void
    {
        $this->logSuperAdmin();

        $request = $this->createRequest('activities_storeactivity_add', [], 'POST')
            ->withParsedBody(['name' => 'Climbing', 'id_group' => '999999', 'comment' => '']);
        //on PostgreSQL, the failing query aborts the test transaction
        $this->zdb->db->query('SAVEPOINT store_error', \Laminas\Db\Adapter\Adapter::QUERY_MODE_EXECUTE);
        $test_response = $this->app->handle($request);
        $this->zdb->db->query('ROLLBACK TO SAVEPOINT store_error', \Laminas\Db\Adapter\Adapter::QUERY_MODE_EXECUTE);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('activities_activity_add')]],
            $test_response->getHeaders()
        );
        $this->expectLogEntry(\Analog\Analog::ERROR, 'Query error');
        $this->expectLogEntry(\Analog\Analog::ERROR, 'Something went wrong');
        $this->expectNoLogEntry();
        $this->expectFlashData(['error_detected' => ['An error occurred while storing the activity.']]);
    }

    /**
     * Stored activities lead to the list
     */
    public function testStoreActivity(): void
    {
        $this->logSuperAdmin();

        $request = $this->createRequest('activities_storeactivity_add', [], 'POST')
            ->withParsedBody(['name' => 'Climbing', 'price' => '12,50', 'comment' => '']);
        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('activities_activities')]],
            $test_response->getHeaders()
        );
        $this->assertSame(303, $test_response->getStatusCode());
        $this->expectNoLogEntry();
        $this->expectFlashData(['success_detected' => ['New activity has been successfully added.']]);
    }

    /**
     * Number of activities per page can be chosen
     */
    public function testFilter(): void
    {
        $this->logSuperAdmin();

        $request = $this->createRequest('activities_filter-activitieslist', [], 'POST')
            ->withParsedBody(['nbshow' => '20']);
        $test_response = $this->app->handle($request);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('activities_activities')]],
            $test_response->getHeaders()
        );
        $this->assertSame(303, $test_response->getStatusCode());
        $this->expectNoLogEntry();

        $test_response = $this->app->handle($this->createRequest('activities_activities'));
        $this->assertSame(200, $test_response->getStatusCode());
        $this->assertStringContainsString('name="nbshow" value="20"', (string)$test_response->getBody());
        $this->expectNoLogEntry();
    }

    /**
     * Session keys do not collide with other plugins ones
     */
    public function testSessionKeysArePrefixed(): void
    {
        $this->logSuperAdmin();
        //plugin-events stores its own entity and filters under these keys
        $this->session->activity = new \stdClass();
        $this->session->activities_filter = new \stdClass();
        $this->session->subscription = new \stdClass();

        //list first: pagination adds Twig globals, impossible once a page has been rendered
        foreach (['activities_activities', 'activities_activity_add', 'activities_subscription_add'] as $route) {
            $test_response = $this->app->handle($this->createRequest($route));
            $this->assertSame(200, $test_response->getStatusCode(), $route);
            $this->expectNoLogEntry();
        }
        unset($this->session->activity, $this->session->activities_filter, $this->session->subscription);
    }

    /**
     * Removal confirmation announces subscriptions removed with the activity
     */
    public function testConfirmRemovalCountsSubscriptions(): void
    {
        $this->logSuperAdmin();
        $activity = $this->insertActivity('Climbing');

        $body = (string)$this->app->handle(
            $this->createRequest('activities_remove_activity', ['id' => (string)$activity])
        )->getBody();
        $this->assertStringNotContainsString('will be removed as well', $body);
        $this->expectNoLogEntry();

        $this->insertSubscription($activity, $this->getMemberOne()->id);
        $this->insertSubscription($activity, $this->getMemberTwo()->id);
        $body = (string)$this->app->handle(
            $this->createRequest('activities_remove_activity', ['id' => (string)$activity])
        )->getBody();
        $this->assertStringContainsString('2 subscriptions to this activity will be removed as well.', $body);
        $this->expectNoLogEntry();
    }

    /**
     * Activities list
     */
    public function testList(): void
    {
        $this->logSuperAdmin();
        $group = $this->createGroup('Climbers');
        $this->insertActivity('Climbing', $group->getId(), ['price' => 12.5]);
        $this->insertActivity('Hiking', null, ['price' => null]);

        $test_response = $this->app->handle($this->createRequest('activities_activities'));
        $this->assertSame(200, $test_response->getStatusCode());
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('2 activities', $body);
        $this->assertStringContainsString('Climbing', $body);
        $this->assertStringContainsString('12.50', $body);
        $this->assertStringContainsString('Climbers', $body);
        $this->assertStringContainsString('Hiking', $body);
        $this->expectNoLogEntry();
    }

    /**
     * Creation and edition forms
     */
    public function testForms(): void
    {
        $this->logSuperAdmin();
        $group = $this->createGroup('Climbers');
        $id = $this->insertActivity('Climbing', $group->getId(), ['type' => 'ESC', 'comment' => 'Indoor']);

        $test_response = $this->app->handle($this->createRequest('activities_activity_add'));
        $this->assertSame(200, $test_response->getStatusCode());
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('action="' . $this->routeparser->urlFor('activities_storeactivity_add') . '"', $body);
        $this->assertStringContainsString('Climbers', $body);
        $this->expectNoLogEntry();

        $test_response = $this->app->handle($this->createRequest('activities_activity_edit', ['id' => (string)$id]));
        $this->assertSame(200, $test_response->getStatusCode());
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString(
            'action="' . $this->routeparser->urlFor('activities_storeactivity_edit', ['id' => (string)$id]) . '"',
            $body
        );
        $this->assertStringContainsString('value="Climbing"', $body);
        $this->assertStringContainsString('value="ESC"', $body);
        $this->assertStringContainsString('Indoor', $body);
        $this->assertMatchesRegularExpression('/<option\s+value="' . $group->getId() . '"\s+selected="selected"/', $body);
        $this->expectNoLogEntry();
    }

    /**
     * Activities are changed, invalid values are displayed again
     */
    public function testEdit(): void
    {
        $this->logSuperAdmin();
        $id = $this->insertActivity('Climbing');
        $post = function (array $data) use ($id): \Psr\Http\Message\ResponseInterface {
            $request = $this->createRequest('activities_storeactivity_edit', ['id' => (string)$id], 'POST')
                ->withParsedBody($data + ['id' => (string)$id, 'comment' => '']);
            return $this->app->handle($request);
        };

        $test_response = $post(['name' => 'Bouldering', 'price' => '8']);
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('activities_activities')]],
            $test_response->getHeaders()
        );
        $this->expectNoLogEntry();
        $this->expectFlashData(['success_detected' => ['Activity has been modified.']]);
        $activity = new \GaletteActivities\Entity\Activity($this->zdb, $id);
        $this->assertSame('Bouldering', $activity->getName());
        $this->assertSame(8.0, $activity->getPrice());

        $test_response = $post(['name' => 'Bouldering', 'type' => 'TOOLONG']);
        $edit_url = $this->routeparser->urlFor('activities_activity_edit', ['id' => (string)$id]);
        $this->assertSame(['Location' => [$edit_url]], $test_response->getHeaders());
        $this->assertSame(303, $test_response->getStatusCode());
        $this->expectLogEntry(\Analog\Analog::ERROR, 'Type is too long');
        $this->expectNoLogEntry();
        $this->expectFlashData(['error_detected' => ['Type is too long']]);
        $this->assertSame('', (new \GaletteActivities\Entity\Activity($this->zdb, $id))->getType());

        //form is displayed again from session
        $this->assertNotNull($this->session->plugin_activities_activity);
        $test_response = $this->app->handle($this->createRequest('activities_activity_edit', ['id' => (string)$id]));
        $this->assertSame(200, $test_response->getStatusCode());
        $this->assertStringContainsString('value="Bouldering"', (string)$test_response->getBody());
        $this->assertFalse(isset($this->session->plugin_activities_activity));
        $this->expectNoLogEntry();
    }

    /**
     * Activities are removed with their subscriptions
     */
    public function testRemove(): void
    {
        $this->logSuperAdmin();
        $id = $this->insertActivity('Climbing');
        $this->insertSubscription($id, $this->getMemberOne()->id);

        //not confirmed
        $request = $this->createRequest('activities_do_remove_activity', ['id' => (string)$id], 'POST');
        $this->app->handle($request->withParsedBody([]));
        $this->expectFlashData(['error_detected' => ['Removal has not been confirmed!']]);
        $this->assertSame(1, $this->countSubscriptions($id));

        $test_response = $this->app->handle($request->withParsedBody(['confirm' => '1']));
        $this->assertSame(
            ['Location' => [$this->routeparser->urlFor('activities_activities')]],
            $test_response->getHeaders()
        );
        $this->expectNoLogEntry();
        $this->expectFlashData(['success_detected' => ['Successfully deleted!']]);
        $this->assertFalse((new \GaletteActivities\Entity\Activity($this->zdb))->load($id));
        $this->assertSame(0, $this->countSubscriptions($id));
    }
}
