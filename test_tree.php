<?php
require_once 'wp-load.php';
$tree = gmcq_get_category_tree(array('filter' => 'all'));
print_r($tree);
