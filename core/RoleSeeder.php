<?php

/**
 * Seeds role permissions from config/role_permissions.default.json.
 *
 * Rules that keep this safe to run on every visit:
 *  - a role is only written when its permissions do NOT carry the "_v2" marker,
 *    so any manual edit made from the roles screen is never overwritten
 *  - the super role (admin) is never rewritten; it keeps bypass behaviour
 *  - missing standard roles are created, existing roles are never renamed
 */
class RoleSeeder
{
    /** Bumped whenever the standard role model changes, so older seeded rows get upgraded. */
    const MODEL = 'standard-2';

    public static function defaultsPath(): string
    {
        return dirname(__DIR__) . '/config/role_permissions.default.json';
    }

    public static function defaults(): array
    {
        $file = self::defaultsPath();
        if (!is_file($file)) {
            return array();
        }
        $json = json_decode(file_get_contents($file), true);
        if (!is_array($json)) {
            return array();
        }
        $out = array();
        foreach ($json as $name => $perms) {
            if ($name === '' || $name[0] === '_') {
                continue;
            }
            $out[(string) $name] = $perms;
        }
        return $out;
    }

    /**
     * Arabic display names declared next to the permissions (if provided).
     */
    public static function arabicNames(): array
    {
        $file = self::defaultsPath();
        if (!is_file($file)) {
            return array();
        }
        $json = json_decode(file_get_contents($file), true);
        return (isset($json['_arabic']) && is_array($json['_arabic'])) ? $json['_arabic'] : array();
    }

    /**
     * @return array{created:string[],updated:string[],skipped:string[],error:string}
     */
    public static function ensure($db): array
    {
        $report = array('created' => array(), 'updated' => array(), 'upgraded' => array(), 'skipped' => array(), 'error' => '');
        $defaults = self::defaults();
        if (empty($defaults)) {
            return $report;
        }

        try {
            $columns = array();
            foreach ($db->fetchAll('SHOW COLUMNS FROM roles') as $col) {
                if (!empty($col['Field'])) {
                    $columns[strtolower((string) $col['Field'])] = true;
                }
            }
            if (!isset($columns['permissions']) || !isset($columns['name'])) {
                $report['error'] = 'جدول الأدوار لا يحتوي العمود permissions';
                return $report;
            }

            $existing = array();
            foreach ($db->fetchAll('SELECT id, name, permissions FROM roles') as $row) {
                $existing[mb_strtolower(trim((string) $row['name']))] = $row;
            }

            foreach ($defaults as $name => $perms) {
                $key = mb_strtolower(trim($name));
                $encoded = self::encode($perms);

                // The super role bypasses the catalog, so it is never rewritten.
                if ($key === mb_strtolower(Permissions::superRole())) {
                    $report['skipped'][] = $name;
                    continue;
                }

                if (!isset($existing[$key])) {
                    self::createRole($db, $name, $perms, $columns, $key);
                    $report['created'][] = $name;
                    continue;
                }

                $row = $existing[$key];
                $current = self::decode((string) $row['permissions']);

                if (self::isManaged($current)) {
                    $report['skipped'][] = $name;
                    continue;
                }

                // No marker at all = never touched by the roles screen (safe to seed).
                // Old _v2 marker = written by an earlier model (upgrade it).
                if (isset($current['_v2'])) {
                    $report['upgraded'][] = $name;
                }

                $db->query('UPDATE roles SET permissions = :p WHERE id = :id', array(
                    ':p' => $encoded,
                    ':id' => (int) $row['id'],
                ));
                if (!isset($current['_v2'])) {
                    $report['updated'][] = $name;
                }
            }
        } catch (Throwable $e) {
            $report['error'] = $e->getMessage();
        }

        return $report;
    }

    /**
     * True when the role already carries the CURRENT model marker, i.e. it was
     * seeded or hand-edited through the roles screen and must not be touched.
     */
    public static function isManaged($permissions): bool
    {
        if (is_string($permissions)) {
            $permissions = self::decode($permissions);
        }
        if (!is_array($permissions)) {
            return false;
        }
        if (isset($permissions['_model']) && $permissions['_model'] === self::MODEL) {
            return true;
        }
        // hand edits from the roles screen always carry the version marker
        return !empty($permissions['_roles_ui']);
    }

    /**
     * Seeded by an EARLIER model (has the old _v2 marker but no _model) -> safe
     * to upgrade, because those rows were never edited by hand in the roles screen.
     */
    public static function needsUpgrade($permissions): bool
    {
        if (is_string($permissions)) {
            $permissions = self::decode($permissions);
        }
        if (!is_array($permissions)) {
            return false;
        }
        return !empty($permissions['_v2']) && !isset($permissions['_model']);
    }

    public static function encode(array $permissions): string
    {
        $permissions['_v2'] = 1;
        $permissions['_model'] = self::MODEL;
        ksort($permissions);
        return (string) json_encode($permissions, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Encode permissions that were edited by hand in the roles screen.
     * These are never re-seeded, even when the standard model changes later.
     */
    public static function encodeManual(array $permissions): string
    {
        $encoded = $permissions;
        $encoded['_roles_ui'] = 1;
        ksort($encoded);
        return (string) json_encode($encoded, JSON_UNESCAPED_UNICODE);
    }

    public static function decode(?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return array();
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : array();
    }

    /**
     * Turn a flat list of checked permissions back into the nested JSON shape
     * the engine understands: {"articles":["create","edit"], "all":true}
     */
    public static function fromCheckboxList(array $tokens): array
    {
        $out = array();
        $hasAll = false;

        foreach ($tokens as $token) {
            $token = mb_strtolower(trim((string) $token));
            if ($token === '') {
                continue;
            }
            if ($token === 'all') {
                $hasAll = true;
                continue;
            }
            if (strpos($token, '.') === false) {
                $out[$token] = true;
                continue;
            }
            list($entity, $action) = explode('.', $token, 2);
            if ($entity === '') {
                continue;
            }
            if (!isset($out[$entity]) || !is_array($out[$entity])) {
                $out[$entity] = array();
            }
            $out[$entity][$action] = true;
        }

        if ($hasAll) {
            return array('all' => true);
        }

        // {"articles":{"create":true}} -> {"articles":["create"]}
        foreach ($out as $entity => $value) {
            if (is_array($value)) {
                $actions = array();
                foreach ($value as $action => $flag) {
                    if ($flag) {
                        $actions[] = (string) $action;
                    }
                }
                sort($actions);
                $out[$entity] = $actions;
            }
        }
        ksort($out);
        return $out;
    }

    private static function createRole($db, string $name, array $perms, array $columns, string $key): void
    {
        $names = self::arabicNames();
        $nameAr = isset($names[$name]) ? (string) $names[$name] : self::arabicName($key);
        $values = array(
            'name'        => $key,
            'name_ar'     => $nameAr,
            'permissions' => self::encode($perms),
            'is_default'  => ($key === 'reader') ? 1 : 0,
        );

        $cols = array();
        $marks = array();
        $params = array();
        foreach ($values as $col => $val) {
            if (!isset($columns[$col])) {
                continue;
            }
            $cols[] = $col;
            $marks[] = ':' . $col;
            $params[':' . $col] = $val;
        }
        if (!in_array('name', $cols, true)) {
            return;
        }

        $sql = 'INSERT INTO roles (' . implode(',', $cols) . ') VALUES (' . implode(',', $marks) . ')'
             . ' ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)';
        $db->query($sql, $params);
    }

    private static function arabicName(string $key): string
    {
        $map = array(
            'admin'             => 'مدير النظام',
            'managing_editor'   => 'المدير التحريري',
            'editor'            => 'محرر رئيسي',
            'publisher'         => 'ناشر',
            'author'            => 'كاتب ومحرر محتوى',
            'contributor'       => 'مساهم',
            'translator'        => 'مترجم',
            'moderator'         => 'مشرف التفاعل',
            'newsletter_manager'=> 'مسؤول النشرة البريدية',
            'analyst'           => 'محلل (قراءة فقط)',
            'subscriber'        => 'مشترك مميز',
            'reader'            => 'قارئ مسجل',
        );
        return $map[$key] ?? $key;
    }
}