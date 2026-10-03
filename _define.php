<?php

/**
 * This file is part of Galette Activities plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2024-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

/** @var \Galette\Core\Plugins $this */
$this->register(
    name: 'Galette Activities',    //Name
    desc: 'Activities management', //Short description
    author: 'Johan Cwiklinski',    //Author
    version: '1.2.0',              //Version
    compver: '1.3.0',              //Galette compatible version
    route: 'activities',           //routing name and translation domain
    date: '2026-10-03',            //Release date
    acls: [                        //Permissions needed
        '/activities_.*/'           => 'staff'
    ],
    dbver: 1.1
);
