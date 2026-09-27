<?php

/**
 * This file is part of Galette Activities plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2024-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteActivities\tests\units;

use Galette\Tests\GaletteTestCase;

/**
 * Activity tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Activity extends GaletteTestCase
{
    protected int $seed = 20240817102541;

    /**
     * Cleanup after each test method
     */
    public function tearDown(): void
    {
        $delete = $this->zdb->delete(ACTIVITIES_PREFIX . \GaletteActivities\Entity\Activity::TABLE);
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(\Galette\Entity\Group::TABLE);
        $this->zdb->execute($delete);

        parent::tearDown();
    }

    /**
     * Test empty
     */
    public function testEmpty(): void
    {
        $activity = new \GaletteActivities\Entity\Activity($this->zdb);

        $this->assertNull($activity->getId());
        $this->assertSame('', $activity->getName());
        $this->assertSame('', $activity->getCreationDate());
        $this->assertNull($activity->getPrice());
        $this->assertSame('', $activity->getComment());
    }

    /**
     * Test add and update
     */
    public function testCrud(): void
    {
        $activity = new \GaletteActivities\Entity\Activity($this->zdb);
        $activities = new \GaletteActivities\Repository\Activities($this->zdb, $this->login, $this->preferences);

        //ensure the table is empty
        $this->assertCount(0, $activities->getList());

        //required activity name
        $data = [
            'comment' => 'Test comment',
        ];
        $this->assertFalse($activity->check($data));
        $this->assertSame(['Name is mandatory'], $activity->getErrors());
        $this->expectLogEntry(
            \Analog\Analog::ERROR,
            'Name is mandatory'
        );

        //required activity name
        $data = [
            'name' => 'Test activity',
            'comment' => 'Test comment',
            'type' => '1234',
        ];
        $this->assertFalse($activity->check($data));
        $this->assertSame(['Type is too long'], $activity->getErrors());
        $this->expectLogEntry(
            \Analog\Analog::ERROR,
            'Type is too long'
        );

        //add new activity
        $data = [
            'name' => 'Test activity',
            'comment' => 'Test comment',
            'type' => 'one'
        ];
        $this->assertTrue($activity->check($data));
        $this->assertTrue($activity->store());
        $first_id = $activity->getId();
        $this->assertGreaterThan(0, $first_id);

        $this->assertTrue($activity->load($first_id));
        $this->assertSame('Test activity', $activity->getName());
        $this->assertSame('Test comment', $activity->getComment());
        $this->assertSame('one', $activity->getType());
        //FIXME: lang must be changed to have a different date format
        $this->assertNotSame('', $activity->getCreationDate());
        $this->assertNull($activity->getPrice());
        $this->assertNull($activity->getGroup());

        $activities_list = $activities->getList();
        $this->assertCount(1, $activities_list);
        $this->assertSame(1, $activities->getCount());
        $lactivity = $activities_list[0];
        $this->assertInstanceOf(\GaletteActivities\Entity\Activity::class, $lactivity);
        $this->assertEquals($activity, $lactivity);

        //edit activity
        $data['name'] = 'Test activity edited';
        $data['price'] = 10.5;
        $data['comment'] = '';
        $this->assertTrue($activity->check($data));
        $this->assertTrue($activity->store());
        $this->assertTrue($activity->load($first_id));

        $this->assertSame('Test activity edited', $activity->getName());
        $this->assertSame(10.5, $activity->getPrice());
        $this->assertSame('', $activity->getComment());

        $group = new \Galette\Entity\Group();
        $group->setName('Test group' . $this->seed);
        $this->assertTrue($group->store());
        $data['id_group'] = $group->getId();
        $this->assertTrue($activity->check($data));
        $this->assertTrue($activity->store());
        $activity = new \GaletteActivities\Entity\Activity($this->zdb, $first_id);

        $this->assertInstanceOf(\Galette\Entity\Group::class, $activity->getGroup());
        $this->assertSame($group->getId(), $activity->getGroup()->getId());

        //remove activity
        $this->assertTrue($activity->remove());
        $this->assertFalse($activity->load($first_id));
    }

    /**
     * Test load error
     */
    public function testLoadError(): void
    {
        $activity = new \GaletteActivities\Entity\Activity($this->zdb);
        $this->assertFalse($activity->load(999));
    }

    /**
     * Assert activity data is refused
     *
     * @param array<string,mixed> $data   Activity data
     * @param array<string>       $errors Expected errors
     */
    private function expectInvalid(array $data, array $errors): void
    {
        $activity = new \GaletteActivities\Entity\Activity($this->zdb);
        $this->assertFalse($activity->check($data));
        $this->assertSame($errors, $activity->getErrors());
        $this->expectLogEntry(\Analog\Analog::ERROR, 'Error(s) checking activity before store');
    }

    /**
     * Names are limited to database length, types counted in characters
     */
    public function testCheckLengths(): void
    {
        $this->expectInvalid(['name' => str_repeat('a', 151)], ['Name is too long']);

        $activity = new \GaletteActivities\Entity\Activity($this->zdb);
        $this->assertTrue($activity->check(['name' => str_repeat('é', 150), 'type' => 'Éàü']));
        $this->assertTrue($activity->store());
        $this->assertTrue($activity->load((int)$activity->getId()));
        $this->assertSame(str_repeat('é', 150), $activity->getName());
        $this->assertSame('Éàü', $activity->getType());
    }

    /**
     * Prices accept comma decimal separator and zero, and can be cleared
     */
    public function testCheckPrice(): void
    {
        $activity = new \GaletteActivities\Entity\Activity($this->zdb);
        $this->assertTrue($activity->check(['name' => 'Climbing', 'price' => '12,50']));
        $this->assertSame(12.5, $activity->getPrice());

        $this->assertTrue($activity->check(['name' => 'Climbing', 'price' => '0']));
        $this->assertSame(0.0, $activity->getPrice());

        $this->assertTrue($activity->check(['name' => 'Climbing', 'price' => '']));
        $this->assertNull($activity->getPrice());

        $this->expectInvalid(['name' => 'Climbing', 'price' => 'twelve'], ['Price must be a number.']);
    }
}
