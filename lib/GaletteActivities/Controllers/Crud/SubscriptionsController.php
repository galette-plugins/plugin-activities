<?php

/**
 * This file is part of Galette Activities plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2024-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteActivities\Controllers\Crud;

use Galette\Core\Pagination;
use Galette\Entity\Adherent;
use Galette\Repository\Members;
use GaletteActivities\Filters\SubscriptionsList;
use GaletteActivities\Entity\Subscription;
use GaletteActivities\Entity\Activity;
use GaletteActivities\NotFoundException;
use GaletteActivities\Repository\Subscriptions;
use GaletteActivities\Repository\Activities;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

/**
 * Subscriptions controller
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @extends AbstractController<SubscriptionsList>
 */

class SubscriptionsController extends AbstractController
{
    /**
     * Entity name, for session keys and logs
     */
    protected function getEntityName(): string
    {
        return 'subscription';
    }

    /**
     * Create empty list filters
     */
    protected function createFilters(): Pagination
    {
        return new SubscriptionsList();
    }

    /**
     * Get the message for a subscription that does not exist
     *
     * @param int $id Requested subscription identifier
     */
    protected function getNotFoundMessage(int $id): string
    {
        return sprintf(
            //TRANS: %1$s is the subscription ID
            _T('No subscription #%1$s.', 'activities'),
            $id
        );
    }

    // CRUD - Create

    /**
     * Add page
     *
     * @param int|null $id_adh Member id
     */
    public function add(Request $request, Response $response, ?int $id_adh = null): Response
    {
        return $this->edit($request, $response, null, 'add', $id_adh);
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

        $activity = null;
        if ($filters->activity_filter !== null) {
            try {
                $activity = new Activity($this->zdb, $this->history, (int)$filters->activity_filter);
            } catch (NotFoundException) {
                $filters->activity_filter = null;
            }
        }

        $subscriptions = new Subscriptions($this->zdb, $this->login, $this->history, $this->preferences, $filters);

        $activities = new Activities($this->zdb, $this->login, $this->history, $this->preferences);
        $list = $subscriptions->getList();
        $count = $subscriptions->getCount();

        //assign pagination variables to the template and add pagination links
        $filters->setViewPagination($this->routeparser, $this->view, false);

        $this->storeFilters($filters);

        // members
        $m = new Members();
        $members = $m->getDropdownMembers(
            $this->zdb,
            $this->login,
            $filters->member_filter,
        );

        // display page
        $this->view->render(
            $response,
            $this->getTemplate('subscriptions'),
            [
                'page_title'        => _T("Subscriptions management", "activities"),
                'subscriptions'     => $subscriptions,
                'subscriptions_list' => $list,
                'nb_subscriptions'  => $count,
                'activity'          => $activity,
                'require_dialog'    => true,
                'filters'           => $filters,
                'activities'        => $activities->getList(),
                'members'           => [
                    'filters'   => $m->getFilters(),
                    'count'     => $m->getCount(),
                    'list'      => $members
                ],
            ]
        );
        return $response;
    }

    /**
     * Apply posted subscriptions filters
     *
     * @param SubscriptionsList   $filters Filters
     * @param array<string,mixed> $post    Posted values
     */
    protected function applyPostedFilters(Pagination $filters, array $post): void
    {
        foreach (['paid_filter', 'payment_type_filter', 'activity_filter', 'date_field'] as $name) {
            if (isset($post[$name]) && is_numeric($post[$name])) {
                $filters->$name = $post[$name];
            }
        }

        if (isset($post['member_filter'])) {
            if ($post['member_filter'] === '' || is_numeric($post['member_filter'])) {
                $filters->member_filter = $post['member_filter'];
            }
        }

        if (isset($post['start_date_filter'])) {
            $filters->start_date_filter = $post['start_date_filter'];
        }

        if (isset($post['end_date_filter'])) {
            $filters->end_date_filter = $post['end_date_filter'];
        }
    }

    // /CRUD - Read
    // CRUD - Update

    /**
     * Edit page
     *
     * @param int|null $id     Model id
     * @param string   $action Action
     * @param int|null $id_adh Member ID (for add)
     */
    public function edit(Request $request, Response $response, ?int $id = null, string $action = 'edit', ?int $id_adh = null): Response
    {
        $route_params = [];
        $subscription = new Subscription($this->zdb, $this->history);

        if ($id !== null) {
            try {
                $subscription->load($id);
            } catch (NotFoundException) {
                return $this->redirectNotFound($response, $id);
            }
        } elseif ($id_adh !== null) {
            $subscription->setMember($id_adh);
        }

        //values posted before an error, or to reload the form
        $values = $this->getPostedValues($subscription->getId());
        if ($values !== null) {
            $subscription->check($values);
        }

        // template variable declaration
        $title = _T("Subscription", "activities");
        if ($subscription->getId() !== null) {
            $title .= ' (' . _T("modification") . ')';
        } else {
            $title .= ' (' . _T("creation") . ')';
        }

        //Activities
        $activities = new Activities($this->zdb, $this->login, $this->history, $this->preferences);

        // members
        $m = new Members();
        $members = $m->getDropdownMembers($this->zdb, $this->login);

        $route_params['members'] = [
            'filters'   => $m->getFilters(),
            'count'     => $m->getCount()
        ];

        //check if current attached member is part of the list
        if (
            $subscription->getMemberId() > 0
            && !isset($members[$subscription->getMemberId()])
        ) {
            $members[$subscription->getMemberId()] = Adherent::getSName($this->zdb, $subscription->getMemberId(), true);
        }

        if (count($members)) {
            $route_params['members']['list'] = $members;
        }

        // display page
        $this->view->render(
            $response,
            $this->getTemplate('subscription'),
            array_merge(
                $route_params,
                [
                    'page_title'        => $title,
                    'subscription'      => $subscription,
                    'activities'        => $activities->getList(),
                    'require_dialog'    => true,
                    'require_calendar'  => true,
                    // pseudo random int
                    'time'              => time()
                ]
            )
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
        $subscription = new Subscription($this->zdb, $this->history);
        if (!empty($post['id'])) {
            try {
                $subscription->load((int)$post['id']);
            } catch (NotFoundException) {
                return $this->redirectNotFound($response, (int)$post['id']);
            }
        }

        if (isset($post['cancel'])) {
            return $this->redirect($response, $this->routeparser->urlFor('activities_subscriptions'));
        }

        //form posted to be reloaded, with values of the chosen activity
        if (!isset($post['save'])) {
            $this->keepPostedValues($subscription->getId(), $post);
            return $this->redirect(
                response: $response,
                redirect_url: $this->getFormUrl($subscription),
                warnings: [_T('Do not forget to store the subscription', 'activities')]
            );
        }

        $successes = [];
        $errors = [];
        if ($subscription->check($post)) {
            $this->storeEntity(
                $subscription,
                _T("New subscription has been successfully added.", "activities"),
                _T("Subscription has been modified.", "activities"),
                _T("An error occurred while storing the subscription.", "activities"),
                $successes,
                $errors
            );
        } else {
            $errors = $subscription->getErrors();
        }

        if (count($errors) === 0) {
            //show subscriptions of the stored activity
            $filters = $this->getFilters();
            $filters->activity_filter = $subscription->getActivityId();
            $this->storeFilters($filters);
            $redirect_url = $this->routeparser->urlFor('activities_subscriptions');
        } else {
            $this->keepPostedValues($subscription->getId(), $post);
            $redirect_url = $this->getFormUrl($subscription);
        }

        return $this->redirect(
            response: $response,
            redirect_url: $redirect_url,
            successes: $successes,
            errors: $errors
        );
    }

    /**
     * Get URL of the form of a subscription
     *
     * @param Subscription $subscription Subscription
     */
    private function getFormUrl(Subscription $subscription): string
    {
        if ($subscription->getId() !== null) {
            return $this->routeparser->urlFor(
                'activities_subscription_edit',
                ['id' => (string)$subscription->getId()]
            );
        }
        return $this->routeparser->urlFor('activities_subscription_add');
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
        return $this->routeparser->urlFor('activities_subscriptions', $args);
    }

    /**
     * Get form URI
     *
     * @param array<string,mixed> $args Route arguments
     */
    public function formUri(array $args): string
    {
        return $this->routeparser->urlFor(
            'activities_do_remove_subscription',
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
            $subscription = new Subscription($this->zdb, $this->history, (int)$args['id']);
        } catch (NotFoundException) {
            return $this->getNotFoundMessage((int)$args['id']);
        }
        return sprintf(
            //TRANS: %1$s is the member name, %2$s the activity name.
            _T('Remove subscription for %1$s on %2$s', 'activities'),
            $subscription->getMember()?->sname,
            $subscription->getActivity()?->getName()
        );
    }

    /**
     * Remove object
     *
     * @param array<string,mixed> $args Route arguments
     * @param array<string,mixed> $post POST values
     */
    protected function doDelete(array $args, array $post): bool
    {
        $subscription = new Subscription($this->zdb, $this->history, (int)$args['id']);
        $subscription->remove();
        return true;
    }

    // /CRUD - Delete
    // /CRUD

    /**
     * Get default filter name
     */
    public static function getDefaultFilterName(): string
    {
        return 'plugin_activities_subscriptions';
    }
}
