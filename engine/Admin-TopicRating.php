<?php
/**
* Topic Rating Bar © 2011-2013, Bugo
* Admin-TopicRating.php
* License http://opensource.org/licenses/artistic-license-2.0
* http://dragomano.ru/page/topic-rating-bar and
* http://custom.simplemachines.org/mods/index.php?mod=3236
*/

if (!defined('SMF'))
	die('Hacking attempt...');

// Loading from integrate_admin_areas
function trb_rating_admin_areas(&$admin_areas)
{
	global $txt;
	
	$admin_areas['config']['areas']['modsettings']['subsections']['topic_rating'] = array($txt['tr_title']);
}

// Loading from integrate_modify_modifications
function trb_rating_modifications(&$subActions)
{
	$subActions['topic_rating'] = 'trb_rating_settings';
}

// Loading from rating_modifications (see above)
function trb_rating_settings()
{
	global $txt, $context, $scripturl, $modSettings;
	
	loadTemplate('TopicRating');
	
	$context['page_title'] = $txt['tr_title'];
	$context['settings_title'] = $txt['settings'];
	$context['permissions_excluded'] = array(-1);
	$context['post_url'] = $scripturl . '?action=admin;area=modsettings;save;sa=topic_rating';
	$context[$context['admin_menu_name']]['tab_data']['tabs']['topic_rating'] = array('description' => $txt['tr_desc']);
	
	if (!isset($modSettings['tr_count_topics']))
		updateSettings(array('tr_count_topics' => 30));
	
	trb_rating_ignoreboards();
	
	$txt['tr_count_topics'] = sprintf($txt['tr_count_topics'], $scripturl);
	
	$config_vars = array(
		array('check', 'tr_show_best_topic'),
		array('check', 'tr_mini_rating'),
		array('int', 'tr_count_topics'),
		array('title', 'tr_ignore_boards'),
		array('callback', 'tr_ignored_boards'),
		array('title', 'edit_permissions'),
		array('permissions', 'rate_topics'),
	);
	
	// Saving?
	if (isset($_GET['save'])) {
		if (empty($_POST['ignore_brd']))
			$_POST['ignore_brd'] = array();

		unset($_POST['ignore_boards']);
		if (isset($_POST['ignore_brd'])) {
			if (!is_array($_POST['ignore_brd']))
				$_POST['ignore_brd'] = array($_POST['ignore_brd']);

			foreach ($_POST['ignore_brd'] as $k => $d) {
				$d = (int) $d;
				if ($d != 0)
					$_POST['ignore_brd'][$k] = $d;
				else
					unset($_POST['ignore_brd'][$k]);
			}
			$_POST['ignore_boards'] = implode(',', $_POST['ignore_brd']);
			unset($_POST['ignore_brd']);
		}
		
		checkSession();
		saveDBSettings($config_vars);
		updateSettings(array('tr_ignore_boards' => $_POST['ignore_boards']));
		redirectexit('action=admin;area=modsettings;sa=topic_rating');
	}

   	prepareDBSettingContext($config_vars);
}

// Loading from rating_settings (see above)
function trb_rating_ignoreboards()
{
	global $txt, $user_info, $context, $modSettings, $smcFunc;

	$request = $smcFunc['db_query']('order_by_board_order', '
		SELECT b.id_cat, c.name AS cat_name, b.id_board, b.name, b.child_level,
			'. (!empty($modSettings['tr_ignore_boards']) ? 'b.id_board IN ({array_int:ignore_boards})' : '0') . ' AS is_ignored
		FROM {db_prefix}boards AS b
			LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
		WHERE redirect = {string:empty_string}' . (!empty($modSettings['recycle_board']) ? '
			AND b.id_board != {int:recycle_board}' : ''),
		array(
			'ignore_boards' => !empty($modSettings['tr_ignore_boards']) ? explode(',', $modSettings['tr_ignore_boards']) : array(),
			'recycle_board' => !empty($modSettings['recycle_board']) ? $modSettings['recycle_board'] : null,
			'empty_string'  => '',
		)
	);
	
	$context['num_boards'] = $smcFunc['db_num_rows']($request);
	$context['categories'] = array();
	
	while ($row = $smcFunc['db_fetch_assoc']($request))	{
		if (!isset($context['categories'][$row['id_cat']]))
			$context['categories'][$row['id_cat']] = array(
				'id'     => $row['id_cat'],
				'name'   => $row['cat_name'],
				'boards' => array(),
			);

		$context['categories'][$row['id_cat']]['boards'][$row['id_board']] = array(
			'id'          => $row['id_board'],
			'name'        => $row['name'],
			'child_level' => $row['child_level'],
			'selected'    => $row['is_ignored'],
		);
	}
	$smcFunc['db_free_result']($request);

	$temp_boards = array();
	foreach ($context['categories'] as $category) {
		$context['categories'][$category['id']]['child_ids'] = array_keys($category['boards']);

		$temp_boards[] = array(
			'name'      => $category['name'],
			'child_ids' => array_keys($category['boards']),
		);
		$temp_boards = array_merge($temp_boards, array_values($category['boards']));
	}

	$max_boards = ceil(count($temp_boards) / 2);
	if ($max_boards == 1)
		$max_boards = 2;

	$context['board_columns'] = array();
	for ($i = 0; $i < $max_boards; $i++) {
		$context['board_columns'][] = $temp_boards[$i];
		if (isset($temp_boards[$i + $max_boards]))
			$context['board_columns'][] = $temp_boards[$i + $max_boards];
		else
			$context['board_columns'][] = array();
	}
}

?>