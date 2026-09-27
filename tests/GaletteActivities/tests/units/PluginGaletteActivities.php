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
 * Plugin class tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class PluginGaletteActivities extends GaletteTestCase
{
    use ActivitiesFixtures;

    protected int $seed = 20260927091512;

    /**
     * Cleanup after each test method
     */
    public function tearDown(): void
    {
        $this->login->logout();
        parent::tearDown();
    }

    /**
     * Get plugin instance
     */
    private function getPlugin(): \GaletteActivities\PluginGaletteActivities
    {
        return $this->container->get(\GaletteActivities\PluginGaletteActivities::class);
    }

    /**
     * Get routes names of menus entries
     *
     * @param array<string|int, mixed> $menus Menus
     *
     * @return array<string, array<string>>
     */
    private function getMenusRoutes(array $menus): array
    {
        $routes = [];
        foreach ($menus as $section => $menu) {
            $routes[$section] = array_map(
                fn(array $item): string => $item['route']['name'],
                $menu['items']
            );
        }
        return $routes;
    }

    /**
     * Menus and member actions are for staff only
     */
    public function testMenusAndActions(): void
    {
        $plugin = $this->getPlugin();
        $member_one = $this->getMemberOne();
        $member_two = $this->getMemberTwo();
        $expected_menus = ['plugin_activities' => ['activities_activities', 'activities_subscriptions']];

        //visitor
        $this->assertSame([], $plugin->getMenus());
        $this->assertSame([], $plugin->getListActions($member_two));

        //member, and group manager
        $this->createGroup('Managed group', [$member_one], [$member_two]);
        $this->logMember($this->dataAdherentOne());
        $this->assertTrue($this->login->isGroupManager());
        $this->assertSame([], $plugin->getMenus());
        $this->assertSame([], $plugin->getListActions($member_two));
        $this->assertSame([], $plugin->getDetailedActions($member_two));
        $this->login->logout();

        //staff
        $staff = $this->getStaffMember($member_one);
        $this->logMember($this->dataAdherentOne());
        $this->assertTrue($this->login->isStaff());
        //results depend on logged in user
        $plugin = $this->getPlugin();
        $this->assertSame($expected_menus, $this->getMenusRoutes($plugin->getMenus()));
        $actions = $plugin->getListActions($member_two);
        $this->assertCount(1, $actions);
        $this->assertSame(
            ['name' => 'activities_subscription_add', 'args' => ['id_adh' => $member_two->id]],
            $actions[0]['route']
        );
        $this->assertStringContainsString($member_two->sname, $actions[0]['label']);
        $this->assertSame($actions, $plugin->getDetailedActions($member_two));
        $this->login->logout();
        $this->resetStaffStatus($staff, $member_two);

        //administrator
        $this->logSuperAdmin();
        $this->assertSame($expected_menus, $this->getMenusRoutes($plugin->getMenus()));
        $this->assertCount(1, $plugin->getListActions($member_two));
    }

    /**
     * Nothing public, no batch actions
     */
    public function testPublicMenusAndBatchActions(): void
    {
        $plugin = $this->getPlugin();
        $this->logSuperAdmin();
        $this->assertSame([], $plugin->getPublicMenus());
        $this->assertSame([], $plugin->getBatchActions());
    }

    /**
     * Plugin is installed once its tables exist
     */
    public function testIsInstalled(): void
    {
        $this->assertTrue($this->getPlugin()->isInstalled());
    }

    /**
     * Tables created before 1.1 are detected; on MySQL, schema changes cannot be rolled back,
     * the CI upgrade job covers them
     */
    public function testLegacyDbVersion(): void
    {
        $plugin = $this->getPlugin();
        $this->assertNull($plugin->getLegacyDbVersion());

        if ($this->zdb->isPostgres()) {
            //rolled back with the test transaction
            $this->zdb->db->query(
                'ALTER TABLE ' . PREFIX_DB . ACTIVITIES_PREFIX . \GaletteActivities\Entity\Subscription::TABLE
                . ' ALTER COLUMN ' . \GaletteActivities\Entity\Activity::PK . ' DROP NOT NULL',
                \Laminas\Db\Adapter\Adapter::QUERY_MODE_EXECUTE
            );
            $this->assertSame(1.0, $plugin->getLegacyDbVersion());
        }
    }
}
