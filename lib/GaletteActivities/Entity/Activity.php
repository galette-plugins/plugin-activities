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
use Galette\Core\History;
use Analog\Analog;
use Galette\Entity\Group;
use Galette\Helpers\EntityHelper;
use Laminas\Db\Sql\Expression;

/**
 * Activity entity
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Activity
{
    use EntityHelper;
    use EntityTrait;

    public const string TABLE = 'activities';
    public const string PK = 'id_activity';

    private Db $zdb;
    private History $history;
    /** @var array<string> */
    private array $errors = [];

    private ?int $id = null;
    private string $name = '';
    private string $type = '';
    private ?float $price = null;
    private ?int $id_group = null;
    private ?Group $group = null;
    private ?string $creation_date = null;
    private ?string $comment = null;

    /**
     * Default constructor
     *
     * @param Db                                  $zdb     Database instance
     * @param History                             $history History instance
     * @param null|int|ArrayObject<string, mixed> $args    Either a ResultSet row or its id for to load
     *                                                     a specific activity, or null to just
     *                                                     instanciate object
     */
    public function __construct(Db $zdb, History $history, int|ArrayObject|null $args = null)
    {
        $this->zdb = $zdb;
        $this->history = $history;
        $this->setFields();

        if (is_int($args)) {
            $this->load($args);
        } elseif ($args !== null) {
            $this->loadFromRS($args);
        }
    }

    /**
     * Populate object from a resultset row
     *
     * @param ArrayObject<string, mixed> $r the resultset row
     */
    private function loadFromRS(ArrayObject $r): void
    {
        $this->id = (int)$r['id_activity'];
        $this->name = $r['name'];
        $this->type = $r['type'] ?? '';
        $this->price = $r['price'] === null ? null : (float)$r['price'];
        $this->id_group = $r['id_group'] === null ? null : (int)$r['id_group'];
        $this->creation_date = $r['creation_date'];
        $this->comment = $r['comment'];
    }

    /**
     * Check posted values validity
     *
     * @param array<string, mixed> $values All values to check, basically the $_POST array
     *                                     after sending the form
     */
    public function check(array $values): bool
    {
        $this->errors = [];

        if (empty($values['name'])) {
            $this->errors[] = _T('Name is mandatory', 'activities');
        } elseif (mb_strlen($values['name']) > 150) {
            $this->errors[] = _T('Name is too long', 'activities');
        } else {
            $this->name = $values['name'];
        }

        if (isset($values['type']) && !empty($values['type'])) {
            if (mb_strlen($values['type']) > 3) {
                $this->errors[] = _T('Type is too long', 'activities');
            } else {
                $this->type = $values['type'];
            }
        } else {
            $this->type = '';
        }

        //accept comma as decimal separator
        $price = strtr(trim((string)($values['price'] ?? '')), ',', '.');
        if ($price === '') {
            $this->price = null;
        } elseif (is_numeric($price)) {
            $this->price = (float)$price;
        } else {
            $this->errors[] = _T('Price must be a number.', 'activities');
        }

        if (isset($values['id_group']) && !empty($values['id_group'])) {
            $this->id_group = (int)$values['id_group'];
        } else {
            $this->id_group = null;
        }

        if (isset($values['comment']) && !empty($values['comment'])) {
            $this->comment = $values['comment'];
        } else {
            $this->comment = null;
        }

        if (count($this->errors) > 0) {
            Analog::log(
                'Error(s) checking activity before store:' . "\n"
                . print_r($this->errors, true),
                Analog::ERROR
            );
            return false;
        }

        return true;
    }

    /**
     * Store the activity
     */
    public function store(): void
    {
        $this->transactional(function (): void {
            $values = [
                'name'                  => $this->name,
                'type'                  => $this->type,
                'price'                 => $this->price ?? new Expression('NULL'),
                'id_group'              => $this->id_group ?? new Expression('NULL'),
                'comment'               => $this->comment ?? new Expression('NULL')
            ];

            if ($this->id === null) {
                //we're inserting a new activity
                $this->creation_date = date("Y-m-d");
                $values['creation_date'] = $this->creation_date;

                $insert = $this->zdb->insert($this->getTableName());
                $insert->values($values);
                $add = $this->zdb->execute($insert);
                if ($add->count() === 0) {
                    $this->history->add(_T("Fail to add new activity.", "activities"));
                    throw new \RuntimeException(
                        'An error occurred inserting new activity!'
                    );
                }
                $this->id = $this->getLastInsertId();

                // logging
                $this->history->add(
                    _T("Activity added", "activities"),
                    $this->name
                );
            } else {
                //we're editing an existing activity
                $update = $this->zdb->update($this->getTableName());
                $update
                    ->set($values)
                    ->where([self::PK => $this->id]);

                $edit = $this->zdb->execute($update);

                //edit == 0 does not mean there were an error, but that there
                //were nothing to change
                if ($edit->count() > 0) {
                    $this->history->add(
                        _T("Activity updated", "activities"),
                        $this->name
                    );
                }
            }
        });
    }

    /**
     * Count subscriptions to this activity
     */
    public function countSubscriptions(): int
    {
        if ($this->id === null) {
            return 0;
        }

        $select = $this->zdb->select(ACTIVITIES_PREFIX . Subscription::TABLE);
        $select->columns(['counter' => new Expression('COUNT(' . Subscription::PK . ')')])
            ->where([self::PK => $this->id]);
        return (int)$this->zdb->execute($select)->current()['counter'];
    }

    /**
     * Get activity id
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Get activity name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get activity type
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get creation date, as Y-m-d
     */
    public function getCreationDate(): string
    {
        return $this->creation_date ?? '';
    }

    /**
     * Get price
     */
    public function getPrice(): ?float
    {
        return $this->price;
    }

    /**
     * Get group id
     */
    public function getGroupId(): ?int
    {
        return $this->id_group;
    }

    /**
     * Get group, loaded once
     */
    public function getGroup(): ?Group
    {
        if ($this->id_group === null) {
            return null;
        }
        if ($this->group?->getId() !== $this->id_group) {
            $this->group = new Group($this->id_group);
        }
        return $this->group;
    }

    /**
     * Get comment
     */
    public function getComment(): string
    {
        return $this->comment ?? '';
    }

    /**
     * Set fields, must populate $this->fields
     */
    protected function setFields(): self
    {
        $this->fields = [
            self::PK => [
                'label'    => 'Activity id', //not a field in the form
                'propname' => 'id'
            ],
            'name' => [
                'label'    => _T('Name', 'activities'),
                'propname' => 'name'
            ],
            'type' => [
                'label'    => _T('Type', 'activities'),
                'propname' => 'type'
            ],
            'price' => [
                'label'    => _T('Price', 'activities'),
                'propname' => 'price'
            ],
            'id_group' => [
                'label'    => _T('Group', 'activities'),
                'propname' => 'id_group'
            ],
            'creation_date' => [
                'label'    => _T('Creation date', 'activities'),
                'propname' => 'creation_date'
            ],
            'comment' => [
                'label'    => _T('Comment', 'activities'),
                'propname' => 'comment'
            ]
        ];

        return $this;
    }
}
