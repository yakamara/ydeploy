<?php

if ($console = rex::getConsole()) {
    $console->add(new rex_ydeploy_command_diff());
    $console->add(new rex_ydeploy_command_migrate());
}
