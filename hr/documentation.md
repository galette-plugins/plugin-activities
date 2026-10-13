---
title: Dokumentacija
description: Upravljanje aktivnostima
---

Dodatak je razvijen za [Association l'Aphyllanthe](https://www.aphyllanthe.fr/).
Omogućuje:

* upravljanje aktivnostima,
* upravljanje prijavama.

## Instalacija

Najprije preuzmite dodatak:

* [Preuzmite najnoviji dodatak
  Activities!](https://github.com/galette-plugins/plugin-activities/releases/latest)
* [Preuzmite noćnu razvojnu verziju dodatka
  Activities!](https://github.com/galette-plugins/plugin-activities/releases/tag/nightly)

Raspakirajte preuzetu arhivu u Galetteovu mapu `plugins`. Primjerice, na Linuxu
(zamijenite `{url}` i `{version}` odgovarajućim vrijednostima):

```bash
$ cd /var/www/html/galette/plugins
$ wget {url}
$ tar xjvf galette-plugin-activities-{version}.tar.bz2
```

## Inicijalizacija baze podataka

Dodatak zahtijeva nekoliko tablica u bazi. Pogledajte [sučelje za upravljanje
dodacima
Galettea](https://doc.galette.eu/en/master/plugins/index.html#plugins-managment).

Gotovo; dodatak Activities je instaliran :)
