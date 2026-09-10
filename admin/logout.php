<?php
require_once __DIR__ . '/_bootstrap.php';
admin_logout();
admin_redirect(admin_base() . '/login.php');
