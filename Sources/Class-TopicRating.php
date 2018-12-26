<?php

/**
 * Class-TopicRating.php
 *
 * @package Topic Rating Bar
 * @link https://custom.simplemachines.org/mods/index.php?mod=3236
 * @author Bugo https://dragomano.ru/mods/topic-rating-bar
 * @copyright 2010-2018 Bugo
 * @license https://opensource.org/licenses/artistic-license-2.0 Artistic License
 *
 * @version 1.1.1
 */

if (!defined('SMF'))
	die('Hacking attempt...');

define('TRB_CDN', '//cdn.jsdelivr.net/jquery/3/jquery.min.js');

class TopicRating
{
	public static function hooks()
	{
		add_integration_function('integrate_load_theme', 'TopicRating::loadTheme', false);
		add_integration_function('integrate_menu_buttons', 'TopicRating::ratingPreload', false);
		add_integration_function('integrate_actions', 'TopicRating::actions', false);
		add_integration_function('integrate_load_permissions', 'TopicRating::loadPermissions', false);
		add_integration_function('integrate_admin_areas', 'TopicRating::adminAreas', false);
		add_integration_function('integrate_modify_modifications', 'TopicRating::modifyModifications', false);
	}

	public static function loadTheme()
	{
		global $modSettings;

		loadLanguage('TopicRating/');

		if (!empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 2)
			clean_cache();
	}

	public static function ratingPreload()
	{
		global $context, $modSettings, $smcFunc, $board_info, $settings;

		if (empty($_REQUEST['board']) && empty($_REQUEST['topic']) && empty($_REQUEST['action']) && (defined('WIRELESS') && !WIRELESS) || $context['current_action'] == 'forum') {
			self::ratingBestTopic();

			if (!empty($context['best_topic']))	{
				loadTemplate('TopicRating');

				if (isset($context['template_layers'][2]) && $context['template_layers'][2] == 'portal') {
					$context['template_layers'][]  = 'portal';
					$context['template_layers'][2] = 'best_topics';
				} else {
					$context['template_layers'][] = 'best_topics';
				}
			}
		}

		if (!empty($context['current_board']) && (defined('WIRELESS') && !WIRELESS)) {
			$context['rating_bar']   = [];
			$context['topic_rating'] = [];
			$rating_ignore_boards    = [];

			if (!empty($modSettings['tr_ignore_boards']))
				$rating_ignore_boards = explode(",", $modSettings['tr_ignore_boards']);

			if (!empty($modSettings['recycle_board']))
				$rating_ignore_boards[] = $modSettings['recycle_board'];

			if (!in_array($context['current_board'], $rating_ignore_boards)) {
				// Message Index
				if (!empty($modSettings['tr_mini_rating']) && empty($context['current_topic'])) {
					if (empty($context['no_topic_listing']) && !isset($_REQUEST['action']))	{
						if (!empty($context['topics']))	{
							$topics = array_keys($context['topics']);

							$query = $smcFunc['db_query']('', '
								SELECT id, total_votes, total_value
								FROM {db_prefix}topic_ratings
								WHERE id IN ({array_int:topics})
								LIMIT ' . count($topics),
								array(
									'topics' => $topics
								)
							);

							while ($row = $smcFunc['db_fetch_assoc']($query)) {
								$context['topic_rating'][$row['id']] = array(
									'votes' => $row['total_votes'],
									'value' => $row['total_value']
								);
							}

							$smcFunc['db_free_result']($query);
						}
					}

					if (!empty($context['topic_rating']))
						$context['html_headers'] .= "\n\t" . '<style type="text/css">
		.topic_stars_main {
			float: right;
			margin-right: 50px;
			margin-top: 10px;
		}
		.topic_stars {
			background-image: url(' . $settings['default_images_url'] . '/trb/one_star.png);
			background-repeat: no-repeat;
		}
	</style>';

					self::ratingMessageIndex();
				}

				// Display bar
				if (!empty($context['current_topic']) && empty($context['current_action']) && empty($board_info['error']) && empty(strpos($_SERVER['REQUEST_URI'], ';'))) {
					loadTemplate(false, 'trb_styles');
					$context['rating_bar'] = [];
					self::ratingBar();
				}
			}
		}
	}

	private static function ratingBestTopic()
	{
		global $modSettings, $smcFunc, $context, $scripturl, $txt;

		if (empty($modSettings['tr_show_best_topic']))
			return;

		$ignore_boards = [];
		if (!empty($modSettings['tr_ignore_boards']))
			$ignore_boards = explode(",", $modSettings['tr_ignore_boards']);

		if (!empty($modSettings['recycle_board']))
			$ignore_boards[] = $modSettings['recycle_board'];

		$query = $smcFunc['db_query']('', '
			SELECT
				tr.id, tr.total_votes, tr.total_value, t.id_last_msg, t.num_replies, ms.subject, ms2.id_member,
				ms2.poster_time, ms2.subject AS last, IFNULL(m.real_name, 0) AS real_name
			FROM {db_prefix}topic_ratings AS tr
				LEFT JOIN {db_prefix}topics AS t ON (t.id_topic = tr.id)
				LEFT JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)
				LEFT JOIN {db_prefix}messages AS ms2 ON (ms2.id_msg = t.id_last_msg)
				LEFT JOIN {db_prefix}boards AS b ON (b.id_board = ms.id_board)
				LEFT JOIN {db_prefix}members AS m ON (m.id_member = ms2.id_member)
			WHERE m.id_member != 0' . (empty($ignore_boards) ? '' : '
				AND b.id_board NOT IN ({array_int:ignore_boards})') . '
				AND {query_wanna_see_board}
				AND {query_see_board}
				AND t.locked = 0
			ORDER BY tr.total_value DESC
			LIMIT 1',
			array(
				'ignore_boards' => $ignore_boards
			)
		);

		$context['best_topic'] = [];
		while ($row = $smcFunc['db_fetch_assoc']($query)) {
			$subject = shorten_subject($row['last'], 36);

			$context['best_topic'] = array(
				'topic'     => '<a href="' . $scripturl . '?topic=' . $row['id'] . '.0" class="subject">' . $row['subject'] . '</a>',
				'rating'    => number_format($row['total_value'] / $row['total_votes'], 2),
				'replies'   => $row['num_replies'] + 1,
				'time'      => $row['poster_time'] > 0 ? timeformat($row['poster_time']) : $txt['not_applicable'],
				'last_post' => '<a href="' . $scripturl . '?topic=' . $row['id'] . '.msg' . $row['id_last_msg'] . '#new" title="' . $row['last'] . '">' . $subject . '</a>',
				'member'    => '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '" target="_blank">' . $row['real_name'] . '</a>',
				'votes'     => $row['total_votes']
			);
		}

		$smcFunc['db_free_result']($query);
	}

	private static function ratingMessageIndex()
	{
		global $forum_version, $context, $txt, $settings;

		$version = substr($forum_version, 6, 1);

		if (!empty($context['topic_rating'])) {
			if (empty($version)) {
				$cdn = '//cdn.jsdelivr.net/g/jquery@3,jquery.migrate@1';

				$context['insert_after_template'] .= '
	<script type="text/javascript">window.jQuery || document.write(unescape(\'%3Cscript src="' . $cdn . '"%3E%3C/script%3E\'))</script>
	<script type="text/javascript"><!-- // --><![CDATA[
		$star = jQuery.noConflict();
		$star(document).ready(function($){';
			} else {
				$context['insert_after_template'] .= '
	<script type="text/javascript"><!-- // --><![CDATA[
		jQuery(document).ready(function($){';
			}

			foreach ($context['topic_rating'] as $topic => $data) {
				$rating = ($data['votes'] == 0) ? 0 : number_format($data['value'] / $data['votes'], 0);

				$img = '';
				for ($i = 0; $i < $rating; $i++)
					$img .= '<span class="topic_stars">&nbsp;&nbsp;&nbsp;</span>';

				if (empty($version))
					$context['insert_after_template'] .= '
			var starImg' . $topic . ' = $star("span#msg_' . $context['topics'][$topic]['first_post']['id'] . '");';
				else
					$context['insert_after_template'] .= '
			var starImg' . $topic . ' = $("span#msg_' . $context['topics'][$topic]['first_post']['id'] . '");';

				$context['insert_after_template'] .= '
			starImg' . $topic . '.before(\'<span class="topic_stars_main" title="' . $txt['tr_average'] . ': ' . $rating . ' | ' . $txt['tr_votes'] . ': ' . $data['votes'] . '">' . $img . '</span>\');';
			}

			$context['insert_after_template'] .= '
		});
	// ]]></script>';
		}
	}

	private static function ratingBar($unit_width = 25)
	{
		global $smcFunc, $context, $modSettings, $topicinfo;

		$query = $smcFunc['db_query']('', '
			SELECT total_votes, total_value, user_ids
			FROM {db_prefix}topic_ratings
			WHERE id = {int:topic_id}
			LIMIT 1',
			array(
				'topic_id' => $context['current_topic']
			)
		);

		list ($count, $current_rating, $users) = $smcFunc['db_fetch_row']($query);

		$smcFunc['db_free_result']($query);

		$rating = ($count == 0) ? 0 : number_format($current_rating / $count, 0);
		$rating_width = $rating * $unit_width;
		$users = @unserialize($users);
		$voted = empty($users) ? false : in_array($context['user']['id'], $users);

		$context['rating_bar'] = array(
			'current'      => $rating,
			'rating_width' => $rating_width,
			'units'        => empty($modSettings['tr_rate_system']) ? 5 : 10,
			'unit_width'   => $unit_width,
			'users'        => $users,
			'voted'        => $voted
		);

		if (empty($context['rating_bar']) || empty($context['subject']))
			return;

		$context['proper_user'] = $topicinfo['id_member_started'] != $context['user']['id'] && allowedTo('rate_topics');

		loadTemplate('TopicRating');
		$context['template_layers'][] = 'bar';
	}

	public static function actions(&$actionArray)
	{
		$actionArray['trb_rate'] = array('Class-TopicRating.php', array('TopicRating', 'ratingControl'));
		$actionArray['rating']   = array('Class-TopicRating.php', array('TopicRating', 'ratingTop'));
	}

	public static function ratingControl()
	{
		global $modSettings, $smcFunc, $context;

		$vote_sent   = (int) $_REQUEST['stars'];
		$topic       = (int) $_REQUEST['topic'];
		$user_id_num = (int) $_REQUEST['user'];
		$units       = empty($modSettings['tr_rate_system']) ? 5 : 10;

		if (empty($vote_sent) || empty($topic) || empty($user_id_num))
			exit();

		$query = $smcFunc['db_query']('', '
			SELECT total_votes, total_value, user_ids
			FROM {db_prefix}topic_ratings
			WHERE id = {int:topic}
			LIMIT 1',
			array(
				'topic' => $topic
			)
		);

		$numbers = $smcFunc['db_fetch_assoc']($query);

		$smcFunc['db_free_result']($query);

		$check_user_id  = @unserialize($numbers['user_ids']);
		$current_rating = $numbers['total_value'];

		$voted = empty($check_user_id) ? false : in_array($user_id_num, $check_user_id);
		$count = $numbers['total_votes'];
		$total = $vote_sent + $current_rating;
		$votes = $total == 0 ? 0 : $count + 1;

		if (is_array($check_user_id))
			array_push($check_user_id, $user_id_num);
		else
			$check_user_id = array($user_id_num);

		$users = serialize($check_user_id);

		if (!$voted) {
			if (($vote_sent >= 1 && $vote_sent <= $units) && ($context['user']['id'] == $user_id_num)) {
				$result = $smcFunc['db_insert']('replace',
					'{db_prefix}topic_ratings',
					array(
						'id'          => 'int',
						'total_votes' => 'int',
						'total_value' => 'int',
						'user_ids'    => 'string'
					),
					array(
						$topic,
						$votes,
						$total,
						$users
					),
					array('id')
				);
			}
		}

		exit();
	}

	public static function ratingTop()
	{
		global $context, $txt, $scripturl, $modSettings, $smcFunc;

		loadTemplate('TopicRating', 'trb_styles');

		$context['sub_template']  = 'rating';
		$context['page_title']    = $txt['tr_top_stat'];
		$context['canonical_url'] = $scripturl . '?action=rating';

		$context['linktree'][] = array(
			'name' => $context['page_title'],
			'url'  => $context['canonical_url']
		);

		$limit = !empty($modSettings['tr_count_topics']) ? (int) $modSettings['tr_count_topics'] : 0;

		$ignore_boards = [];
		if (!empty($modSettings['tr_ignore_boards']))
			$ignore_boards = explode(",", $modSettings['tr_ignore_boards']);

		if (!empty($modSettings['recycle_board']))
			$ignore_boards[] = $modSettings['recycle_board'];

		$query = $smcFunc['db_query']('', '
			SELECT tr.id, tr.total_votes, tr.total_value, ms.subject, b.id_board, b.name, m.id_member, m.id_group, m.real_name, mg.group_name
			FROM {db_prefix}topic_ratings AS tr
				LEFT JOIN {db_prefix}topics AS t ON (t.id_topic = tr.id)
				LEFT JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)
				LEFT JOIN {db_prefix}boards AS b ON (b.id_board = ms.id_board)
				LEFT JOIN {db_prefix}members AS m ON (m.id_member = t.id_member_started)
				LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = m.id_group)
			WHERE m.id_member != 0' . (empty($ignore_boards) ? '' : '
				AND b.id_board NOT IN ({array_int:ignore_boards})') . '
				AND {query_wanna_see_board}
				AND {query_see_board}
			ORDER BY tr.total_votes DESC, tr.total_value DESC
			LIMIT ' . $limit,
			array(
				'ignore_boards' => $ignore_boards
			)
		);

		$context['top_rating'] = [];
		while ($row = $smcFunc['db_fetch_assoc']($query))
			$context['top_rating'][$row['id']] = array(
				'topic'  => '<a href="' . $scripturl . '?topic=' . $row['id'] . '.0" target="_blank">' . $row['subject'] . '</a>',
				'board'  => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0" target="_blank">' . $row['name'] . '</a>',
				'author' => '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '" target="_blank">' . $row['real_name'] . '</a>',
				'group'  => empty($row['id_group']) ? $txt['tr_regular_members'] : $row['group_name'],
				'rating' => number_format($row['total_value'] / $row['total_votes'], 2),
				'votes'  => $row['total_votes']
			);

		$smcFunc['db_free_result']($query);
	}

	public static function loadPermissions(&$permissionGroups, &$permissionList)
	{
		global $context;

		$context['non_guest_permissions'][] = 'rate_topics';
		$permissionList['membergroup']['rate_topics'] = array(false, 'general', 'view_basic_info');
	}

	public static function adminAreas(&$admin_areas)
	{
		global $txt;

		$admin_areas['config']['areas']['modsettings']['subsections']['topic_rating'] = array($txt['tr_title']);
	}

	public static function modifyModifications(&$subActions)
	{
		$subActions['topic_rating'] = array('TopicRating', 'settings');
	}

	public static function settings()
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

		self::ignoreBoards();

		$txt['tr_count_topics'] = sprintf($txt['tr_count_topics'], $scripturl);

		$config_vars = array(
			array('select', 'tr_rate_system', $txt['tr_system_array']),
			array('check', 'tr_show_best_topic'),
			array('check', 'tr_mini_rating'),
			array('int', 'tr_count_topics'),
			array('title', 'tr_ignore_boards'),
			array('callback', 'tr_ignored_boards'),
			array('title', 'edit_permissions'),
			array('permissions', 'rate_topics')
		);

		// Saving?
		if (isset($_GET['save'])) {
			checkSession();

			if (empty($_POST['ignore_brd']))
				$_POST['ignore_brd'] = [];

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

			saveDBSettings($config_vars);
			updateSettings(array('tr_ignore_boards' => $_POST['ignore_boards']));
			clean_cache();
			redirectexit('action=admin;area=modsettings;sa=topic_rating');
		}

		   prepareDBSettingContext($config_vars);
	}

	private static function ignoreBoards()
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
				'ignore_boards' => !empty($modSettings['tr_ignore_boards']) ? explode(',', $modSettings['tr_ignore_boards']) : [],
				'recycle_board' => !empty($modSettings['recycle_board']) ? $modSettings['recycle_board'] : null,
				'empty_string'  => ''
			)
		);

		$context['num_boards'] = $smcFunc['db_num_rows']($request);
		$context['categories'] = [];

		while ($row = $smcFunc['db_fetch_assoc']($request))	{
			if (!isset($context['categories'][$row['id_cat']]))
				$context['categories'][$row['id_cat']] = array(
					'id'     => $row['id_cat'],
					'name'   => $row['cat_name'],
					'boards' => []
				);

			$context['categories'][$row['id_cat']]['boards'][$row['id_board']] = array(
				'id'          => $row['id_board'],
				'name'        => $row['name'],
				'child_level' => $row['child_level'],
				'selected'    => $row['is_ignored']
			);
		}
		$smcFunc['db_free_result']($request);

		$temp_boards = [];
		foreach ($context['categories'] as $category) {
			$context['categories'][$category['id']]['child_ids'] = array_keys($category['boards']);

			$temp_boards[] = array(
				'name'      => $category['name'],
				'child_ids' => array_keys($category['boards'])
			);
			$temp_boards = array_merge($temp_boards, array_values($category['boards']));
		}

		$max_boards = ceil(count($temp_boards) / 2);
		if ($max_boards == 1)
			$max_boards = 2;

		$context['board_columns'] = [];
		for ($i = 0; $i < $max_boards; $i++) {
			$context['board_columns'][] = $temp_boards[$i];
			if (isset($temp_boards[$i + $max_boards]))
				$context['board_columns'][] = $temp_boards[$i + $max_boards];
			else
				$context['board_columns'][] = [];
		}
	}
}
