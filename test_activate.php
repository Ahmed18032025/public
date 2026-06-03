<?php
require 'wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$result = activate_plugin('gmcq-core/gmcq-core.php');
if (is_wp_error($result)) {
    echo "Error: " . $result->get_error_message();
} else {
    echo "Plugin activated.";
}
