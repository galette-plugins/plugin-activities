--
-- This file is part of Galette Activities plugin (https://galette.eu).
-- SPDX-FileCopyrightText: Copyright © 2024-2026 The Galette Team
-- SPDX-License-Identifier: GPL-3.0-or-later
--

SET FOREIGN_KEY_CHECKS=0;

--
-- Table structure for table `galette_activities_activities`
--

DROP TABLE IF EXISTS galette_activities_activities;
CREATE TABLE galette_activities_activities (
  id_activity int(10) NOT NULL auto_increment,
  name varchar(150) NOT NULL,
  type varchar(3) NOT NULL default '',
  price decimal(15, 2) default NULL,
  id_group int unsigned default NULL,
  creation_date date NOT NULL,
  comment text,
  PRIMARY KEY (id_activity),
  CONSTRAINT galette_activities_activities_id_group_fkey FOREIGN KEY (id_group)
    REFERENCES galette_groups (id_group) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

--
-- Table structure for table `galette_activities_subscriptions`
--

DROP TABLE IF EXISTS galette_activities_subscriptions;
CREATE TABLE galette_activities_subscriptions (
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
  CONSTRAINT galette_activities_subscriptions_id_activity_id_adh_key UNIQUE (id_activity, id_adh),
  CONSTRAINT galette_activities_subscriptions_id_activity_fkey FOREIGN KEY (id_activity)
    REFERENCES galette_activities_activities (id_activity) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT galette_activities_subscriptions_id_adh_fkey FOREIGN KEY (id_adh)
    REFERENCES galette_adherents (id_adh) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

SET FOREIGN_KEY_CHECKS=1;
