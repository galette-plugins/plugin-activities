<?php

/**
 * This file is part of Galette Activities plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2024-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteActivities\Controllers\tests\units;

use Galette\Tests\GaletteRoutingTestCase;
use GaletteActivities\tests\ActivitiesFixtures;

/**
 * Plugin routes access tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Routes extends GaletteRoutingTestCase
{
    use ActivitiesFixtures;

    protected int $seed = 20260926173105;
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
     * Build a request for every plugin route
     *
     * @return array<string,\Slim\Psr7\Request>
     */
    private function getPluginRequests(): array
    {
        $activity = $this->insertActivity('Climbing');
        $subscription = $this->insertSubscription($activity, $this->getMemberOne()->id);

        $requests = [];
        foreach ($this->app->getRouteCollector()->getRoutes() as $route) {
            $name = (string)$route->getName();
            if (!str_starts_with($name, 'activities_')) {
                continue;
            }
            //only required arguments, optional ones are enclosed in brackets
            preg_match_all('/{(\w+)/', (string)preg_replace('/\[.*]/', '', $route->getPattern()), $matches);
            $args = [];
            foreach ($matches[1] as $arg) {
                $args[$arg] = (string)(str_contains($name, 'subscription') ? $subscription : $activity);
            }
            foreach ($route->getMethods() as $method) {
                $requests[$method . ' ' . $name] = $this->createRequest($name, $args, $method)
                    ->withParsedBody(['id' => $args['id'] ?? '', 'save' => '1']);
            }
        }

        //ensure routes have been found; a new route is checked without any change here
        $this->assertGreaterThanOrEqual(15, count($requests));
        return $requests;
    }

    /**
     * Visitors are sent to login page on every route
     */
    public function testVisitorRefused(): void
    {
        foreach ($this->getPluginRequests() as $label => $request) {
            try {
                $this->expectLogin($this->app->handle($request));
            } catch (\PHPUnit\Framework\AssertionFailedError $e) {
                $this->fail($label . ': ' . $e->getMessage());
            }
        }
    }

    /**
     * Members are refused on every route
     */
    public function testMemberRefused(): void
    {
        $requests = $this->getPluginRequests();
        $this->logMember($this->dataAdherentOne());
        foreach ($requests as $label => $request) {
            try {
                $this->expectAuthMiddlewareRefused($this->app->handle($request));
            } catch (\PHPUnit\Framework\AssertionFailedError $e) {
                $this->fail($label . ': ' . $e->getMessage());
            }
        }
    }
}
