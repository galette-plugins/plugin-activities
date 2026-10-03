<?php

/**
 * This file is part of Galette Activities plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2024-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteActivities\Controllers\Crud;

use Analog\Analog;
use DI\Attribute\Inject;
use Galette\Controllers\Crud\AbstractPluginController;
use Galette\Core\Pagination;
use GaletteActivities\Entity\Activity;
use GaletteActivities\Entity\Subscription;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

/**
 * Common code for activities and subscriptions: filters, forms and storage
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @template TFilters of Pagination
 */
abstract class AbstractController extends AbstractPluginController
{
    /**
     * @var array<string, mixed>
     */
    #[Inject("Plugin Galette Activities")]
    protected array $module_info;

    /**
     * Get default filter name, session key of list filters
     */
    abstract public static function getDefaultFilterName(): string;

    /**
     * Entity name, for session keys and logs: activity or subscription
     */
    abstract protected function getEntityName(): string;

    /**
     * Create empty list filters
     *
     * @return TFilters
     */
    abstract protected function createFilters(): Pagination;

    /**
     * Get the message for an entity that does not exist
     *
     * @param int $id Requested identifier
     */
    abstract protected function getNotFoundMessage(int $id): string;

    /**
     * Apply posted filters specific to the list
     *
     * @param TFilters            $filters Filters
     * @param array<string,mixed> $post    Posted values
     */
    protected function applyPostedFilters(Pagination $filters, array $post): void
    {
    }

    /**
     * Get list filters from session, with page or order of the list route
     *
     * @param string|null     $option One of 'page' or 'order'
     * @param string|int|null $value  Value of the option
     *
     * @return TFilters
     */
    protected function getFilters(?string $option = null, string|int|null $value = null): Pagination
    {
        $filters = $this->session->{$this->getFilterName($this->getDefaultFilterName())} ?? $this->createFilters();

        switch ($option) {
            case 'page':
                $filters->current_page = (int)$value;
                break;
            case 'order':
                $filters->orderby = $value;
                break;
        }

        return $filters;
    }

    /**
     * Store list filters in session
     *
     * @param Pagination $filters Filters
     */
    protected function storeFilters(Pagination $filters): void
    {
        $this->session->{$this->getFilterName($this->getDefaultFilterName())} = $filters;
    }

    /**
     * Filtering
     */
    public function filter(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();
        $filters = $this->getFilters();

        if (isset($post['clear_filter'])) {
            $filters->reinit();
        } else {
            //number of rows to show
            if (isset($post['nbshow'])) {
                $filters->show = $post['nbshow'];
            }
            $this->applyPostedFilters($filters, $post);
        }

        $this->storeFilters($filters);
        return $this->redirect($response, $this->redirectUri([]));
    }

    /**
     * Keep posted values, to fill the form again after a redirection
     *
     * @param ?int                $id   Entity identifier, null for a new one
     * @param array<string,mixed> $post Posted values
     */
    protected function keepPostedValues(?int $id, array $post): void
    {
        $this->session->{$this->getPostedValuesKey()} = [
            'id'        => $id,
            'values'    => $post
        ];
    }

    /**
     * Get values posted on the form of an entity, once
     *
     * @param ?int $id Entity identifier, null for a new one
     *
     * @return ?array<string,mixed>
     */
    protected function getPostedValues(?int $id): ?array
    {
        $key = $this->getPostedValuesKey();
        $data = $this->session->$key ?? null;
        unset($this->session->$key);
        return is_array($data) && $data['id'] === $id ? $data['values'] : null;
    }

    /**
     * Session key of posted values
     */
    private function getPostedValuesKey(): string
    {
        return 'plugin_activities_' . $this->getEntityName();
    }

    /**
     * Store an entity, and report how it went
     *
     * @param Activity|Subscription $entity    Entity
     * @param string                $added     Message for a new entity
     * @param string                $modified  Message for an existing entity
     * @param string                $failed    Message when storage failed
     * @param array<string>         $successes Success messages
     * @param array<string>         $errors    Error messages
     */
    protected function storeEntity(
        Activity|Subscription $entity,
        string $added,
        string $modified,
        string $failed,
        array &$successes,
        array &$errors
    ): void {
        $new = $entity->getId() === null;
        try {
            $entity->store();
            $successes[] = $new ? $added : $modified;
        } catch (\Throwable $e) {
            Analog::log(
                sprintf(
                    'Unable to store %1$s #%2$s | %3$s',
                    $this->getEntityName(),
                    $entity->getId() ?? 'new',
                    $e->getMessage()
                ),
                Analog::ERROR
            );
            $errors[] = $failed;
        }
    }

    /**
     * Redirect when requested entity does not exist
     *
     * @param int $id Requested identifier
     */
    protected function redirectNotFound(Response $response, int $id): Response
    {
        return $this->redirectWithErrors(
            response: $response,
            errors: [$this->getNotFoundMessage($id)],
            redirect_url: $this->redirectUri([])
        )->withStatus(302);
    }

    /**
     * Report messages, and redirect after a POST: the browser follows with a GET
     *
     * @param Response $response     PSR Response
     * @param string   $redirect_url URL to redirect to
     * @param string[] $successes    Successes to report
     * @param string[] $warnings     Warnings to report
     * @param string[] $errors       Errors to report
     */
    protected function redirect(
        Response $response,
        string $redirect_url,
        array $successes = [],
        array $warnings = [],
        array $errors = []
    ): Response {
        return parent::redirect($response, $redirect_url, $successes, $warnings, $errors)
            ->withStatus(303);
    }
}
