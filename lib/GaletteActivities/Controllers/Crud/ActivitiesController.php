<?php

/**
 * This file is part of Galette Activities plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2024-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteActivities\Controllers\Crud;

use Galette\Core\Pagination;
use Galette\Repository\Groups;
use GaletteActivities\Filters\ActivitiesList;
use GaletteActivities\Entity\Activity;
use GaletteActivities\NotFoundException;
use GaletteActivities\Repository\Activities;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

/**
 * Activities controller
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @extends AbstractController<ActivitiesList>
 */

class ActivitiesController extends AbstractController
{
    /**
     * Entity name, for session keys and logs
     */
    protected function getEntityName(): string
    {
        return 'activity';
    }

    /**
     * Create empty list filters
     */
    protected function createFilters(): Pagination
    {
        return new ActivitiesList();
    }

    /**
     * Get the message for an activity that does not exist
     *
     * @param int $id Requested activity identifier
     */
    protected function getNotFoundMessage(int $id): string
    {
        return sprintf(
            //TRANS: %1$s is the activity ID
            _T('No activity #%1$s.', 'activities'),
            $id
        );
    }

    // CRUD - Create

    /**
     * Add page
     */
    public function add(Request $request, Response $response): Response
    {
        return $this->edit($request, $response, null, 'add');
    }

    /**
     * Add action
     */
    public function doAdd(Request $request, Response $response): Response
    {
        return $this->doEdit($request, $response, null, 'add');
    }

    // /CRUD - Create
    // CRUD - Read

    /**
     * List page
     *
     * @param string|null     $option One of 'page' or 'order'
     * @param string|int|null $value  Value of the option
     */
    public function list(Request $request, Response $response, ?string $option = null, string|int|null $value = null): Response
    {
        $filters = $this->getFilters($option, $value);
        $activities = new Activities($this->zdb, $this->login, $this->history, $this->preferences, $filters);
        $list = $activities->getList();

        //assign pagination variables to the template and add pagination links
        $filters->setViewPagination($this->routeparser, $this->view, false);
        $this->storeFilters($filters);

        // display page
        $this->view->render(
            $response,
            $this->getTemplate('activities'),
            [
                'page_title'            => _T("Activities management", "activities"),
                'require_dialog'        => true,
                'activities'            => $list,
                'nb_activities'         => $activities->getCount(),
                'filters'               => $filters
            ]
        );
        return $response;
    }

    // /CRUD - Read
    // CRUD - Update

    /**
     * Edit page
     *
     * @param int|null $id     Model id
     * @param string   $action Action
     */
    public function edit(Request $request, Response $response, ?int $id = null, string $action = 'edit'): Response
    {
        $activity = new Activity($this->zdb, $this->history);

        if ($id !== null) {
            try {
                $activity->load($id);
            } catch (NotFoundException) {
                return $this->redirectNotFound($response, $id);
            }
        }

        //values posted before an error
        $values = $this->getPostedValues($activity->getId());
        if ($values !== null) {
            $activity->check($values);
        }

        // template variable declaration
        $title = _T("Activity", "activities");
        if ($activity->getId() !== null) {
            $title .= ' (' . _T("modification") . ')';
        } else {
            $title .= ' (' . _T("creation") . ')';
        }

        //Groups
        $groups = new Groups($this->zdb, $this->login);
        $groups_list = $groups->getList();

        // display page
        $this->view->render(
            $response,
            $this->getTemplate('activity'),
            [
                'autocomplete'  => true,
                'page_title'    => $title,
                'activity'      => $activity,
                // pseudo random int
                'time'          => time(),
                'groups'        => $groups_list
            ]
        );
        return $response;
    }

    /**
     * Edit action
     *
     * @param null|int $id     Model id for edit
     * @param string   $action Either add or edit
     */
    public function doEdit(Request $request, Response $response, ?int $id = null, string $action = 'edit'): Response
    {
        $post = $request->getParsedBody();
        $activity = new Activity($this->zdb, $this->history);
        if (!empty($post['id'])) {
            try {
                $activity->load((int)$post['id']);
            } catch (NotFoundException) {
                return $this->redirectNotFound($response, (int)$post['id']);
            }
        }

        $successes = [];
        $errors = [];
        if ($activity->check($post)) {
            $this->storeEntity(
                $activity,
                _T("New activity has been successfully added.", "activities"),
                _T("Activity has been modified.", "activities"),
                _T("An error occurred while storing the activity.", "activities"),
                $successes,
                $errors
            );
        } else {
            $errors = $activity->getErrors();
        }

        if (count($errors) === 0) {
            $redirect_url = $this->routeparser->urlFor('activities_activities');
        } else {
            $this->keepPostedValues($activity->getId(), $post);
            $redirect_url = $activity->getId() !== null
                ? $this->routeparser->urlFor('activities_activity_edit', ['id' => (string)$activity->getId()])
                : $this->routeparser->urlFor('activities_activity_add');
        }

        return $this->redirect(
            response: $response,
            redirect_url: $redirect_url,
            successes: $successes,
            errors: $errors
        );
    }

    // /CRUD - Update
    // CRUD - Delete

    /**
     * Get redirection URI
     *
     * @param array<string,mixed> $args Route arguments
     */
    public function redirectUri(array $args): string
    {
        return $this->routeparser->urlFor('activities_activities');
    }

    /**
     * Get form URI
     *
     * @param array<string,mixed> $args Route arguments
     */
    public function formUri(array $args): string
    {
        return $this->routeparser->urlFor(
            'activities_do_remove_activity',
            $args
        );
    }

    /**
     * Get confirmation removal page title
     *
     * @param array<string,mixed> $args Route arguments
     */
    public function confirmRemoveTitle(array $args): string
    {
        try {
            $activity = new Activity($this->zdb, $this->history, (int)$args['id']);
        } catch (NotFoundException) {
            return $this->getNotFoundMessage((int)$args['id']);
        }
        return sprintf(
            //TRANS %1$s is activity name
            _T('Remove activity %1$s', 'activities'),
            $activity->getName()
        );
    }

    /**
     * Removal confirmation parameters: subscriptions are removed with the activity
     *
     * @return array<string,mixed>
     */
    protected function getconfirmDeleteParams(Request $request): array
    {
        $params = parent::getconfirmDeleteParams($request);

        try {
            $count = (new Activity($this->zdb, $this->history, (int)$params['data']['id']))->countSubscriptions();
        } catch (NotFoundException) {
            $count = 0;
        }
        if ($count > 0) {
            $params['message'] = sprintf(
                _Tn(
                    //TRANS: %1$s is the number of subscriptions
                    '%1$s subscription to this activity will be removed as well.',
                    '%1$s subscriptions to this activity will be removed as well.',
                    $count,
                    'activities'
                ),
                $count
            );
        }

        return $params;
    }

    /**
     * Remove object
     *
     * @param array<string,mixed> $args Route arguments
     * @param array<string,mixed> $post POST values
     */
    protected function doDelete(array $args, array $post): bool
    {
        $activity = new Activity($this->zdb, $this->history, (int)$args['id']);
        $activity->remove();
        return true;
    }

    // /CRUD - Delete
    // /CRUD

    /**
     * Get default filter name
     */
    public static function getDefaultFilterName(): string
    {
        return 'plugin_activities_activities';
    }
}
