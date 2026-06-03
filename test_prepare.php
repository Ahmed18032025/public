<?php
require_once 'wp-load.php';
global $wpdb;
$p = $wpdb->prefix;
$where_clause = "1=1";
$orderby = "sort_order";
$order = "ASC";
$prepare = array();
$query = "SELECT c.* FROM {$p}gmcq_categories c WHERE {$where_clause} ORDER BY c.{$orderby} {$order}";
var_dump($wpdb->prepare($query, $prepare));
