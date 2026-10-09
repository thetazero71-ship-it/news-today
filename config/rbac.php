<?php
/**
 * RBAC kill switch + defaults.
 *
 * Enforcement is OFF by default: until RBAC_ENFORCE is turned on every guard
 * behaves exactly like the old guardAdmin() (login + admin/editor role), so the
 * site keeps working while roles are being configured.
 *
 * Turn it on either by editing the constant below, or from the host with the
 * environment variable RBAC_ENFORCE=1 (no redeploy needed if the host supports it).
 */

if (!defined('RBAC_ENFORCE')) {
    $rbacEnv = getenv('RBAC_ENFORCE');
    $rbacOn  = ($rbacEnv !== false && $rbacEnv !== '')
        ? in_array(strtolower((string) $rbacEnv), array('1', 'true', 'on', 'yes'), true)
        : false;
    define('RBAC_ENFORCE', $rbacOn);
}

if (!defined('RBAC_SUPER_ROLE')) {
    define('RBAC_SUPER_ROLE', 'admin');
}
