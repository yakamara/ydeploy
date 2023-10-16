<?php

/**
 * @internal
 */
final class rex_api_ydeploy_protected_page extends rex_api_function
{
    public function execute(): rex_api_result
    {
        if (!rex::requireUser()->isAdmin()) {
            throw new rex_api_exception('Protected pages can be (un)locked only by admins.');
        }

        $action = rex_get('action', 'string');

        if (!in_array($action, ['lock', 'unlock'], true)) {
            throw new rex_api_exception('Supported protected page actions are "lock" and "unlock", but "' . $action . '" given.');
        }

        $protectedPage = rex_get('protected_page', 'string');

        $foundPage = null;
        foreach (rex_ydeploy_handler::getProtectedPages() as $page => $subpages) {
            // `yform/manager/table_edit` must not match `yform/man`
            // so we add slashes to avoid this
            if (str_starts_with($protectedPage . '/', $page . '/')) {
                $foundPage = $page;

                break;
            }
        }

        if (!$foundPage) {
            throw new rex_api_exception('The page "' . $protectedPage . '" is not protected.');
        }

        if ('unlock' === $action) {
            rex_ydeploy_handler::unlockPage($foundPage);
        } else {
            rex_ydeploy_handler::lockPage($foundPage);
        }

        if ($redirect = rex_get('redirect', 'string')) {
            rex_response::sendRedirect($redirect);
        }

        $result = new rex_api_result(true);
        $result->setRequiresReboot(true);

        return $result;
    }

    protected function requiresCsrfProtection(): bool
    {
        return true;
    }
}
