<?php

class AdminController extends Controller
{
    protected function guardAdmin()
    {
        Auth::requireLogin();
        if (!Auth::isAdmin()) {
            http_response_code(403);
            exit('Forbidden');
        }
    }

    /**
     * Permission guard (RBAC). Accepts "entity.action" or an array of
     * alternatives where holding any one of them is enough.
     *
     * While enforcement is off (config/rbac.php) this is exactly guardAdmin(),
     * so wiring it in area by area cannot change behaviour before the switch
     * is turned on.
     */
    protected function guardPermission($permission)
    {
        Auth::requireLogin();

        if (!class_exists('Permissions') || !Permissions::enforced()) {
            if (!Auth::isAdmin()) {
                $this->denyPermission($permission);
            }
            return;
        }

        $permissions = is_array($permission) ? $permission : array($permission);
        $user = Auth::user();

        foreach ($permissions as $perm) {
            if (Permissions::can($user, (string) $perm)) {
                return;
            }
        }

        $this->denyPermission($permission);
    }

    protected function postGuardPermission($permission)
    {
        $this->guardPermission($permission);
        CSRF::verifyRequest();
    }

    private function denyPermission($permission)
    {
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

        http_response_code(403);
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array(
                'ok'      => false,
                'success' => false,
                'error'   => 'ليس لديك صلاحية للوصول إلى هذه العملية.',
            ), JSON_UNESCAPED_UNICODE);
            exit;
        }

        $label = is_array($permission) ? implode(' / ', $permission) : (string) $permission;
        exit('Forbidden: ' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8'));
    }

    protected function postGuard()
    {
        $this->guardAdmin();
        CSRF::verifyRequest();
    }

    protected function renderAdmin($view, array $data = array())
    {
        $this->view($view, $data);
    }

    protected function view($view, array $data = array())
    {
        $this->guardAdmin();
        $viewFile = dirname(__DIR__) . '/views/' . ltrim($view, '/') . '.php';

        if (!is_file($viewFile)) {
            throw new RuntimeException('Admin view not found: ' . $view);
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        // Only skip layout if explicitly requested via $noLayout = true
        if (!empty($noLayout)) {
            echo $content;
            return;
        }

        $layoutFile = dirname(__DIR__) . '/views/layouts/admin.php';
        require $layoutFile;
    }

    protected function audit($action, $entity, $entityId = null, $oldValues = null, $newValues = null)
    {
        if (class_exists('ActivityLogger')) {
            ActivityLogger::log($action, $entity, $entityId, $oldValues, $newValues);
        }
    }
}
