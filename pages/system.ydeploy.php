<?php

$ydeploy = rex_ydeploy::factory();

if ($ydeploy->isDeployed()) {
    $info = [
        'Deployed' => rex_formatter::strftime($ydeploy->getTimestamp()->getTimestamp(), 'datetime'),
        'Host' => $ydeploy->getHost(),
        'Stage' => $ydeploy->getStage(),
        'Branch' => $ydeploy->getBranch(),
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

if (!$ydeploy->isDeployed()) {
    return;
}

$content = '';

$protectedPages = rex_addon::get('ydeploy')->getProperty('config')['protected_pages'];
$pages = [];

foreach ($protectedPages as $page) {
    $page = rex_be_controller::getPageObject($page);

    if (!$page) {
        continue;
    }

    $icon = $page->getIcon();
    $root = $page;
    while ($parent = $root->getParent()) {
        $root = $parent;
        $icon = $icon ?: $parent->getIcon();
    }

    // create non-hidden fake page
    if ($root instanceof rex_be_page_main) {
        $fakePage = new rex_be_page_main($root->getBlock(), $page->getFullKey(), $page->getTitle());
        $fakePage->setPrio($root->getPrio());
    } else {
        $fakePage = new rex_be_page($page->getFullKey(), $page->getTitle());
    }

    // rex_be_navigation does not provide the page keys in navigation items
    // so we misuse the href for the page key
    $fakePage->setHref($page->getFullKey());
    $fakePage->setIcon($icon);

    $pages[$root->getKey()][] = $fakePage;
}

$navi = rex_be_navigation::factory();
$prio = 10000;

// add fake pages to navigation, but use original order from rex_be_controller
foreach (rex_be_controller::getPages() as $key => $page) {
    if (!isset($pages[$key])) {
        continue;
    }

    foreach ($pages[$key] as $fakePage) {
        // some protected subpages does not have a title, so we force a prio to avoid sorting by title
        if ($fakePage instanceof rex_be_page_main && !$fakePage->getPrio()) {
            $fakePage->setPrio(++$prio);
        }

        $navi->addPage($fakePage);
    }
}

foreach ($navi->getNavigation() as $block) {
    $content .= '
        <tr>
            <td></td>
            <td colspan="3"><b>'.$block['headline']['title'].'</b></td>
        </tr>
    ';

    foreach ($block['navigation'] as $page) {
        $content .= '
            <tr>
                <td class="rex-table-icon"><i class="'.$page['icon'].'"></i></td>
                <td><code>'.rex_escape($page['href']).'</code></td>
                <td>'.$page['title'].'</td>
                <td class="rex-table-action"><span class="rex-offline"><i class="rex-icon fa-lock"></i> Locked</span></td>
            </tr>
        ';
    }
}

$content = '
    <table class="table table-hover">
        <thead>
            <tr>
                <th></th>
                <th>Key</th>
                <th>Title</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            '.$content.'
        </tbody>
    </table>';

$fragment = new rex_fragment();
$fragment->setVar('title', 'Protected Pages');
$fragment->setVar('content', $content, false);
echo $fragment->parse('core/page/section.php');
