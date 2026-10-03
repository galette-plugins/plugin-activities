--
-- This file is part of Galette Activities plugin (https://galette.eu).
-- SPDX-FileCopyrightText: Copyright © 2024-2026 The Galette Team
-- SPDX-License-Identifier: GPL-3.0-or-later
--

-- Align schema with MySQL one: subscriptions always have an activity, a member
-- and a payment status.
DELETE FROM galette_activities_subscriptions WHERE id_activity IS NULL OR id_adh IS NULL;
UPDATE galette_activities_subscriptions SET is_paid = FALSE WHERE is_paid IS NULL;

ALTER TABLE galette_activities_subscriptions
  ALTER COLUMN id_activity SET NOT NULL,
  ALTER COLUMN id_adh SET NOT NULL,
  ALTER COLUMN is_paid SET NOT NULL;
