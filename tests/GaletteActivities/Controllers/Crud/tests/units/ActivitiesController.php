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
        $this->expectLogEntry(\Analog::ERROR, 'Query error');
        $this->expectLogEntry(\Analog::ERROR, 'Something went wrong');
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
}
