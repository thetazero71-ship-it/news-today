<?php

class ActivityLogger
{
    /**
     * Who is acting right now?
     *
     * Cron jobs and webhooks have no session and stay null ("نظام آلي"), but
     * every request made by a signed-in person must carry their real id —
     * otherwise the audit trail blames the machine for human actions.
     */
    private static function currentUserId()
    {
        if (class_exists('Auth')) {
            try {
                $user = Auth::user();
                if (!empty($user['id'])) {
                    return (int) $user['id'];
                }
            } catch (Throwable $e) {
                // fall through to the session key
            }
        }

        if (class_exists('Session')) {
            try {
                $id = (int) Session::get('auth_user_id', 0);
                if ($id > 0) {
                    return $id;
                }
            } catch (Throwable $e) {
                // no session in this context
            }
        }

        return null;
    }

    public static function log($action, $entity, $entityId = null, $oldValues = null, $newValues = null)
    {
        try {
            $db = new Database();
            if (is_array($newValues)) {
                unset($newValues['_csrf'], $newValues['_csrf_token'], $newValues['password'], $newValues['password_hash'], $newValues['password_confirmation']);
            }
            if (is_array($oldValues)) {
                unset($oldValues['_csrf'], $oldValues['_csrf_token'], $oldValues['password'], $oldValues['password_hash'], $oldValues['password_confirmation']);
            }

            $db->query('INSERT INTO activity_logs (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent) VALUES (:user_id, :action, :entity_type, :entity_id, :old_values, :new_values, :ip_address, :user_agent)', array(
                ':user_id' => self::currentUserId(),
                ':action' => $action,
                ':entity_type' => $entity,
                ':entity_id' => $entityId,
                ':old_values' => $oldValues === null ? null : json_encode($oldValues, JSON_UNESCAPED_UNICODE),
                ':new_values' => $newValues === null ? null : json_encode($newValues, JSON_UNESCAPED_UNICODE),
                ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                ':user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            ));
        } catch (Throwable $e) {
            error_log($e->getMessage());
        }
    }
}
