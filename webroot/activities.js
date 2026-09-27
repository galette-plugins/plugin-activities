/**
 * This file is part of Galette Activities plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2024-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/* Subscription form: choosing another activity reloads the form with its values, without storing */
var _activitiesSubscriptionActivity = function() {
    $('#modifform #activity').on('change', function() {
        $(this).closest('form').trigger('submit');
    });
};

$(function() {
    _activitiesSubscriptionActivity();
});
