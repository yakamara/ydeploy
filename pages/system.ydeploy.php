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

$apiUrl = function (array $params, string $page, string $redirect = null) {
    $params['protected_page'] = $page;

    if ($redirect) {
        $params['redirect'] = $redirect;
    }

    return rex_url::currentBackendPage($params);
};

$calledPage = rex_request('page', 'string');

if ('system/ydeploy' !== $calledPage) {
    $redirect = rex_context::fromGet()->getUrl([], false);
    $url = $apiUrl(rex_api_ydeploy_unlock_page::getUrlParams(), $calledPage, $redirect);

    echo rex_view::error('
        The called page <code>'.$calledPage.'</code> is protected in deployed instances because it should be used only in development instances. <br><br>
        <a href="'.$url.'">Unlock and open it anyway</a>
    ');
}

$content = '';

$pages = [];

foreach (rex_ydeploy_handler::getProtectedPages() as $page => $subpages) {
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

// add fake pages to navigation, but use original order from rex_be_controller
foreach (rex_be_controller::getPages() as $key => $page) {
    if (!isset($pages[$key])) {
        continue;
    }

    foreach ($pages[$key] as $fakePage) {
        $navi->addPage($fakePage);
    }
}

$unlockedPages = rex_ydeploy_handler::getUnlockedPages();

foreach ($navi->getNavigation() as $block) {
    $content .= '
        <tr>
            <td></td>
            <td colspan="4"><b>'.$block['headline']['title'].'</b></td>
        </tr>
    ';

    foreach ($block['navigation'] as $page) {
        if (isset($unlockedPages[$page['href']])) {
            $url = $apiUrl(rex_api_ydeploy_lock_page::getUrlParams(), $page['href']);
            $action = '<a class="rex-online" href="'.$url.'"><i class="rex-icon fa-unlock-alt"></i> Unlocked</a>';

            $action2 = '<a href="'.rex_url::backendPage($page['href']).'">Open</a>';
        } else {
            $url = $apiUrl(rex_api_ydeploy_unlock_page::getUrlParams(), $page['href']);
            $action = '<a class="rex-offline" href="'.$url.'"><i class="rex-icon fa-lock"></i> Locked</a>';

            $url = $apiUrl(rex_api_ydeploy_unlock_page::getUrlParams(), $page['href'], rex_url::backendPage($page['href']));
            $action2 = '<a href="'.$url.'">Unlock & open</a>';
        }

        $content .= '
            <tr>
                <td class="rex-table-icon"><i class="'.$page['icon'].'"></i></td>
                <td><code>'.rex_escape($page['href']).'</code></td>
                <td>'.$page['title'].'</td>
                <td class="rex-table-action">'.$action.'</td>
                <td class="rex-table-action">'.$action2.'</td>
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
                <th colspan="2"></th>
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
