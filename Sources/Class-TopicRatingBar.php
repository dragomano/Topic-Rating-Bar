<?php

/**
 * Class-TopicRatingBar.php
 *
 * @package Topic Rating Bar
 * @link https://custom.simplemachines.org/mods/index.php?mod=3236
 * @author Bugo https://dragomano.ru/mods/topic-rating-bar
 * @copyright 2010-2019 Bugo
 * @license https://opensource.org/licenses/artistic-license-2.0 Artistic License
 *
 * @version 1.2
 */

if (!defined('SMF'))
	die('Hacking attempt...');

class TopicRatingBar
{
	/**
	 * Подключаем используемые хуки
	 *
	 * @return void
	 */
	public static function hooks()
	{
		add_integration_function('integrate_load_theme', 'TopicRatingBar::loadTheme', false);
		add_integration_function('integrate_menu_buttons', 'TopicRatingBar::menuButtons', false);
		add_integration_function('integrate_actions', 'TopicRatingBar::actions', false);
		add_integration_function('integrate_load_permissions', 'TopicRatingBar::loadPermissions', false);
		add_integration_function('integrate_admin_areas', 'TopicRatingBar::adminAreas', false);
		add_integration_function('integrate_admin_search', 'TopicRatingBar::adminSearch', false);
		add_integration_function('integrate_modify_modifications', 'TopicRatingBar::modifyModifications', false);
	}

	/**
	 * Подключаем языковые строчки мода
	 *
	 * @return void
	 */
	public static function loadTheme()
	{
		global $context, $modSettings;

		loadLanguage('TopicRatingBar/');

		$context['trb_ignored_boards'] = [];

		if (!empty($modSettings['tr_ignored_boards']))
			$context['trb_ignored_boards'] = explode(",", $modSettings['tr_ignored_boards']);

		if (!empty($modSettings['recycle_board']))
			$context['trb_ignored_boards'][] = $modSettings['recycle_board'];

		if (!empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 2)
			clean_cache();
	}

	/**
	 * Осуществляем различные проверки и вызываем необходимые функции
	 *
	 * @return void
	 */
	public static function menuButtons()
	{
		global $context, $modSettings, $smcFunc, $settings, $board_info;

		if (isset($context['current_board']) && in_array($context['current_board'], $context['trb_ignored_boards']))
			return;

		if (empty($_REQUEST['board']) && empty($_REQUEST['topic']) && empty($_REQUEST['action']) || $context['current_action'] == 'forum') {
			self::getBestTopic();

			if (!empty($context['best_topic']))	{
				loadTemplate('TopicRatingBar');

				if (isset($context['template_layers'][2]) && $context['template_layers'][2] == 'portal') {
					$context['template_layers'][]  = 'portal';
					$context['template_layers'][2] = 'best_topics';
				} else
					$context['template_layers'][] = 'best_topics';
			}
		}

		if (!empty($context['current_board'])) {
			$context['rating_bar']   = [];
			$context['topic_rating'] = [];

			// Message Index
			if (!empty($modSettings['tr_mini_rating'])) {
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
					addInlineCss('
		.topic_stars_main {
			float: right;
			margin-right: 50px;
			margin-top: 10px;
		}
		.topic_stars {
			background-image: url(' . $settings['default_images_url'] . '/trb/one_star.png);
			background-repeat: no-repeat;
		}');

				self::showRatingOnMessageIndex();
			}

			// Display bar
			if (empty($context['current_action']) && empty($board_info['error']))
				self::showRatingBar();
		}
	}

	/**
	 * Отображаем панель со звёздочками внутри темы
	 *
	 * @param integer $unit_width ширина звёздочки
	 * @return void
	 */
	private static function showRatingBar($unit_width = 25)
	{
		global $smcFunc, $context, $modSettings, $topicinfo;

		if (empty($context['current_topic']) || empty($context['topicinfo']['id_member_started']))
			return;

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
		$users = empty($users) ? [] : unserialize($users);
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

		$context['proper_user'] = $context['topicinfo']['id_member_started'] !== $context['user']['id'] && allowedTo('rate_topics');

		loadTemplate('TopicRatingBar', 'trb_styles');
		$context['template_layers'][] = 'bar';
	}

	/**
	 * Добавляем отображение рейтинга тем внутри разделов
	 *
	 * @return void
	 */
	private static function showRatingOnMessageIndex()
	{
		global $context, $txt;

		if (empty($context['topic_rating']))
			return;

		$context['insert_after_template'] .= '
	<script>
		jQuery(document).ready(function($) {';

		foreach ($context['topic_rating'] as $topic => $data) {
			$rating = ($data['votes'] == 0) ? 0 : number_format($data['value'] / $data['votes'], 0);

			$img = '';
			for ($i = 0; $i < $rating; $i++)
				$img .= '<span class="topic_stars">&nbsp;&nbsp;&nbsp;</span>';

		$context['insert_after_template'] .= '
			var starImg' . $topic . ' = $("span#msg_' . $context['topics'][$topic]['first_post']['id'] . '");';

		$context['insert_after_template'] .= '
			starImg' . $topic . '.before(\'<span class="topic_stars_main" title="' . $txt['tr_average'] . ': ' . $rating . ' | ' . $txt['tr_votes'] . ': ' . $data['votes'] . '">' . $img . '</span>\');';
			}

		$context['insert_after_template'] .= '
		});
	</script>';
	}

	/**
	 * Добавляем свои actions
	 *
	 * @param array $actionArray
	 * @return void
	 */
	public static function actions(&$actionArray)
	{
		$actionArray['trb_rate'] = array('Class-TopicRatingBar.php', array('TopicRatingBar', 'ratingControl'));
		$actionArray['rating']   = array('Class-TopicRatingBar.php', array('TopicRatingBar', 'ratingTop'));
	}

	/**
	 * Обработка оценки
	 *
	 * @return void
	 */
	public static function ratingControl()
	{
		global $modSettings, $smcFunc, $context;

		$vote_sent   = (int) $_REQUEST['stars'];
		$topic       = (int) $_REQUEST['topic'];
		$user_id_num = (int) $_REQUEST['user'];
		$units       = empty($modSettings['tr_rate_system']) ? 5 : 10;

		if (empty($vote_sent) || empty($topic) || empty($user_id_num))
			exit;

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

		exit;
	}

	/**
	 * Отображение таблицы с популярными темами
	 *
	 * @return void
	 */
	public static function ratingTop()
	{
		global $context, $txt, $scripturl, $modSettings, $smcFunc;

		loadTemplate('TopicRatingBar', 'trb_styles');
		$context['sub_template']  = 'rating';
		$context['page_title']    = $txt['tr_top_stat'];
		$context['canonical_url'] = $scripturl . '?action=rating';

		$context['linktree'][] = array(
			'name' => $context['page_title'],
			'url'  => $context['canonical_url']
		);

		$limit = !empty($modSettings['tr_count_topics']) ? (int) $modSettings['tr_count_topics'] : 0;

		$query = $smcFunc['db_query']('', '
			SELECT tr.id, tr.total_votes, tr.total_value, ms.subject, b.id_board, b.name, m.id_member, m.id_group, m.real_name, mg.group_name
			FROM {db_prefix}topic_ratings AS tr
				LEFT JOIN {db_prefix}topics AS t ON (t.id_topic = tr.id)
				LEFT JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)
				LEFT JOIN {db_prefix}boards AS b ON (b.id_board = ms.id_board)
				LEFT JOIN {db_prefix}members AS m ON (m.id_member = t.id_member_started)
				LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = m.id_group)
			WHERE m.id_member != 0' . (empty($context['trb_ignored_boards']) ? '' : '
				AND b.id_board NOT IN ({array_int:ignore_boards})') . '
				AND {query_wanna_see_board}
				AND {query_see_board}
			ORDER BY tr.total_votes DESC, tr.total_value DESC
			LIMIT ' . $limit,
			array(
				'ignore_boards' => $context['trb_ignored_boards']
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

	/**
	 * Добавляем разрешение на оценивание тем
	 *
	 * @param array $permissionGroups
	 * @param array $permissionList
	 * @return void
	 */
	public static function loadPermissions(&$permissionGroups, &$permissionList)
	{
		global $context;

		$context['non_guest_permissions'][] = 'rate_topics';
		$permissionList['membergroup']['rate_topics'] = array(false, 'general', 'view_basic_info');
	}

	/**
	 * Получаем самую популярную тему форума
	 *
	 * @return void
	 */
	public static function getBestTopic()
	{
		global $modSettings, $smcFunc, $context, $scripturl, $txt;

		if (empty($modSettings['tr_show_best_topic']))
			return;

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
			WHERE m.id_member != 0' . (empty($context['trb_ignored_boards']) ? '' : '
				AND b.id_board NOT IN ({array_int:ignore_boards})') . '
				AND {query_wanna_see_board}
				AND {query_see_board}
				AND t.locked = 0
			ORDER BY tr.total_value DESC
			LIMIT 1',
			array(
				'ignore_boards' => $context['trb_ignored_boards']
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

	/**
	 * Заводим секцию для настроек мода в админке
	 *
	 * @param array $admin_areas
	 * @return void
	 */
	public static function adminAreas(&$admin_areas)
	{
		global $txt;

		$admin_areas['config']['areas']['modsettings']['subsections']['topic_rating'] = array($txt['tr_title']);
	}

	/**
	 * Легкий доступ к настройкам мода через быстрый поиск в админке
	 *
	 * @param array $language_files
	 * @param array $include_files
	 * @param array $settings_search
	 * @return void
	 */
	public static function adminSearch(&$language_files, &$include_files, &$settings_search)
	{
		$settings_search[] = array('TopicRatingBar::settings', 'area=modsettings;sa=topic_rating');
	}

	/**
	 * Подключаем функцию с настройками мода
	 *
	 * @param array $subActions
	 * @return void
	 */
	public static function modifyModifications(&$subActions)
	{
		$subActions['topic_rating'] = array('TopicRatingBar', 'settings');
	}

	/**
	 * Настройки мода
	 *
	 * @param boolean $return_config
	 * @return void
	 */
	public static function settings($return_config = false)
	{
		global $context, $txt, $scripturl, $modSettings;

		loadTemplate('TopicRatingBar');

		$context['page_title']           = $txt['tr_title'];
		$context['settings_title']       = $txt['settings'];
		$context['permissions_excluded'] = array(-1); // Прячем выбор разрешений на оценку тем гостями
		$context['post_url']             = $scripturl . '?action=admin;area=modsettings;save;sa=topic_rating';
		$context[$context['admin_menu_name']]['tab_data']['tabs']['topic_rating'] = array('description' => $txt['tr_desc']);

		if (!isset($modSettings['tr_count_topics']))
			updateSettings(array('tr_count_topics' => 30));

		$txt['tr_count_topics'] = sprintf($txt['tr_count_topics'], $scripturl);

		$config_vars = array(
			array('select', 'tr_rate_system', $txt['tr_system_array']),
			array('check', 'tr_show_best_topic'),
			array('check', 'tr_mini_rating'),
			array('int', 'tr_count_topics'),
			array('boards', 'tr_ignored_boards'),
			array('title', 'edit_permissions'),
			array('permissions', 'rate_topics')
		);

		if ($return_config)
			return $config_vars;

		// Saving?
		if (isset($_GET['save'])) {
			checkSession();
			$save_vars = $config_vars;
			saveDBSettings($save_vars);
			clean_cache();
			redirectexit('action=admin;area=modsettings;sa=topic_rating');
		}

		prepareDBSettingContext($config_vars);
	}
}
