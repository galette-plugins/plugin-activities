---
title: Documentation
description: Activities management
---

This plugin was developed for [Association
l'Aphyllanthe](https://www.aphyllanthe.fr/). It provides:

* activities management,
* subscriptions management.

## Installation

First of all, download the plugin:

* [Get latest Activities
  plugin!](https://github.com/galette-plugins/plugin-activities/releases/latest)
* [Get Activities plugin nightly
  build!](https://github.com/galette-plugins/plugin-activities/releases/tag/nightly)

Extract the downloaded archive in Galette `plugins` directory. For example,
under linux (replacing `{url}` and `{version}` with correct values):

```bash
$ cd /var/www/html/galette/plugins
$ wget {url}
$ tar xjvf galette-plugin-activities-{version}.tar.bz2
```

## Database initialisation

In order to work, this plugin requires several tables in the database. See
[Galette plugins management
interface](https://doc.galette.eu/en/master/plugins/index.html#plugins-managment).

And this is finished; Activities plugin is installed :)
