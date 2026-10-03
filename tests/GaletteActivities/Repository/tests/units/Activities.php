<?php

/**
 * This file is part of Galette Activities plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2024-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteActivities\Repository\tests\units;

use Galette\Tests\GaletteTestCase;
use GaletteActivities\Filters\ActivitiesList;
use GaletteActivities\tests\ActivitiesFixtures;

/**
 * Activities repository tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Activities extends GaletteTestCase
{
    use ActivitiesFixtures;

    protected int $seed = 20260927093012;

    /**
     * Cleanup after each test method
     */
    public function tearDown(): void
    {
        $this->cleanActivities();
        parent::tearDown();
    }

    /**
     * Get activities names, in list order
     *
     * @param ActivitiesList $filters Filters
     *
     * @return array<string>
     */
    private function getListNames(ActivitiesList $filters): array
    {
        $activities = new \GaletteActivities\Repository\Activities($this->zdb, $this->login, $this->history, $this->preferences, $filters);
        $names = [];
        foreach ($activities->getList() as $activity) {
            $names[] = $activity->getName();
        }
        $this->assertSame(3, $activities->getCount());
        return $names;
    }

    /**
     * Activities are ordered and paginated
     */
    public function testList(): void
    {
        $this->insertActivity('Climbing', null, ['creation_date' => '2026-01-01']);
        $this->insertActivity('Diving', null, ['creation_date' => '2026-03-01']);
        $this->insertActivity('Hiking', null, ['creation_date' => '2026-02-01']);

        //newest first
        $filters = new ActivitiesList();
        $this->assertSame(['Diving', 'Hiking', 'Climbing'], $this->getListNames($filters));

        $filters->orderby = \GaletteActivities\Repository\Activities::ORDERBY_NAME;
        $this->assertSame(['Hiking', 'Diving', 'Climbing'], $this->getListNames($filters));
        //same order again inverts direction
        $filters->orderby = \GaletteActivities\Repository\Activities::ORDERBY_NAME;
        $this->assertSame(['Climbing', 'Diving', 'Hiking'], $this->getListNames($filters));

        $filters->show = 2;
        $this->assertSame(['Climbing', 'Diving'], $this->getListNames($filters));
        $filters->current_page = 2;
        $this->assertSame(['Hiking'], $this->getListNames($filters));
    }
}
