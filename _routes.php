<?php

/**
 * This file is part of Galette Activities plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2024-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

use GaletteActivities\Controllers\Crud\ActivitiesController;
use GaletteActivities\Controllers\Crud\SubscriptionsController;
use Galette\Middleware\Authenticate;

//Constants and classes from plugin
require_once $module['root'] . '/_config.inc.php';

$app->get(
    '/activities[/{option:page|order}/{value:\d+}]',
    [ActivitiesController::class, 'list']
)->setName('activities_activities')->add(Authenticate::class);

$app->get(
    '/activity/add',
    [ActivitiesController::class, 'add']
)->setName(
    'activities_activity_add'
)->add(Authenticate::class);

$app->get(
    '/activity/edit/{id:\d+}',
    [ActivitiesController::class, 'edit']
)->setName(
    'activities_activity_edit'
)->add(Authenticate::class);

$app->post(
    '/activity/add',
    [ActivitiesController::class, 'doAdd']
)->setName('activities_storeactivity_add')->add(Authenticate::class);

$app->post(
    '/activity/edit/{id:\d+}',
    [ActivitiesController::class, 'doEdit']
)->setName('activities_storeactivity_edit')->add(Authenticate::class);

$app->get(
    '/activity/remove/{id:\d+}',
    [ActivitiesController::class, 'confirmDelete']
)->setName('activities_remove_activity')->add(Authenticate::class);

$app->post(
    '/activity/remove[/{id:\d+}]',
    [ActivitiesController::class, 'delete']
)->setName('activities_do_remove_activity')->add(Authenticate::class);

//Subscriptions
$app->get(
    '/subscriptions[/{option:page|order}/{value:\d+}]',
    [SubscriptionsController::class, 'list']
)->setName('activities_subscriptions')->add(Authenticate::class);

$app->post(
    '/subscriptions/filter',
    [SubscriptionsController::class, 'filter']
)->setName('activities_filter-subscriptionslist')->add(Authenticate::class);

$app->get(
    '/subscription/add[/{id_adh:\d+}]',
    [SubscriptionsController::class, 'add']
)->setName(
    'activities_subscription_add'
)->add(Authenticate::class);

$app->get(
    '/subscription/edit/{id:\d+}',
    [SubscriptionsController::class, 'edit']
)->setName(
    'activities_subscription_edit'
)->add(Authenticate::class);

$app->post(
    '/subscription/add',
    [SubscriptionsController::class, 'doAdd']
)->setName('activities_storesubscription_add')->add(Authenticate::class);

$app->post(
    '/subscription/store',
    [SubscriptionsController::class, 'doEdit']
)->setName('activities_storesubscription_edit')->add(Authenticate::class);

$app->get(
    '/subscription/remove/{id:\d+}',
    [SubscriptionsController::class, 'confirmDelete']
)->setName('activities_remove_subscription')->add(Authenticate::class);

$app->post(
    '/subscription/remove[/{id:\d+}]',
    [SubscriptionsController::class, 'delete']
)->setName('activities_do_remove_subscription')->add(Authenticate::class);
