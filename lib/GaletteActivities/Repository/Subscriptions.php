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
use Galette\Entity\Adherent;
use Galette\Entity\Status;
use GaletteActivities\Entity\Activity;
use GaletteActivities\Entity\Subscription;
use GaletteActivities\Filters\SubscriptionsList;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Select;

/**
 * Subscriptions
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Subscriptions extends AbstractRepository
{
    protected const string PK = Subscription::PK;
    protected const string ALIAS = 's';

    /** @var SubscriptionsList */
    protected \Galette\Core\Pagination $filters;
    private float $sum = 0;

    public const int ORDERBY_ACTIVITY = 0;
    public const int ORDERBY_MEMBER = 1;
    public const int ORDERBY_SUBSCRIPTIONDATE = 2;
    public const int ORDERBY_ENDDATE = 3;
    public const int ORDERBY_AMOUNT = 4;
    public const int ORDERBY_PAID = 5;

    public const int FILTER_DC_PAID = 0;
    public const int FILTER_PAID = 1;
    public const int FILTER_NOT_PAID = 2;

    /**
     * Constructor
     *
     * @param Db                 $zdb         Database instance
     * @param Login              $login       Login instance
     * @param History            $history     History instance
     * @param Preferences        $preferences Preferences instance
     * @param ?SubscriptionsList $filters     Filtering
     */
    public function __construct(
        Db $zdb,
        Login $login,
        History $history,
        Preferences $preferences,
        ?SubscriptionsList $filters = null
    ) {
        parent::__construct(
            $zdb,
            $login,
            $history,
            $preferences,
            'Entity\Subscription',
            $filters ?? new SubscriptionsList()
        );
    }

    /**
     * Get subscriptions list
     *
     * @param bool $full Export full list (no pagination), defaults to false
     *
     * @return array<Subscription>
     */
    public function getList(bool $full = false): array
    {
        try {
            $select = $this->buildSelect();
            $this->calculateSum($select);
            $this->proceedCount($select);
            $select->order($this->buildOrderClause());

            if ($full !== true) {
                $this->filters->setLimits($select);
            }
            $results = $this->zdb->execute($select);

            $subscriptions = [];
            foreach ($results as $row) {
                $subscriptions[] = new Subscription($this->zdb, $this->history, $row);
            }
            $this->loadActivities($subscriptions);
            $this->loadMembers($subscriptions);

            return $subscriptions;
        } catch (\Exception $e) {
            Analog::log(
                'Cannot list subscriptions | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Load activities of listed subscriptions, once each
     *
     * @param array<Subscription> $subscriptions Subscriptions
     */
    private function loadActivities(array $subscriptions): void
    {
        $ids = array_unique(array_map(fn(Subscription $subscription): int => (int)$subscription->getActivityId(), $subscriptions));
        if (count($ids) === 0) {
            return;
        }

        $select = $this->zdb->select(ACTIVITIES_PREFIX . Activity::TABLE);
        $select->where([Activity::PK => array_values($ids)]);
        $activities = [];
        foreach ($this->zdb->execute($select) as $row) {
            $activities[(int)$row[Activity::PK]] = new Activity($this->zdb, $this->history, $row);
        }

        foreach ($subscriptions as $subscription) {
            $subscription->useActivity($activities[$subscription->getActivityId()]);
        }
    }

    /**
     * Load members of listed subscriptions, once each
     *
     * @param array<Subscription> $subscriptions Subscriptions
     */
    private function loadMembers(array $subscriptions): void
    {
        $ids = array_unique(array_map(fn(Subscription $subscription): int => (int)$subscription->getMemberId(), $subscriptions));
        if (count($ids) === 0) {
            return;
        }

        //same query as a member loaded from its id
        $select = $this->zdb->select(Adherent::TABLE, 'a');
        $select->join(
            ['b' => PREFIX_DB . Status::TABLE],
            'a.' . Status::PK . '=b.' . Status::PK,
            ['priorite_statut']
        )->where(['a.' . Adherent::PK => array_values($ids)]);
        $members = [];
        foreach ($this->zdb->execute($select) as $row) {
            $members[(int)$row[Adherent::PK]] = new Adherent($this->zdb, $row, false);
        }

        foreach ($subscriptions as $subscription) {
            $subscription->useMember($members[$subscription->getMemberId()]);
        }
    }

    /**
     * Builds the SELECT statement, filtered but neither ordered nor limited
     */
    private function buildSelect(): Select
    {
        $select = $this->zdb->select(ACTIVITIES_PREFIX . Subscription::TABLE, self::ALIAS);
        $select->columns([Subscription::PK, Activity::PK, Adherent::PK, 'is_paid', 'payment_amount',
            'payment_method', 'creation_date', 'subscription_date', 'end_date', 'comment']);

        //joined tables are used for filtering and ordering only, their columns would override subscriptions ones
        $select->join(
            ['a' => PREFIX_DB . Adherent::TABLE],
            's.' . Adherent::PK . '= a.' . Adherent::PK,
            []
        );
        $select->join(
            ['ac' => PREFIX_DB . ACTIVITIES_PREFIX . Activity::TABLE],
            's.' . Activity::PK . '= ac.' . Activity::PK,
            []
        );

        $this->buildWhereClause($select);
        return $select;
    }

    /**
     * Calculate sum of all selected subscriptions
     *
     * @param Select $select Original select
     */
    private function calculateSum(Select $select): void
    {
        $sumSelect = clone $select;
        $sumSelect->columns(['sum' => new Expression('SUM(s.payment_amount)')]);
        $this->sum = round((float)$this->zdb->execute($sumSelect)->current()['sum'], 2);
    }

    /**
     * Builds where clause, for filtering on simple list mode
     *
     * @param Select $select Original select
     */
    private function buildWhereClause(Select $select): void
    {
        try {
            switch ($this->filters->paid_filter) {
                case self::FILTER_PAID:
                    $select->where(['s.is_paid' => $this->zdb->isPostgres() ? 'true' : 1]);
                    break;
                case self::FILTER_NOT_PAID:
                    $select->where(['s.is_paid' => $this->zdb->isPostgres() ? 'false' : 0]);
                    break;
                case self::FILTER_DC_PAID:
                    //nothing to do here.
                    break;
            }

            if (
                $this->filters->activity_filter !== null
                && $this->filters->activity_filter != -1
            ) {
                $select->where(['s.' . Activity::PK => $this->filters->activity_filter]);
            }

            if ($this->filters->payment_type_filter != -1) {
                $select->where->equalTo(
                    's.payment_method',
                    $this->filters->payment_type_filter
                );
            }

            if ($this->filters->member_filter !== null) {
                $select->where->equalTo(
                    'a.' . Adherent::PK,
                    $this->filters->member_filter
                );
            }

            switch ($this->filters->date_field) {
                case SubscriptionsList::DATE_CREATION:
                    $field = 's.creation_date';
                    break;
                case SubscriptionsList::DATE_SUBSCRIPTION:
                    $field = 's.subscription_date';
                    break;
                case SubscriptionsList::DATE_END:
                default:
                    $field = 's.end_date';
                    break;
            }

            if ($this->filters->start_date_filter != null) {
                $d = new \DateTime($this->filters->rstart_date_filter);
                $select->where->greaterThanOrEqualTo(
                    $field,
                    $d->format('Y-m-d')
                );
            }

            if ($this->filters->end_date_filter != null) {
                $d = new \DateTime($this->filters->rend_date_filter);
                $select->where->lessThanOrEqualTo(
                    $field,
                    $d->format('Y-m-d')
                );
            }

            if (count($this->filters->selected)) {
                $select->where(['s.' . Subscription::PK => $this->filters->selected]);
            }
        } catch (\Exception $e) {
            Analog::log(
                __METHOD__ . ' | ' . $e->getMessage(),
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
        $direction = $this->filters->getDirection();
        return match ($this->filters->orderby) {
            self::ORDERBY_ACTIVITY => ['ac.name ' . $direction],
            self::ORDERBY_MEMBER => ['a.nom_adh ' . $direction, 'a.prenom_adh ' . $direction],
            self::ORDERBY_SUBSCRIPTIONDATE => ['s.subscription_date ' . $direction],
            self::ORDERBY_ENDDATE => ['s.end_date ' . $direction],
            self::ORDERBY_PAID => ['s.is_paid ' . $direction],
            self::ORDERBY_AMOUNT => ['s.payment_amount ' . $direction],
            default => [],
        };
    }

    /**
     * Get sum
     */
    public function getSum(): float
    {
        return $this->sum;
    }
}
