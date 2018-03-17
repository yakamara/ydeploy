<?php

$ydeploy = rex_ydeploy::factory();

if ($ydeploy->isDeployed()) {
    $info = [
        'Deployed' => rex_formatter::strftime($ydeploy->getTimestamp()->getTimestamp(), 'datetime'),
        'Host' => $ydeploy->getHost(),
        'Stage' => $ydeploy->getStage(),
        'Commit' => $ydeploy->getCommit(),
    ];
} else {
    $info = [
        'Deployed' => rex_i18n::rawMsg('no'),
    ];
}

$content = '';

foreach ($info as $key => $value) {
    $content .= '<dt>'.rex_escape($key).'</dt>';
    $content .= '<dd>'.rex_escape($value).'</dd>';
}

$content = '<dl class="dl-horizontal">'.$content.'</dl>';

$fragment = new rex_fragment();
$fragment->setVar('title', 'Info');
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');
