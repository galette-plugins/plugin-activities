<?php

/**
 * This file is part of Galette Activities plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2024-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteActivities;

use DI\Attribute\Inject;
use Galette\Core\Db;
use Galette\Core\Login;
use Galette\Core\Plugins\MemberActionProviderInterface;
use Galette\Core\Plugins\MenuProviderInterface;
use Galette\Entity\Adherent;
use Galette\Entity\Group;
use Galette\Core\GalettePlugin;
use GaletteActivities\Entity\Activity;
use GaletteActivities\Entity\Subscription;
use Laminas\Db\Metadata\Object\ConstraintObject;
use Laminas\Db\Metadata\Source\Factory;

/**
 * Galette Activities plugin
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class PluginGaletteActivities extends GalettePlugin implements MenuProviderInterface, MemberActionProviderInterface
{
    #[Inject]
    private readonly Db $zdb; //@phpstan-ignore property.uninitializedReadonly,property.onlyRead (injected from DI)

    #[Inject]
    private readonly Login $login; //@phpstan-ignore property.uninitializedReadonly,property.onlyRead (injected from DI)

    /**
     * Get plugins menus
     *
     * @return array<string, string|array<string, mixed>>
     */
    public function getMenus(): array
    {
        $menus = [];

        if ($this->login->isAdmin() || $this->login->isStaff()) {
            $menus['plugin_activities'] = [
                'title' => _T("Activities", "activities"),
                'icon' => 'calendar alternate',
                'items' => [
                    [
                        'label' => _T('Activities', 'activities'),
                        'route' => [
                            'name' => 'activities_activities',
                            'aliases' => ['activities_activity_add', 'activities_activity_edit']
                        ]
                    ],
                    [
                        'label' => _T('Subscriptions', 'activities'),
                        'route' => [
                            'name' => 'activities_subscriptions',
                            'aliases' => ['activities_subscription_add', 'activities_subscription_edit']
                        ]
                    ]
                ]
            ];
        }

        return $menus;
    }

    /**
     * Get plugins public menus
     *
     * @return array<int, string|array<string, mixed>>
     */
    public function getPublicMenus(): array
    {
        return [];
    }

    /**
     * Get member actions
     *
     * @param Adherent $member Member instance
     *
     * @return array<int, string|array<string, mixed>>
     */
    public function getListActions(Adherent $member): array
    {
        if (!$this->login->isAdmin() && !$this->login->isStaff()) {
            return [];
        }

        return [
            [
                'label' => str_replace(
                    '%membername',
                    $member->sname,
                    //TRANS %membername will be replaced with current member name
                    _T("New subscription for %membername", "activities")
                ),
                'route' => [
                    'name' => 'activities_subscription_add',
                    'args' => [Adherent::PK => $member->id]
                ],
                'icon' => 'money check alternate grey'
            ],
        ];
    }

    /**
     * Get detailed member actions
     *
     * @param Adherent $member Member instance
     *
     * @return array<int, string|array<string, mixed>>
     */
    public function getDetailedActions(Adherent $member): array
    {
        return $this->getListActions($member);
    }

    /**
     * Get member batch actions
     *
     * @return array<int, string|array<string, mixed>>
     */
    public function getBatchActions(): array
    {
        return [];
    }

    /**
     * Is the plugin fully installed (including database, extra configuration, etc.)?
     */
    public function isInstalled(): bool
    {
        return
            $this->zdb->tableExists(ACTIVITIES_PREFIX . Activity::TABLE)
            && $this->zdb->tableExists(ACTIVITIES_PREFIX . Subscription::TABLE);
    }

    /**
     * Database version of tables created before plugins versions tracking
     *
     * Before 1.1, the group foreign key was not updated in cascade on MySQL,
     * and subscriptions columns accepted NULL on PostgreSQL.
     */
    public function getLegacyDbVersion(): ?float
    {
        $metadata = Factory::createSourceFromAdapter($this->zdb->db);
        /** @var ConstraintObject $constraint */
        foreach ($metadata->getConstraints(PREFIX_DB . ACTIVITIES_PREFIX . Activity::TABLE) as $constraint) {
            if ($constraint->isForeignKey() && $constraint->getColumns() === [Group::PK]) {
                if ($constraint->getUpdateRule() !== 'CASCADE') {
                    return 1.0;
                }
            }
        }

        $column = $metadata->getColumn(Activity::PK, PREFIX_DB . ACTIVITIES_PREFIX . Subscription::TABLE);
        return $column->isNullable() ? 1.0 : null;
    }
}
