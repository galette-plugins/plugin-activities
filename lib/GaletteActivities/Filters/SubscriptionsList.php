<?php

/**
 * This file is part of Galette Activities plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2024-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteActivities\Filters;

use Analog\Analog;
use Galette\Core\Pagination;
use Galette\Enums\SQLOrder;
use Galette\Helpers\DatesHelper;
use GaletteActivities\Repository\Subscriptions;

/**
 * Subscription lists filters and paginator
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @property ?string    $start_date_filter
 * @property ?string    $end_date_filter
 * @property ?string    $rstart_date_filter
 * @property ?string    $rend_date_filter
 * @property ?int       $activity_filter
 * @property ?int       $member_filter
 * @property int        $paid_filter
 * @property int        $payment_type_filter
 * @property int        $date_field
 * @property array<int> $selected
 */
class SubscriptionsList extends Pagination
{
    use DatesHelper;

    public const int DATE_END = 0;
    public const int DATE_SUBSCRIPTION = 1;
    public const int DATE_CREATION = 2;
    //filters
    private ?int $activity_filter;
    private ?int $member_filter;

    private int $paid_filter;
    private int $payment_type_filter;
    private ?int $date_field = null;
    private ?string $start_date_filter; //@phpstan-ignore property.unusedType (assigned by DatesHelper::setFilterDate())
    private ?string $end_date_filter; //@phpstan-ignore property.unusedType (assigned by DatesHelper::setFilterDate())

    /** @var array<int> */
    private array $selected;

    /** @var array<string> */
    protected array $list_fields = [
        'activity_filter',
        'member_filter',
        'paid_filter',
        'payment_type_filter',
        'date_field',
        'start_date_filter',
        'end_date_filter',
        'selected'
    ];

    /** @var array<string>  */
    protected array $virtuals_list_fields = [
        'rstart_date_filter',
        'rend_date_filter'
    ];

    /**
     * Default constructor
     */
    public function __construct()
    {
        $this->reinit();
    }

    /**
     * Returns the field we want to default set order to
     *
     * @return int|string field name
     */
    protected function getDefaultOrder(): int|string
    {
        return Subscriptions::ORDERBY_ENDDATE;
    }

    /**
     * Return the default direction for ordering
     */
    protected function getDefaultDirection(): SQLOrder
    {
        return SQLOrder::DESC;
    }

    /**
     * Reinit default parameters
     */
    public function reinit(): void
    {
        parent::reinit();
        $this->activity_filter = null;
        $this->member_filter = null;
        $this->paid_filter = Subscriptions::FILTER_DC_PAID;
        $this->payment_type_filter = -1;
        $this->selected = [];
        $this->date_field = self::DATE_END;
        $this->start_date_filter = null;
        $this->end_date_filter = null;
    }

    /**
     * Global getter method
     *
     * @param string $name name of the property we want to retrieve
     *
     * @return mixed the called property
     */
    public function __get(string $name): mixed
    {
        if (in_array($name, $this->pagination_fields)) {
            return parent::__get($name);
        } else {
            if (in_array($name, $this->list_fields) || in_array($name, $this->virtuals_list_fields)) {
                switch ($name) {
                    case 'start_date_filter':
                    case 'end_date_filter':
                        return $this->getDate($name);
                    case 'rstart_date_filter':
                    case 'rend_date_filter':
                        //same as above, but raw format
                        $rname = substr($name, 1);
                        return $this->getDate($rname, true, false);
                    default:
                        return $this->$name;
                }
            }
        }

        throw new \RuntimeException(
            sprintf(
                'Unable to get property "%s::%s"!',
                __CLASS__,
                $name
            )
        );
    }

    /**
     * Global setter method
     *
     * @param string $name  name of the property we want to assign a value to
     * @param mixed  $value a relevant value for the property
     */
    public function __set(string $name, mixed $value): void
    {
        if (in_array($name, $this->pagination_fields)) {
            parent::__set($name, $value);
        } else {
            Analog::log(
                '[SubscriptionsList] Setting property `' . $name . '`',
                Analog::DEBUG
            );

            switch ($name) {
                case 'start_date_filter':
                case 'end_date_filter':
                    $this->setFilterDate($name, $value, $name === 'start_date_filter');
                    break;
                case 'selected':
                    if (is_array($value)) {
                        $this->$name = $value;
                    } elseif ($value !== null) {
                        Analog::log(
                            '[SubscriptionsList] Value for property `' . $name
                            . '` should be an array (' . gettype($value) . ' given)',
                            Analog::WARNING
                        );
                    }
                    break;
                case 'activity_filter':
                case 'member_filter':
                    //empty means no filter
                    $this->$name = ($value === null || $value === '') ? null : (int)$value;
                    break;
                case 'paid_filter':
                case 'payment_type_filter':
                case 'date_field':
                    $this->$name = (int)$value;
                    break;
                default:
                    throw new \RuntimeException(
                        sprintf(
                            'Unable to set property "%s::%s"!',
                            __CLASS__,
                            $name
                        )
                    );
            }
        }
    }
}
