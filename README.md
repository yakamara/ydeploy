YDeploy
=======

Das Addon bietet Tools für die Datenbank-Migration während des Deployments von REDAXO-Projekten.
 Zusätzlich bietet es eine auf REDAXO abgestimmte Konfiguration für [deployer](https://deployer.org).

Migration
---------

Das Addon bietet zwei Konsolen-Befehle für die Migration:

### `redaxo/bin/console ydeploy:diff`

Beim ersten Aufruf dieses Kommandos wird eine Schemadatei in `redaxo/data/addons/ydeploy/schema.yml` angelegt, mit allen Tabellendefinitionen der Datenbank.

Beim erneuten Aufruf wird dann die aktuelle Datenbank-Struktur mit der aus der Schemadatei verglichen. Sollte es Abweichungen geben, wird die Schemadatei aktualisiert und eine Migrationsdatei in `redaxo/data/addons/ydeploy/migrations/` erstellt, die alle Änderungen enthält.

Details des Kommandos erhält man über `redaxo/bin/console help ydeploy:diff`.

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

server('servername', 'example.com')
    ->identityFile()
    ->set('deploy_path', '/var/www/com.example')
;
```

In dieser Datei kann die Konfiguration individuell auf das Projekt abgestimmt werden, sowie durch eigene weitere Tasks
ergänzt werden.
Siehe dazu: 
* https://deployer.org/docs/configuration 
* https://deployer.org/docs/servers
* https://deployer.org/docs/tasks

### .gitignore

Die folgende `.gitignore` hat sich als Basis bewährt bei Nutzung von deployer:

```
/media/*
!/media/.redaxo
/redaxo/cache/*
/redaxo/data/*
!/redaxo/cache/.*
!/redaxo/data/.*
!/redaxo/data/addons/ydeploy/*
```

Sollte REDAXO nicht direkt im Projekt-Root liegen, müssen die Pfade entsprechend angepasst werden.
