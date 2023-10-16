Upgrade YDeploy
===============

Von 1.x auf 2.x
---------------

* Es wird deployer v7 benötigt:
  - Ggf. die globale Installation von deployer aktualisieren:  
    `composer global require deployer/deployer:^7.0`  
  - Oder alternativ deployer v7 im Projekt installieren (ggf. in separatem `.tools`-Ordner):  
    `composer require deployer/deployer:^7.0`
  - Oder mit der `deployer.phar` arbeiten: https://deployer.org/download
* Die Hosts in der `deploy.php` anpassen:
  ```diff
  host('production')
  -    ->hostname('example.com')
  +    ->setHostname('example.com')
  -    ->set('deploy_path', '/var/www/www.example')
  +    ->setDeployPath('/var/www/www.example')
  -    ->set('user', 'ssh-12345')
  +    ->setRemoteUser('ssh-12345')
  -    ->stage('production')
  +    ->setLabels(['stage' => 'production']) // oder ganz weglassen, wenn der Host-Name dem Stage-Namen entspricht 
  ```
* Die `default_stage`-Konfiguration ist entfallen. Stattdessen immer den Host angeben beim Deployen, falls es mehrere Server gibt.   
  Dazu auch https://deployer.org/docs/7.x/selector beachten.
* Die Konfigurationen `yarn` und `gulp` entfernen.  
  Es wird automatisch erkannt, ob eine `packages.json` existiert und dann per yarn oder npm installiert. Ebenso werden Webpack und Gulp automatisch erkannt.  
  Alternativ können die Befehle über `set('assets_install', 'yarn install')` und `set('assets_install', 'yarn build')` explizit angepasst werden.
* Falls die [Yak-Struktur](https://github.com/yakamara/yak) verwendet wird, kann die `deploy.php` vereinfacht werden:
  ```diff
  <?php
  
  // ...
  
  -require __DIR__.'/redaxo/src/addons/ydeploy/deploy.php';
  +require __DIR__.'/redaxo/src/addons/ydeploy/deploy_yak.php';
  
  // ...
  
  -set('base_dir', 'public/');
  -set('cache_dir', 'var/cache');
  -set('data_dir', 'var/data');
  -set('src_dir', 'src');
  -set('bin/console', 'bin/console');
  
  add('shared_dirs', [
  -    'var/log',
  ]);
  
  add('writable_dirs', [
  -    'var/log',
  ]);
  ```
* Neue Command-Namen nutzen:
  - `dep build` -> `dep build local`
  - `dep local:setup` -> `dep setup local`
* Bei Problemen ggf. weitere Anpassungen gemäß [Upgrade-Guide](https://deployer.org/docs/7.x/UPGRADE) von deployer vornehmen.  
  Viele Anpassungen sind jedoch automatisch in YDeploy 2 enthalten. Insbesondere "Step 2" muss nicht beachtet werden, da sich YDeploy automatisch um die Fortführung der Release-Nummerierung kümmert.
