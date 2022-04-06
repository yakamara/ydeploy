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

Setup für deployer
------------------

Zunächst sollte man sich mit den Grundlagen von deployer vertraut machen: https://deployer.org

Das Addon liefert deployer selbst nicht mit, es sollte vorzugsweise global mittels Composer installiert werden:

```
composer global require deployer/deployer
```

Mehr Infos: https://deployer.org/docs/installation

### Konfiguration

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
    ->hostname('example.com')
    ->set('deploy_path', '/var/www/com.example')
;
```

In dieser Datei kann die Konfiguration individuell auf das Projekt abgestimmt werden, sowie durch eigene weitere Tasks
ergänzt werden.
Siehe dazu: 
* https://deployer.org/docs/configuration 
* https://deployer.org/docs/hosts
* https://deployer.org/docs/tasks

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
```

Sollte REDAXO nicht direkt im Projekt-Root liegen, müssen die Pfade entsprechend angepasst werden.

### Benutzer- und Gruppen-Dateiberechtigungen auf dem Server

Sollten beim Deployment-Prozess Fehler mit den Schreibrechten auftreten, kann dieses Skript angepasst, auf dem Webserver abgelegt und per SSH mit sudo ausgeführt werden. Zuvor `###user###` und ggf. den Pfad zum Projekt `###example.org###` anpassen.

```
#!/bin/bash

pushd . > /dev/null
#cd /home/###user###/htdocs/
for i in /home/###user###/htdocs/###example.org###/releases/*; do
	cd $i;
	chown -R ###user###:www-data src public/media/ public/assets/ var/cache/ var/data/ var/log/;
	chmod -R u+rw src public/media/ public/assets/ var/cache/ var/data/ var/log;
	chmod -R g+rw src public/media/ public/assets/ var/cache/ var/data/ var/log;
	chmod -R o-w src public/media/ public/assets/ var/cache/ var/data/ var/log;
	chmod -R u+x var/cache/addons var/data/addons/phpmailer var/data/addons/cronjob var/data/addons/yform var/data/core;
	chmod -R g+x var/cache/addons var/data/addons/phpmailer var/data/addons/cronjob var/data/addons/yform var/data/core;
done

cd /home/###user###/htdocs/###example.org###/shared/
chown -R ###user###:www-data var/data/addons/ var/data/core/
chmod -R u+rw var/data/addons/ var/data/core/
chmod -R g+rw var/data/addons/ var/data/core/
popd > /dev/null
```
