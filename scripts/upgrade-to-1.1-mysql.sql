--
-- This file is part of Galette Activities plugin (https://galette.eu).
-- SPDX-FileCopyrightText: Copyright © 2024-2026 The Galette Team
-- SPDX-License-Identifier: GPL-3.0-or-later
--

-- Align schema with PostgreSQL one: same foreign keys names and actions,
-- no default amount. Foreign keys names depend on the MySQL version that
-- created them, and MySQL cannot drop them conditionally: tables are rebuilt.
-- Foreign keys are added with checks enabled: without them, MariaDB records
-- ON DELETE RESTRICT as NO ACTION.
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE galette_activities_activities_new (
  id_activity int(10) NOT NULL auto_increment,
  name varchar(150) NOT NULL,
  type varchar(3) NOT NULL default '',
  price decimal(15, 2) default NULL,
  id_group int unsigned default NULL,
  creation_date date NOT NULL,
  comment text,
  PRIMARY KEY (id_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

INSERT INTO galette_activities_activities_new (id_activity, name, type, price, id_group, creation_date, comment)
SELECT id_activity, name, type, price, id_group, creation_date, comment
FROM galette_activities_activities;

CREATE TABLE galette_activities_subscriptions_new (
  id_subscription int(10) NOT NULL auto_increment,
  id_activity int(10) NOT NULL,
  id_adh int(10) unsigned NOT NULL,
  is_paid tinyint(1) NOT NULL default 0,
  payment_amount decimal(15, 2) default NULL,
  payment_method tinyint(3) unsigned NOT NULL default '0',
  creation_date date NOT NULL,
  subscription_date date NOT NULL,
  end_date date NOT NULL,
  comment text,
  PRIMARY KEY (id_subscription),
  CONSTRAINT galette_activities_subscriptions_id_activity_id_adh_key UNIQUE (id_activity, id_adh)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

INSERT INTO galette_activities_subscriptions_new (id_subscription, id_activity, id_adh, is_paid, payment_amount,
  payment_method, creation_date, subscription_date, end_date, comment)
SELECT id_subscription, id_activity, id_adh, is_paid, payment_amount,
  payment_method, creation_date, subscription_date, end_date, comment
FROM galette_activities_subscriptions;

DROP TABLE galette_activities_subscriptions, galette_activities_activities;

RENAME TABLE galette_activities_activities_new TO galette_activities_activities,
  galette_activities_subscriptions_new TO galette_activities_subscriptions;

SET FOREIGN_KEY_CHECKS=1;

ALTER TABLE galette_activities_activities
  ADD CONSTRAINT galette_activities_activities_id_group_fkey FOREIGN KEY (id_group)
    REFERENCES galette_groups (id_group) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE galette_activities_subscriptions
  ADD CONSTRAINT galette_activities_subscriptions_id_activity_fkey FOREIGN KEY (id_activity)
    REFERENCES galette_activities_activities (id_activity) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT galette_activities_subscriptions_id_adh_fkey FOREIGN KEY (id_adh)
    REFERENCES galette_adherents (id_adh) ON DELETE CASCADE ON UPDATE CASCADE;
