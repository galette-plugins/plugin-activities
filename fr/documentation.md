---
title: Documentation
description: Gestion des activités
---

Ce plugin a été développé pour [l'Association
l'Aphyllanthe](https://www.aphyllanthe.fr/). Il fournit :

* gestion d'activités,
* gestion de souscriptions.

## Installation

Tout d'abord, téléchargez le plugin :

* [Obtenir le dernier plugin Activities
  !](https://github.com/galette-plugins/plugin-activities/releases/latest)
* [Obtenir la nightly du plugin Activities
  !](https://github.com/galette-plugins/plugin-activities/releases/tag/nightly)

Extrayez l'archive téléchargée dans le dossier `plugins` de Galette. Par
exemple, sous linux (en remplaçant `{url}` et `{version}` par les valeurs
adéquates) :

```bash
$ cd /var/www/html/galette/plugins
$ wget {url}
$ tar xjvf galette-plugin-activities-{version}.tar.bz2
```

## Initialisation de la base de données

Pour fonctionner, ce plugin requiert des tables dans la base de données.
Référez-vous [à l'interface de gestion des plugins de
Galette](https://doc.galette.eu/en/master/plugins/index.html#plugins-managment).

Et c'est fini ; le plugin Activités est installé :)
