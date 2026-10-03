<?php

/**
 * This file is part of Galette Activities plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2024-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteActivities\Repository;

use Analog\Analog;
use Galette\Core\Db;
use Galette\Core\History;
use Galette\Core\Login;
use Galette\Core\Preferences;
use GaletteActivities\Entity\Activity;
use GaletteActivities\Filters\ActivitiesList;

/**
 * Activities
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Activities extends AbstractRepository
{
    protected const string PK = Activity::PK;
    protected const string ALIAS = 'ac';

    /** @var ActivitiesList */
    protected \Galette\Core\Pagination $filters;

    public const int ORDERBY_DATE = 0;
    public const int ORDERBY_NAME = 1;

    /**
     * Constructor
     *
     * @param Db              $zdb         Database instance
     * @param Login           $login       Login instance
     * @param History         $history     History instance
     * @param Preferences     $preferences Preferences instance
     * @param ?ActivitiesList $filters     Filtering
     */
    public function __construct(
        Db $zdb,
        Login $login,
        History $history,
        Preferences $preferences,
        ?ActivitiesList $filters = null
    ) {
        parent::__construct($zdb, $login, $history, $preferences, 'Entity\Activity', $filters ?? new ActivitiesList());
    }

    /**
     * Get activities list
     *
     * @return array<int, Activity>
     */
    public function getList(): array
    {
        try {
            $select = $this->zdb->select(ACTIVITIES_PREFIX . Activity::TABLE, self::ALIAS);
            $select->order($this->buildOrderClause());

            $this->proceedCount($select);

            $this->filters->setLimits($select);
            $results = $this->zdb->execute($select);

            $activities = [];
            foreach ($results as $row) {
                $activities[] = new Activity($this->zdb, $this->history, $row);
            }

            return $activities;
        } catch (\Exception $e) {
            Analog::log(
                'Cannot list activities | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Builds the order clause
     *
     * @return array<string> SQL ORDER clauses
     */
    private function buildOrderClause(): array
    {
        $order = [];

        switch ($this->filters->orderby) {
            case self::ORDERBY_DATE:
                $order[] = 'creation_date ' . $this->filters->getDirection();
                break;
            case self::ORDERBY_NAME:
                $order[] = 'name ' . $this->filters->getDirection();
                break;
        }

        return $order;
    }
}
