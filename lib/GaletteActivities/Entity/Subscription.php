<?php

/**
 * This file is part of Galette Activities plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2024-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteActivities\Entity;

use ArrayObject;
use Galette\Core\Db;
use Galette\Entity\Adherent;
use Galette\Entity\Group;
use Galette\Entity\PaymentType;
use Analog\Analog;
use Galette\Helpers\EntityHelper;

/**
 * Subscription entity
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Subscription
{
    use EntityHelper;

    public const string TABLE = 'subscriptions';
    public const string PK = 'id_subscription';

    private Db $zdb;
    /** @var array<string> */
    private array $errors;

    private int $id;
    private int $id_activity;
    private ?Activity $activity = null;
    private int $id_member;
    private ?Adherent $member = null;
    //activity and member as stored in database
    private ?int $stored_activity = null;
    private ?int $stored_member = null;
    private bool $paid = true;
    private ?float $payment_amount = null;
    private int $payment_method = PaymentType::OTHER;
    private ?string $creation_date;
    private ?string $subscription_date = null;
    private ?string $end_date = null;
    private string $comment = '';

    /**
     * Default constructor
     *
     * @param Db                                      $zdb  Database instance
     * @param null|int|ArrayObject<string,int|string> $args Either a ResultSet row or its id for to load
     *                                                      a specific subscription, or null to just
     *                                                      instanciate object
     */
    public function __construct(Db $zdb, int|ArrayObject|null $args = null)
    {
        $this->zdb = $zdb;
        $this->setFields();

        $this->creation_date = date("Y-m-d");
        if (is_int($args)) {
            $this->load($args);
        } elseif (is_object($args)) {
            $this->loadFromRS($args);
        }
    }

    /**
     * Loads a subscription from its id
     *
     * @param int $id the identifiant for the subscription to load
     */
    public function load(int $id): bool
    {
        try {
            $select = $this->zdb->select($this->getTableName());
            $select->where([self::PK => $id]);

            $results = $this->zdb->execute($select);

            if ($results->count() > 0) {
                $this->loadFromRS($results->current());
                return true;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            Analog::log(
                'Cannot load subscription form id `' . $id . '` | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Populate object from a resultset row
     *
     * @param ArrayObject<string, int|string> $r the resultset row
     */
    private function loadFromRS(ArrayObject $r): void
    {
        $this->id = (int)$r->id_subscription;
        $this->setActivity((int)$r->{Activity::PK});
        $this->setMember((int)$r->{Adherent::PK});
        $this->stored_activity = $this->id_activity;
        $this->stored_member = $this->id_member;
        $this->paid = (bool)$r->is_paid;
        if ($r->payment_amount !== null) {
            $this->payment_amount = (float)$r->payment_amount;
        }
        $this->payment_method = (int)$r->payment_method;
        $this->creation_date = $r->creation_date;
        $this->subscription_date = $r->subscription_date;
        $this->end_date = $r->end_date;
        $this->comment = $r->comment ?? '';
    }

    /**
     * Remove specified subscription
     */
    public function remove(): bool
    {
        $transaction = false;

        try {
            if (!$this->zdb->connection->inTransaction()) {
                $this->zdb->connection->beginTransaction();
                $transaction = true;
            }

            $delete = $this->zdb->delete($this->getTableName());
            $delete->where([self::PK => $this->id]);
            $this->zdb->execute($delete);

            //commit all changes
            if ($transaction) {
                $this->zdb->connection->commit();
            }

            return true;
        } catch (\Exception $e) {
            if ($transaction) {
                $this->zdb->connection->rollBack();
            }
            Analog::log(
                'Unable to delete subscription '
                . ' (' . $this->id . ') |' . $e->getMessage(),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Check posted values validity
     *
     * @param array<string,mixed> $values All values to check, basically the $_POST array
     *                                    after sending the form
     */
    public function check(array $values): bool
    {
        $this->errors = [];

        if (!isset($values['activity']) || empty($values['activity']) || $values['activity'] == -1) {
            $this->errors[] = _T('Activity is mandatory', 'activities');
        } else {
            $this->setActivity((int)$values['activity']);
            if ($this->activity?->getId() === null) {
                $this->errors[] = sprintf(
                    //TRANS: %1$s is the activity ID
                    _T('No activity #%1$s.', 'activities'),
                    (int)$values['activity']
                );
            }
        }

        //financial information
        if (isset($values['paid']) && $values['paid']) {
            $this->paid = true;
        } else {
            $this->paid = false;
        }

        $amount = null;
        if (isset($values['payment_amount'])) {
            //accept comma as decimal separator
            $amount = strtr(trim((string)$values['payment_amount']), ',', '.');
        }
        if ($amount !== null && $amount !== '') {
            if (is_numeric($amount)) {
                $this->payment_amount = (float)$amount;
            } else {
                $this->errors[] = _T('Amount must be a number.', 'activities');
            }
        } elseif ($amount === null || empty($this->id)) {
            //new subscriptions default to activity price; existing ones can be cleared
            if (isset($values['save']) && $this->activity !== null) {
                $this->payment_amount = $this->activity->getPrice();
            }
        } else {
            $this->payment_amount = null;
        }

        if (isset($values['creation_date']) && !empty($values['creation_date'])) {
            $this->setDate('creation_date', $values['creation_date']);
        }

        if (isset($values['payment_method'])) {
            $this->payment_method = (int)$values['payment_method'];
        }

        if (!isset($values['member']) || empty($values['member'])) {
            $this->errors[] = _T('Member is mandatory', 'activities');
        } elseif (!$this->memberExists((int)$values['member'])) {
            $this->errors[] = sprintf(
                _T('No member #%1$s.'),
                (int)$values['member']
            );
        } else {
            $this->setMember((int)$values['member']);
        }

        if (isset($values['comment'])) {
            $this->comment = $values['comment'];
        }

        $errors_count = count($this->errors);
        if (!isset($values['subscription_date']) || empty($values['subscription_date'])) {
            $this->errors[] = _T('Subscription date is mandatory', 'activities');
        } else {
            $this->setDate('subscription_date', $values['subscription_date']);
        }

        if (!isset($values['end_date']) || empty($values['end_date'])) {
            $this->errors[] = _T('End date is mandatory', 'activities');
        } else {
            $this->setDate('end_date', $values['end_date']);
        }

        if (
            count($this->errors) === $errors_count
            && $this->end_date < $this->subscription_date
        ) {
            $this->errors[] = _T('End date must not be before subscription date.', 'activities');
        }

        if (count($this->errors) > 0) {
            Analog::log(
                'Some errors has been threw attempting to edit/store a subscription' . "\n"
                . print_r($this->errors, true),
                Analog::ERROR
            );
            return false;
        }

        return true;
    }

    /**
     * Does member exist?
     *
     * @param int $id Member ID
     */
    private function memberExists(int $id): bool
    {
        $select = $this->zdb->select(Adherent::TABLE);
        $select->columns([Adherent::PK])->where([Adherent::PK => $id]);
        return $this->zdb->execute($select)->count() > 0;
    }

    /**
     * Store the subscription
     */
    public function store(): bool
    {
        global $hist;

        if ($this->isDuplicate()) {
            //checked before any query: on PostgreSQL, a failing query aborts the whole transaction
            $this->errors[] = _T('Subscription already exists for this member and activity', 'activities');
            return false;
        }

        $transaction = false;
        try {
            if (!$this->zdb->connection->inTransaction()) {
                $this->zdb->connection->beginTransaction();
                $transaction = true;
            }
            $values = [
                Activity::PK => $this->id_activity,
                Adherent::PK => $this->id_member,
                'is_paid' => ($this->paid
                    ?: ($this->zdb->isPostgres() ? 'false' : 0)),
                'payment_method' => $this->payment_method,
                'payment_amount' => $this->payment_amount,
                'creation_date' => $this->creation_date,
                'subscription_date' => $this->subscription_date,
                'end_date' => $this->end_date,
                'comment' => $this->comment
            ];

            if (empty($this->id)) {
                //we're inserting a new subscription
                $insert = $this->zdb->insert($this->getTableName());
                $insert->values($values);
                $add = $this->zdb->execute($insert);
                if ($add->count() > 0) {
                    if ($this->zdb->isPostgres()) {
                        /** @phpstan-ignore-next-line */
                        $this->id = (int)$this->zdb->driver->getLastGeneratedValue(
                            PREFIX_DB . ACTIVITIES_PREFIX . self::TABLE . '_id_seq'
                        );
                    } else {
                        $this->id = (int)$this->zdb->driver->getLastGeneratedValue();
                    }

                    // logging
                    $hist->add(
                        _T("Subscription added", "activities"),
                        $this->getActivity()->getName()
                    );
                } else {
                    $hist->add(_T("Fail to add new subscription.", "activities"));
                    throw new \Exception(
                        'An error occurred inserting new subscription!'
                    );
                }
            } else {
                //we're editing an existing subscription
                $values[self::PK] = $this->id;
                $update = $this->zdb->update($this->getTableName());
                $update
                    ->set($values)
                    ->where([self::PK => $this->id]);

                $edit = $this->zdb->execute($update);

                //edit == 0 does not mean there were an error, but that there
                //were nothing to change
                if ($edit->count() > 0) {
                    $hist->add(
                        _T("Subscription updated", "activities")
                    );
                }
            }

            //members join the activity group when they subscribe, or change activity; they never leave it
            if ($this->id_activity !== $this->stored_activity || $this->id_member !== $this->stored_member) {
                $this->joinActivityGroup();
            }

            if ($transaction) {
                $this->zdb->connection->commit();
            }
            $this->stored_activity = $this->id_activity;
            $this->stored_member = $this->id_member;
            return true;
        } catch (\OverflowException $e) {
            if ($transaction) {
                $this->zdb->connection->rollBack();
            }
            $this->errors[] = _T('Subscription already exists for this member and activity', 'activities');
            return false;
        } catch (\Exception $e) {
            if ($transaction) {
                $this->zdb->connection->rollBack();
            }
            Analog::log(
                'Something went wrong :\'( | ' . $e->getMessage() . "\n"
                . $e->getTraceAsString(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Does another subscription exist for the same member and activity?
     */
    private function isDuplicate(): bool
    {
        $select = $this->zdb->select($this->getTableName());
        $select->where([
            Activity::PK => $this->id_activity,
            Adherent::PK => $this->id_member
        ]);
        if (!empty($this->id)) {
            $select->where->notEqualTo(self::PK, $this->id);
        }
        return $this->zdb->execute($select)->count() > 0;
    }

    /**
     * Add member to the activity group, if any and if not already in
     */
    private function joinActivityGroup(): void
    {
        $group = $this->getActivity()?->getGroup();
        if ($group === null) {
            return;
        }

        $select = $this->zdb->select(Group::GROUPSUSERS_TABLE);
        $select->where([
            Group::PK => $group->getId(),
            Adherent::PK => $this->id_member
        ]);
        //checked before inserting: on PostgreSQL, a duplicate entry aborts the whole transaction
        if ($this->zdb->execute($select)->count() === 0) {
            $group->addMember($this->getMember());
        }
    }

    /**
     * Get activity id
     */
    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    /**
     * Get activity id
     */
    public function getActivityId(): ?int
    {
        return $this->id_activity ?? null;
    }

    /**
     * Get activity
     */
    public function getActivity(): ?Activity
    {
        if (isset($this->id_activity)) {
            $this->activity = new Activity($this->zdb, $this->id_activity);
        }
        return $this->activity;
    }

    /**
     * Get amount from activity
     */
    public function getAmountFromActivity(): ?float
    {
        $activity = $this->getActivity();
        if ($activity !== null) {
            return $this->getActivity()->getPrice();
        }
        return null;
    }

    /**
     * Get member id
     */
    public function getMemberId(): ?int
    {
        return $this->id_member ?? null;
    }

    /**
     * Get member
     */
    public function getMember(): ?Adherent
    {
        if (isset($this->id_member)) {
            $this->member = new Adherent($this->zdb, $this->id_member);
        }
        return $this->member;
    }

    /**
     * Is subscription paid?
     */
    public function isPaid(): bool
    {
        return $this->paid;
    }

    /**
     * Get amount
     */
    public function getAmount(): ?float
    {
        return $this->payment_amount;
    }

    /**
     * Get payment method
     */
    public function getPaymentMethod(): int
    {
        return $this->payment_method;
    }

    /**
     * Get payment method name
     */
    public function getPaymentMethodName(): string
    {
        $pt = new PaymentType($this->zdb, $this->payment_method);
        return $pt->getname();
    }

    /**
     * Get creation date
     *
     * @param bool $formatted Return date formatted, raw if false
     */
    public function getCreationDate(bool $formatted = true): string
    {
        return $this->getDate('creation_date', $formatted) ?? '';
    }

    /**
     * Get subscription date
     *
     * @param bool $formatted Return date formatted, raw if false
     */
    public function getSubscriptionDate(bool $formatted = true): string
    {
        return $this->getDate('subscription_date', $formatted) ?? '';
    }

    /**
     * Get end date
     *
     * @param bool $formatted Return date formatted, raw if false
     */
    public function getEndDate(bool $formatted = true): string
    {
        return $this->getDate('end_date', $formatted) ?? '';
    }

    /**
     * Set activity
     *
     * @param int $activity Activity id
     */
    public function setActivity(int $activity): self
    {
        $this->id_activity = $activity;
        $this->activity = new Activity($this->zdb, $this->id_activity);
        return $this;
    }

    /**
     * Set member
     *
     * @param int $member Member id
     */
    public function setMember(int $member): self
    {
        $this->id_member = $member;
        $this->member = new Adherent($this->zdb, $this->id_member, false);
        return $this;
    }

    /**
     * Get table's name
     */
    protected function getTableName(): string
    {
        return ACTIVITIES_PREFIX . self::TABLE;
    }

    /**
     * Get comment
     */
    public function getComment(): string
    {
        return $this->comment;
    }

    /**
     * Get row class related to current subscription status
     *
     * @param bool $public we want the class for public pages
     *
     * @return string the class to apply
     */
    public function getRowClass(bool $public = false): string
    {
        $strclass = 'subscription-'
            . ($this->isPaid() ? 'paid' : 'notpaid');
        return $strclass;
    }

    /**
     * Set fields, must populate $this->fields
     */
    protected function setFields(): self
    {
        $this->fields = [
            self::PK => [
                'label'    => 'Subscription id', //not a field in the form
                'propname' => 'id'
            ],
            Activity::PK => [
                'label'    => _T('Activity', 'activities'),
                'propname' => 'id_activity'
            ],
            Adherent::PK => [
                'label'    => _T('Member', 'activities'),
                'propname' => 'id_member'
            ],
            'is_paid' => [
                'label'    => _T('Is paid', 'activities'),
                'propname' => 'is_paid'
            ],
            'payment_amount' => [
                'label'    => _T('Amount', 'activities'),
                'propname' => 'payment_amount'
            ],
            'payment_method' => [
                'label'    => _T('Payment method', 'activities'),
                'propname' => 'payment_method'
            ],
            'creation_date' => [
                'label'    => _T('Creation date', 'activities'),
                'propname' => 'creation_date'
            ],
            'subscription_date' => [
                'label'    => _T('Subscription date', 'activities'),
                'propname' => 'subscription_date'
            ],
            'end_date'      => [
                'label'    => _T("End date"),
                'propname' => 'end_date'
            ],
            'comment' => [
                'label'    => _T('Comment', 'activities'),
                'propname' => 'comment'
            ]
        ];

        return $this;
    }

    /**
     * Get errors
     *
     * @return array<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
