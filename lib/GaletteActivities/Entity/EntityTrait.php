<?php

/**
 * This file is part of Galette Activities plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2024-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteActivities\Entity;

use ArrayObject;
use GaletteActivities\NotFoundException;

/**
 * Loading, storage and removal shared by activities and subscriptions
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
trait EntityTrait
{
    /**
     * Populate object from a resultset row
     *
     * @param ArrayObject<string, mixed> $r the resultset row
     */
    abstract private function loadFromRS(ArrayObject $r): void;

    /**
     * Load entity from its id
     *
     * @param int $id Identifier
     *
     * @throws NotFoundException
     */
    public function load(int $id): void
    {
        $select = $this->zdb->select($this->getTableName());
        $select->where([self::PK => $id]);
        $results = $this->zdb->execute($select);

        if ($results->count() === 0) {
            throw new NotFoundException(sprintf('%1$s #%2$s does not exist', self::class, $id));
        }
        $this->loadFromRS($results->current());
    }

    /**
     * Remove entity; database removes its links
     */
    public function remove(): void
    {
        $delete = $this->zdb->delete($this->getTableName());
        $delete->where([self::PK => $this->id]);
        $this->zdb->execute($delete);
    }

    /**
     * Run storage in a transaction, unless one is already running
     *
     * @param callable $store Storage
     */
    private function transactional(callable $store): void
    {
        $new = $this->id === null;
        $transaction = !$this->zdb->connection->inTransaction();
        if ($transaction) {
            $this->zdb->connection->beginTransaction();
        }

        try {
            $store();
            if ($transaction) {
                $this->zdb->connection->commit();
            }
        } catch (\Throwable $e) {
            if ($transaction) {
                $this->zdb->connection->rollBack();
            }
            if ($new) {
                //nothing has been stored
                $this->id = null;
            }
            throw $e;
        }
    }

    /**
     * Get identifier of the row that has just been inserted
     */
    private function getLastInsertId(): int
    {
        if ($this->zdb->isPostgres()) {
            /** @phpstan-ignore-next-line */
            return (int)$this->zdb->driver->getLastGeneratedValue(
                PREFIX_DB . $this->getTableName() . '_id_seq'
            );
        }
        return (int)$this->zdb->driver->getLastGeneratedValue();
    }

    /**
     * Get table's name
     */
    protected function getTableName(): string
    {
        return ACTIVITIES_PREFIX . self::TABLE;
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
