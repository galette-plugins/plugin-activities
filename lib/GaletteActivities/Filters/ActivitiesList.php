<?php

/**
 * This file is part of Galette Activities plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2024-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteActivities\Filters;

use Galette\Core\Pagination;
use Galette\Enums\SQLOrder;
use GaletteActivities\Repository\Activities;

/**
 * Activities lists filters and paginator
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ActivitiesList extends Pagination
{
    /**
     * Returns the field we want to default set order to
     *
     * @return int|string field name
     */
    protected function getDefaultOrder(): int|string
    {
        return Activities::ORDERBY_DATE;
    }

    /**
     * Return the default direction for ordering
     */
    protected function getDefaultDirection(): SQLOrder
    {
        return SQLOrder::DESC;
    }
}
