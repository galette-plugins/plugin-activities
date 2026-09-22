---
title: Dokumentacija
description: Vodenje dejavnosti
---

Ta vtičnik je bil razvit za [Association
l'Aphyllanthe](https://www.aphyllanthe.fr/). Ponuja:

* upravljanje dejavnosti,
* upravljanje naročnin.

## Namestitev

Najprej prenesite vtičnik:

* [Pridobite najnovejši vtičnik za
  dejavnosti!](https://github.com/galette-plugins/plugin-activities/releases/latest)
* [Pridobite nočno gradnjo vtičnika za
  dejavnosti!](https://github.com/galette-plugins/plugin-activities/releases/tag/nightly)

Razširite prenesen arhiv v imenik Galette `plugins`. Na primer v Linuxu
(zamenjajte `{url}` in `{version}` s pravilnimi vrednostmi):

```bash
$ cd /var/www/html/galette/plugins
$ wget {url}
$ tar xjvf galette-plugin-activities-{version}.tar.bz2
```

## Inicializacija baze podatkov

Za delovanje ta vtičnik potrebuje več tabel v bazi podatkov. Glejte [Vmesnik za
upravljanje vtičnikov
Galette](https://doc.galette.eu/en/master/plugins/index.html#plugins-managment).

In to je končano; vtičnik Activities je nameščen :)
