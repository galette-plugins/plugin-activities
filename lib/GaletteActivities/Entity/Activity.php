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

    public const string TABLE = 'activities';
    public const string PK = 'id_activity';

    private Db $zdb;
    /** @var array<string> */
    private array $errors = [];

    private int $id;
    private string $name;
    private string $type;
    private ?float $price = null;
    private ?int $id_group = null;
    private ?Group $group = null;
    private ?string $creation_date = null;
    private ?string $comment;

    /**
     * Default constructor
     *
     * @param Db                                  $zdb  Database instance
     * @param null|int|ArrayObject<string, mixed> $args Either a ResultSet row or its id for to load
     *                                                  a specific activity, or null to just
     *                                                  instanciate object
     */
    public function __construct(Db $zdb, int|ArrayObject|null $args = null)
    {
        $this->zdb = $zdb;
        $this->setFields();

        if (is_int($args) && $args > 0) {
            $this->load($args);
        } elseif (is_object($args)) {
            $this->loadFromRS($args);
        }
    }

    /**
     * Loads an activity from its id
     *
     * @param int $id the identifiant for the activity to load
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
                'Cannot load activity #`' . $id . '` | ' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
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
        if ($r['price'] !== null) {
            $this->price = (float)$r['price'];
        }
        if ($r['id_group'] !== null) {
            $this->id_group = (int)$r['id_group'];
            $this->group = new Group($this->id_group);
        }
        $this->creation_date = $r['creation_date'];
        $this->comment = $r['comment'];
    }

    /**
     * Remove specified activity
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
                'Unable to delete activity ' . $this->name
                . ' (' . $this->id . ') |' . $e->getMessage(),
                Analog::ERROR
            );
            return false;
        }
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
            $this->group = new Group($this->id_group);
        } else {
            $this->id_group = null;
            $this->group = null;
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
    public function store(): bool
    {
        global $hist;

        try {
            $values = [
                'name'                  => $this->name,
                'type'                  => $this->type,
                'price'                 => $this->price ?? new Expression('NULL'),
                'id_group'              => $this->id_group ?? new Expression('NULL'),
                'comment'               => $this->comment ?? new Expression('NULL')
            ];

            if (empty($this->id)) {
                //we're inserting a new activity
                $this->creation_date = date("Y-m-d");
                $values['creation_date'] = $this->creation_date;

                $insert = $this->zdb->insert($this->getTableName());
                $insert->values($values);
                $add = $this->zdb->execute($insert);
                if ($add->count() > 0) {
                    if ($this->zdb->isPostgres()) {
                        /** @phpstan-ignore-next-line */
                        $this->id = (int)$this->zdb->driver->getLastGeneratedValue(
                            PREFIX_DB . $this->getTableName() . '_id_seq'
                        );
                    } else {
                        $this->id = (int)$this->zdb->driver->getLastGeneratedValue();
                    }

                    // logging
                    $hist->add(
                        _T("Activity added", "activities"),
                        $this->name
                    );
                    return true;
                } else {
                    $hist->add(_T("Fail to add new activity.", "activities"));
                    throw new \Exception(
                        'An error occurred inserting new activity!'
                    );
                }
            } else {
                //we're editing an existing activity
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
                        _T("Activity updated", "activities"),
                        $this->name
                    );
                }
                return true;
            }
        } catch (\Exception $e) {
            Analog::log(
                'Something went wrong :\'( | ' . $e->getMessage() . "\n"
                . $e->getTraceAsString(),
                Analog::ERROR
            );
            throw $e;
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
     * Get activity name
     */
    public function getName(): string
    {
        return $this->name ?? '';
    }

    /**
     * Get activity type
     */
    public function getType(): string
    {
        return $this->type ?? '';
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
     * Get price
     */
    public function getPrice(): ?float
    {
        return $this->price;
    }

    /**
     * Get Group
     */
    public function getGroup(): ?Group
    {
        return $this->group;
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
        return $this->comment ?? '';
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
