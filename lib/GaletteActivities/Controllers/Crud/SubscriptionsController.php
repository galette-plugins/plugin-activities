<?php

/**
 * This file is part of Galette Activities plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2024-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteActivities\Controllers\Crud;

use Galette\Entity\Adherent;
use Galette\Repository\Members;
use Galette\Controllers\Crud\AbstractPluginController;
use GaletteActivities\Filters\SubscriptionsList;
use GaletteActivities\Entity\Subscription;
use GaletteActivities\Entity\Activity;
use GaletteActivities\Repository\Subscriptions;
use GaletteActivities\Repository\Activities;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use DI\Attribute\Inject;

/**
 * Subscriptions controller
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class SubscriptionsController extends AbstractPluginController
{
    /**
     * @var array<string, mixed>
     */
    #[Inject("Plugin Galette Activities")]
    protected array $module_info;

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
        $filters = $this->session->{$this->getFilterName($this->getDefaultFilterName())} ?? new SubscriptionsList();

        if ($option !== null) {
            switch ($option) {
                case 'page':
                    $filters->current_page = (int)$value;
                    break;
                case 'order':
                    $filters->orderby = $value;
                    break;
                case 'clear_filter':
                    $filters->reinit();
                    break;
            }
        }

        $activity = null;
        if ($filters->activity_filter) {
            $activity = new Activity($this->zdb, (int)$filters->activity_filter);
        }

        $subscriptions = new Subscriptions($this->zdb, $filters);

        $activities = new Activities($this->zdb, $this->login, $this->preferences);
        $list = $subscriptions->getList();
        $count = $subscriptions->getCount();

        //assign pagination variables to the template and add pagination links
        $filters->setViewPagination($this->routeparser, $this->view, false);

        $this->session->{$this->getFilterName($this->getDefaultFilterName())} = $filters;

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
     * Filtering
     */
    public function filter(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();
        if (isset($this->session->{$this->getFilterName($this->getDefaultFilterName())})) {
            $filters = $this->session->{$this->getFilterName($this->getDefaultFilterName())};
        } else {
            $filters = new SubscriptionsList();
        }

        //reinitialize filters
        if (isset($post['clear_filter'])) {
            $filters->reinit();
        } else {
            //number of rows to show
            if (isset($post['nbshow'])) {
                $filters->show = $post['nbshow'];
            }

            if (isset($post['paid_filter'])) {
                if (is_numeric($post['paid_filter'])) {
                    $filters->paid_filter = $post['paid_filter'];
                }
            }

            if (isset($post['payment_type_filter'])) {
                if (is_numeric($post['payment_type_filter'])) {
                    $filters->payment_type_filter = $post['payment_type_filter'];
                }
            }

            if (isset($post['activity_filter'])) {
                if (is_numeric($post['activity_filter'])) {
                    $filters->activity_filter = $post['activity_filter'];
                }
            }

            if (isset($post['member_filter'])) {
                if (is_numeric($post['member_filter'])) {
                    $filters->member_filter = $post['member_filter'];
                }
            }

            if (isset($post['date_field'])) {
                if (is_numeric($post['date_field'])) {
                    $filters->date_field = $post['date_field'];
                }
            }

            if (isset($post['start_date_filter'])) {
                $filters->start_date_filter = $post['start_date_filter'];
            }

            if (isset($post['end_date_filter'])) {
                $filters->end_date_filter = $post['end_date_filter'];
            }
        }

        $this->session->{$this->getFilterName($this->getDefaultFilterName())} = $filters;

        return $response
            ->withStatus(303)
            ->withHeader(
                'Location',
                $this->routeparser->urlFor('activities_subscriptions')
            );
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

        if ($this->session->subscription !== null) {
            $subscription = $this->session->subscription;
            $this->session->subscription = null;
        } else {
            $subscription = new Subscription($this->zdb);
        }

        if ($id !== null && $subscription->getId() != $id) {
            if (!$subscription->load($id)) {
                $this->flash->addMessage(
                    'error_detected',
                    sprintf(
                        //TRANS: %1$s is the subscription ID
                        _T('No subscription #%1$s.', 'activities'),
                        $id
                    )
                );
                return $response
                    ->withStatus(302)
                    ->withHeader('Location', $this->routeparser->urlFor('activities_subscriptions'));
            }
        } elseif ($id_adh !== null) {
            $subscription->setMember($id_adh);
        }

        // template variable declaration
        $title = _T("Subscription", "activities");
        if ($subscription->getId() != '') {
            $title .= ' (' . _T("modification") . ')';
        } else {
            $title .= ' (' . _T("creation") . ')';
        }

        //Activities
        $activities = new Activities($this->zdb, $this->login, $this->preferences);

        // members
        $m = new Members();
        $members = $m->getDropdownMembers($this->zdb, $this->login);

        $route_params['members'] = [
            'filters'   => $m->getFilters(),
            'count'     => $m->getCount()
        ];
        $route_params['autocomplete'] = true;

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
                    'autocomplete'      => true,
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
        $subscription = new Subscription($this->zdb);
        if (isset($post['id']) && !empty($post['id'])) {
            $subscription->load((int)$post['id']);
        }

        if (isset($post['cancel'])) {
            $redirect_url = $this->routeparser->urlFor(
                'activities_subscriptions'
            );
            return $response
                ->withStatus(303)
                ->withHeader('Location', $redirect_url);
        }

        $success_detected = [];
        $warning_detected = [];
        $error_detected = [];
        $goto_list = true;

        // Validation
        $valid = $subscription->check($post);
        if ($valid !== true) {
            $error_detected = array_merge($error_detected, $subscription->getErrors());
        }

        if (count($error_detected) == 0 && isset($post['save'])) {
            //all goes well, we can proceed

            $new = false;
            if ($subscription->getId() == '') {
                $new = true;
            }
            try {
                $store = $subscription->store();
            } catch (\Throwable) {
                //already logged by the entity
                $store = false;
            }
            if ($store === true) {
                //member has been stored :)
                if ($new) {
                    $success_detected[] = _T("New subscription has been successfully added.", "activities");
                } else {
                    $success_detected[] = _T("Subscription has been modified.", "activities");
                }
            } else {
                //something went wrong :'(
                $errors = $subscription->getErrors();
                if (count($errors)) {
                    $error_detected = array_merge($error_detected, $errors);
                } else {
                    $error_detected[] = _T("An error occurred while storing the subscription.", "activities");
                }
            }
        }

        if (!isset($post['save'])) {
            $this->session->subscription = $subscription;
            $error_detected = [];
            $goto_list = false;
            $warning_detected[] = _T('Do not forget to store the subscription', 'activities');
        }

        if (count($error_detected) > 0) {
            foreach ($error_detected as $error) {
                $this->flash->addMessage(
                    'error_detected',
                    $error
                );
            }
        }

        if (count($warning_detected) > 0) {
            foreach ($warning_detected as $warning) {
                $this->flash->addMessage(
                    'warning_detected',
                    $warning
                );
            }
        }
        if (count($success_detected) > 0) {
            foreach ($success_detected as $success) {
                $this->flash->addMessage(
                    'success_detected',
                    $success
                );
            }
        }

        if (count($error_detected) == 0 && $goto_list) {
            //show subscriptions of the stored activity
            $filter_name = $this->getFilterName($this->getDefaultFilterName());
            $filters = $this->session->$filter_name ?? new SubscriptionsList();
            $filters->activity_filter = $subscription->getActivityId();
            $this->session->$filter_name = $filters;
            $redirect_url = $this->routeparser->urlFor('activities_subscriptions');
        } else {
            //store entity in session
            $this->session->subscription = $subscription;

            if ($subscription->getId()) {
                $route = 'activities_subscription_edit';
                $rparams = [
                    'id'        => $subscription->getId(),
                    'action'    => 'edit'
                ];
            } else {
                $route = 'activities_subscription_add';
                $rparams = ['action' => 'add'];
            }
            $redirect_url = $this->routeparser->urlFor(
                $route,
                $rparams
            );
        }

        return $response
            ->withStatus(303)
            ->withHeader('Location', $redirect_url);
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
        $subscription = new Subscription($this->zdb, (int)$args['id']);
        $member = $subscription->getMember();
        $activity = $subscription->getActivity();
        return sprintf(
            //TRANS: %1$s is the member name, %2$s the activity name.
            _T('Remove subscription for %1$s on %2$s', 'activities'),
            $member->sname,
            $activity->getName()
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
        $subscription = new Subscription($this->zdb, (int)$post['id']);
        return $subscription->remove();
    }

    // /CRUD - Delete
    // /CRUD

    /**
     * Get default filter name
     */
    public static function getDefaultFilterName(): string
    {
        return 'subscriptions';
    }
}
