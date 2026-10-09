<?php

class RolesController extends AdminController
{
    public function index()
    {
        $this->guardPermission('roles.manage');
        $db = new Database();

        $seed = RoleSeeder::ensure($db);

        $roles = $db->fetchAll('SELECT * FROM roles ORDER BY id ASC');

        $counts = array();
        foreach ($db->fetchAll('SELECT role_id, COUNT(*) AS total FROM users GROUP BY role_id') as $row) {
            $counts[(int) $row['role_id']] = (int) $row['total'];
        }

        $rows = array();
        foreach ($roles as $role) {
            $permissions = RoleSeeder::decode((string) ($role['permissions'] ?? ''));
            $tokens = Permissions::grantedTokens(array(
                'role_name'    => $role['name'],
                'permissions'  => $role['permissions'],
            ));

            $granted = 0;
            foreach (array_keys(Permissions::all()) as $perm) {
                if (Permissions::can(array('role_name' => $role['name'], 'permissions' => $role['permissions']), $perm)) {
                    $granted++;
                }
            }

            $rows[] = array(
                'id'          => (int) $role['id'],
                'name'        => (string) $role['name'],
                'name_ar'     => (string) ($role['name_ar'] ?: $role['name']),
                'is_default'  => (int) ($role['is_default'] ?? 0),
                'is_super'    => mb_strtolower((string) $role['name']) === mb_strtolower(Permissions::superRole()),
                'managed'     => RoleSeeder::isManaged($permissions),
                'users'       => $counts[(int) $role['id']] ?? 0,
                'granted'     => $granted,
                'total'       => count(Permissions::all()),
                'tokens'      => array_keys($tokens),
                'summary'     => Permissions::describe(array('role_name' => $role['name'], 'permissions' => $role['permissions'])),
            );
        }

        $this->view('admin/roles/index', array(
            'roles'        => $rows,
            'seed'         => $seed,
            'enforced'     => Permissions::enforced(),
            'success'      => Session::getFlash('success'),
            'error'        => Session::getFlash('error'),
        ));
    }

    public function edit($id)
    {
        $this->guardPermission('roles.manage');
        $db = new Database();

        $role = $db->fetch('SELECT * FROM roles WHERE id = :id', array(':id' => (int) $id));
        if (!$role) {
            http_response_code(404);
            return $this->view('errors/404');
        }

        $this->view('admin/roles/edit', array(
            'role'      => $role,
            'tokens'    => array_keys(Permissions::grantedTokens(array(
                'role_name'   => $role['name'],
                'permissions' => $role['permissions'],
            ))),
            'users'     => (int) ($db->fetch('SELECT COUNT(*) AS c FROM users WHERE role_id = :id', array(':id' => (int) $id))['c'] ?? 0),
            'success'   => Session::getFlash('success'),
            'error'     => Session::getFlash('error'),
        ));
    }

    public function update($id)
    {
        $this->postGuardPermission('roles.manage');
        $db = new Database();

        $role = $db->fetch('SELECT * FROM roles WHERE id = :id', array(':id' => (int) $id));
        if (!$role) {
            http_response_code(404);
            return $this->view('errors/404');
        }

        if (mb_strtolower((string) $role['name']) === mb_strtolower(Permissions::superRole())) {
            Session::flash('error', 'دور ' . Permissions::superRole() . ' يملك كل الصلاحيات تلقائياً ولا يمكن تعديله.');
            return $this->redirect('admin/roles');
        }

        $submitted = isset($_POST['permissions']) ? (array) $_POST['permissions'] : array();
        $permissions = RoleSeeder::fromCheckboxList($submitted);
        $encoded = RoleSeeder::encode($permissions);

        $old = (string) ($role['permissions'] ?? '');

        $db->query('UPDATE roles SET permissions = :p WHERE id = :id', array(
            ':p' => $encoded,
            ':id' => (int) $id,
        ));

        $this->audit('update', 'role_permissions', (int) $id, array('permissions' => $old), array('permissions' => $encoded));

        Session::flash('success', 'تم تحديث صلاحيات الدور «' . ($role['name_ar'] ?: $role['name']) . '».');
        return $this->redirect('admin/roles');
    }
}