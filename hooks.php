<?php

if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif(!defined('SMF'))
	die('<b>Error:</b> Cannot install - please verify that you put this file in the same place as SMF\'s index.php and SSI.php files.');

if ((SMF == 'SSI') && !$user_info['is_admin'])
	die('Admin privileges required.');
	
$hooks = array(
	'integrate_pre_include'          => '$sourcedir/Subs-TopicRating.php',
	'integrate_admin_include'        => '$sourcedir/Admin-TopicRating.php',
	'integrate_admin_areas'          => 'trb_rating_admin_areas',
	'integrate_modify_modifications' => 'trb_rating_modifications',
	'integrate_actions'              => 'trb_rating_actions',
	'integrate_load_theme'           => 'trb_rating_load_theme',
	'integrate_load_permissions'     => 'trb_rating_permissions',
	'integrate_menu_buttons'         => 'trb_rating_preload',
	'integrate_messageindex_buttons' => 'trb_rating_messageindex',
);

if (!empty($context['uninstalling']))
	$call = 'remove_integration_function';

else
	$call = 'add_integration_function';

foreach ($hooks as $hook => $function)
	$call($hook, $function);
	
if (SMF == 'SSI')
	echo 'Database changes are complete! Please wait...';

?>