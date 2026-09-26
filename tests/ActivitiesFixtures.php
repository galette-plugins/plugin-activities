<?php

/**
 * This file is part of Galette Activities plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2024-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteActivities\tests;

use Galette\Entity\Adherent;
use Galette\Entity\Group;
use GaletteActivities\Entity\Activity;
use GaletteActivities\Entity\Subscription;

/**
 * Activities, subscriptions and groups for plugin tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
trait ActivitiesFixtures
{
    /**
     * Remove plugin data
     */
    protected function cleanActivities(): void
    {
        foreach ([Subscription::TABLE, Activity::TABLE] as $table) {
            $this->zdb->execute($this->zdb->delete(ACTIVITIES_PREFIX . $table));
        }
    }

    /**
     * Log in given member
     *
     * @param array<string,mixed> $mdata Member data
     */
    protected function logMember(array $mdata): void
    {
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
    }

    /**
     * Create a group
     *
     * @param string     $name     Group name
     * @param Adherent[] $managers Group managers
     * @param Adherent[] $members  Group members
     */
    protected function createGroup(string $name, array $managers = [], array $members = []): Group
    {
        $group = new Group();
        $group->setName($name);
        $this->assertTrue($group->store());
        if (count($managers)) {
            $this->assertTrue($group->setManagers($managers));
        }
        if (count($members)) {
            $this->assertTrue($group->setMembers($members));
        }
        return $group;
    }

    /**
     * Insert an activity
     *
     * @param string   $name     Activity name
     * @param int|null $id_group Group ID
     *
     * @return int Activity ID
     */
    protected function insertActivity(string $name, ?int $id_group = null): int
    {
        $insert = $this->zdb->insert(ACTIVITIES_PREFIX . Activity::TABLE);
        $insert->values([
            'name'          => $name,
            'price'         => 10,
            Group::PK       => $id_group,
            'creation_date' => date('Y-m-d'),
            'comment'       => '',
        ]);
        $this->zdb->execute($insert);

        $select = $this->zdb->select(ACTIVITIES_PREFIX . Activity::TABLE);
        $select->where(['name' => $name]);
        return (int)$this->zdb->execute($select)->current()[Activity::PK];
    }

    /**
     * Insert a subscription
     *
     * @param int $activity Activity ID
     * @param int $member   Member ID
     *
     * @return int Subscription ID
     */
    protected function insertSubscription(int $activity, int $member): int
    {
        $insert = $this->zdb->insert(ACTIVITIES_PREFIX . Subscription::TABLE);
        $insert->values([
            Activity::PK        => $activity,
            Adherent::PK        => $member,
            'is_paid'           => $this->zdb->isPostgres() ? 'false' : 0,
            'payment_method'    => 0,
            'creation_date'     => date('Y-m-d'),
            'subscription_date' => date('Y-m-d'),
            'end_date'          => date('Y-m-d', strtotime('+1 year')),
            'comment'           => '',
        ]);
        $this->zdb->execute($insert);

        $select = $this->zdb->select(ACTIVITIES_PREFIX . Subscription::TABLE);
        $select->where([Activity::PK => $activity, Adherent::PK => $member]);
        return (int)$this->zdb->execute($select)->current()[Subscription::PK];
    }

    /**
     * Count subscriptions of an activity
     *
     * @param int $activity Activity ID
     */
    protected function countSubscriptions(int $activity): int
    {
        $select = $this->zdb->select(ACTIVITIES_PREFIX . Subscription::TABLE);
        $select->where([Activity::PK => $activity]);
        return $this->zdb->execute($select)->count();
    }

    /**
     * Is member in group?
     *
     * @param int $group  Group ID
     * @param int $member Member ID
     */
    protected function isInGroup(int $group, int $member): bool
    {
        $select = $this->zdb->select(Group::GROUPSUSERS_TABLE);
        $select->where([Group::PK => $group, Adherent::PK => $member]);
        return $this->zdb->execute($select)->count() > 0;
    }
}
