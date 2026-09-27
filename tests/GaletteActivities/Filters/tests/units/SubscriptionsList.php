<?php

/**
 * This file is part of Galette Activities plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2024-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteActivities\Filters\tests\units;

use Galette\Tests\GaletteTestCase;

/**
 * Subscriptions list filters tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class SubscriptionsList extends GaletteTestCase
{
    protected int $seed = 20260927143012;

    /**
     * Unknown properties are refused
     */
    public function testUnknownPropertyIsRefused(): void
    {
        $filters = new \GaletteActivities\Filters\SubscriptionsList();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to set property "GaletteActivities\Filters\SubscriptionsList::query"!');
        $filters->query = 'SELECT 1'; //@phpstan-ignore property.notFound (removed property)
    }
}
