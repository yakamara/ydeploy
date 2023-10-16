YDeploy
=======

Das Addon bietet Tools für die Datenbank-Migration während des Deployments von REDAXO-Projekten.
 Zusätzlich bietet es eine auf REDAXO abgestimmte Konfiguration für [deployer](https://deployer.org).

Migration
---------

Das Addon bietet zwei Konsolen-Befehle für die Migration:

### `redaxo/bin/console ydeploy:diff`

Beim ersten Aufruf dieses Kommandos werden in `redaxo/data/addons/ydeploy` zwei Dateien angelegt:

* `schema.yml` mit den Tabellendefinitionen der Datenbank
* `fixtures.yml` mit allen Datensätzen der Tabellen, deren Daten mit synchronisiert werden sollen (Metainfo-Definitionen, MediaManager-Typen, YForm-Manager-Defintionen etc.)

Beim erneuten Aufruf wird dann die aktuelle Datenbank-Struktur mit der aus der `schema.yml` verglichen, und die relevanten Daten mit der `fixtures.yml`. Sollte es Abweichungen geben, werden die beiden Dateien aktualisiert und eine Migrationsdatei in `redaxo/data/addons/ydeploy/migrations/` erstellt, die alle Änderungen enthält.

Details des Kommandos erhält man über `redaxo/bin/console help ydeploy:diff`.

Templates, Module und Actions werden nicht über die `fixtures.yml` synchronisiert, sondern dafür sollte das [Developer-Addon](https://github.com/FriendsOfREDAXO/developer) genutzt werden.

### `redaxo/bin/console ydeploy:migrate`

Dieses Kommando führt alle noch ausstehenden Migrationsdateien aus.

Bei Nutzung von deployer (siehe unten) wird dieses Kommando automatisch während des Deployments ausgeführt.
Es ist aber auch geeignet, um Datenbank-Änderungen der anderen Entwickler in die lokale Entwicklungsumgebung zu übernehmen.

Details des Kommandos erhält man über `redaxo/bin/console help ydeploy:migrate`.

## Deployment

Führe `dep deploy` aus, um das Deployment auf den Zielserver zu starten. [Dieser Befehl besteht aus zwei Teilen](https://github.com/yakamara/ydeploy/blob/master/deploy.php#L92-L95), die sich auch einzeln ausführen lassen:

1. lokal vorbereiten und aufspielen: `dep build` und 
2. danach `dep release [host]`

So lässt sich bspw. `dep deploy` auf einen Test- oder Stage-Server gemäß `default_stage` deployen, testen und anschließen mit dem bereits vorliegenden Build auf den Live-/Produktivserver aufspielen: `dep release live`

Setup für deployer
------------------

Zunächst sollte man sich mit den Grundlagen von deployer vertraut machen: https://deployer.org

Das Addon liefert deployer selbst nicht mit, es sollte vorzugsweise global mittels Composer installiert werden:

```
composer global require deployer/deployer
```

Mehr Infos: https://deployer.org/docs/installation

## Konfiguration

Im Projekt-Root sollte die Konfigurationsdatei `deploy.php`  angelegt werden, die die auf REDAXO abgestimmte 
[Basis-Konfiguration](https://github.com/yakamara/ydeploy/blob/master/deploy.php) aus diesem Addon einbindet:

```php
<?php

namespace Deployer;

if ('cli' !== PHP_SAPI) {
    throw new \Exception('The deployer configuration must be used in cli.');
}

// Der Pfad ist ggf. anzupassen, falls der Projekt-Root nicht dem REDAXO-Root entspricht
require __DIR__.'/redaxo/src/addons/ydeploy/deploy.php';

set('repository', 'git@github.com:user/repo.git');

host('servername')
    ->setHostname('example.com')
    ->setDeployPath('/var/www/com.example')
;
```

In dieser Datei kann die Konfiguration individuell auf das Projekt abgestimmt werden, sowie durch eigene weitere Tasks
ergänzt werden.
Siehe dazu: 
* https://deployer.org/docs/7.x/basics
* https://deployer.org/docs/7.x/hosts
* https://deployer.org/docs/7.x/tasks

### .gitignore

Die folgende `.gitignore` hat sich als Basis bewährt bei Nutzung von deployer:

```
/.build
/media/*
!/media/.redaxo
/redaxo/cache/*
!/redaxo/cache/.*
/redaxo/data/addons/*/*
!/redaxo/data/addons/developer/*
!/redaxo/data/addons/mblock/*
!/redaxo/data/addons/mform/*
!/redaxo/data/addons/ydeploy/*
/redaxo/data/core/*
/redaxo/data/log/*
```

Sollte REDAXO nicht direkt im Projekt-Root liegen, müssen die Pfade entsprechend angepasst werden.

### Optional: Stage-Server

Wenn man `dep deploy` aufruft, greift der `default_stage` zuerst.

> **Vorteil:** Man deployed nicht durch Unachtsamkeit auf den Live-Server sondern nur die Testserver. Will man auch live deployen, muss man das immer explizit angeben: `dep deploy live`

Dazu in der `deploy.php` folgende Einstellungen vornehmen.

1. `default_stage` einstellen: `set('default_stage', 'test');`

2. im Host `stage` einstellen (kann mehrfach gesetzt werden): 

```
host('yakamara')
    ->stage('test')

host('preview')
    ->stage('test')
```
