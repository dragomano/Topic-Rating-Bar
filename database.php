<?php

if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif(!defined('SMF'))
	die('<b>Error:</b> Cannot install - please verify that you put this file in the same place as SMF\'s index.php and SSI.php files.');

if ((SMF == 'SSI') && !$user_info['is_admin'])
	die('Admin privileges required.');

$tables[] = array(
	'name' => 'topic_ratings',
	'columns' => array(
		0 => array(
			'name'     => 'id',
			'type'     => 'mediumint',
			'size'     => 8,
			'unsigned' => true,
			'null'     => false
		),
		1 => array(
			'name'    => 'total_votes',
			'type'    => 'mediumint',
			'size'    => 8,
			'null'    => false,
			'default' => 0
		),
		2 => array(
			'name'    => 'total_value',
			'type'    => 'mediumint',
			'size'    => 8,
			'null'    => false,
			'default' => 0
		),
		3 => array(
			'name' => 'user_ids',
			'type' => 'longtext',
			'null' => false
		)
	),
	'indexes' => array(
		array(
			'type'    => 'unique',
			'columns' => array('id')
		),
		array(
			'type' => 'index',
			'columns' => array('total_votes', 'total_value')
		)
	)
);

db_extend('packages');

foreach($tables as $table) {
	$smcFunc['db_create_table']('{db_prefix}' . $table['name'], $table['columns'], $table['indexes'], array(), 'update');
	if (isset($table['default']))
		$smcFunc['db_insert']('ignore', '{db_prefix}' . $table['name'], $table['default']['columns'], $table['default']['values'], $table['default']['keys']);
}

if (SMF == 'SSI')
	echo 'Database changes are complete! Please wait...';
