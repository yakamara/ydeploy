<?php

class rex_api_ydeploy_unlock_page extends rex_api_function
{
    public function execute()
    {
        $protectedPage = rex_get('protected_page', 'string');

        $unlockPage = null;
        foreach (rex_ydeploy_handler::getProtectedPages() as $page => $subpages) {
            if (0 === strpos($protectedPage.'/', $page.'/')) {
                $unlockPage = $page;

                break;
            }
        }

        if (!$unlockPage) {
            throw new rex_api_exception('The page "'.$protectedPage.'" is not protected.');
        }

        rex_ydeploy_handler::unlockPage($unlockPage);

        if ($redirect = rex_get('redirect', 'string')) {
            rex_response::sendRedirect($redirect);
        }

        $result = new rex_api_result(true);
        $result->setRequiresReboot(true);

        return $result;
    }

    protected function requiresCsrfProtection()
    {
        return true;
    }
}
