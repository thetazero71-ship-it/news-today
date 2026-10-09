<?php

class DiagnosticsController extends AdminController
{
 public function index()
 {
$this->guardPermission('diagnostics.view');

 require_once APP_ROOT . '/diagnostics_hub.php';
 exit;
 }

 public function seo()
 {
$this->guardPermission('diagnostics.view');

 require_once APP_ROOT . '/diagnostics_seo.php';
 exit;
 }

 public function security()
 {
$this->guardPermission('diagnostics.view');

 require_once APP_ROOT . '/diagnostics_security.php';
 exit;
 }

 public function database()
 {
$this->guardPermission('diagnostics.view');

 require_once APP_ROOT . '/diagnostics_database.php';
 exit;
 }

 public function media()
 {
$this->guardPermission('diagnostics.view');

 require_once APP_ROOT . '/diagnostics_media.php';
 exit;
 }

 public function health()
 {
$this->guardPermission('diagnostics.view');

 require_once APP_ROOT . '/health_check.php';
 exit;
 }
}
