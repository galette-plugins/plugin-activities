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
}
