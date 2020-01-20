<?php

/**
 * Class-TopicRatingBar.php
 *
 * @package Topic Rating Bar
 * @link https://custom.simplemachines.org/mods/index.php?mod=3236
 * @author Bugo https://dragomano.ru/mods/topic-rating-bar
 * @copyright 2011-2020 Bugo
 * @license https://opensource.org/licenses/BSD-3-Clause BSD
 *
 * @version 1.6
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
		add_integration_function('integrate_load_theme', __CLASS__ . '::loadTheme', false, __FILE__);
		add_integration_function('integrate_menu_buttons', __CLASS__ . '::menuButtons', false, __FILE__);
		add_integration_function('integrate_actions', __CLASS__ . '::actions', false, __FILE__);
		add_integration_function('integrate_load_illegal_guest_permissions', __CLASS__ . '::loadIllegalGuestPermissions', false, __FILE__);
		add_integration_function('integrate_load_permissions', __CLASS__ . '::loadPermissions', false, __FILE__);
		add_integration_function('integrate_remove_topics', __CLASS__ . '::removeTopics', false, __FILE__);
		add_integration_function('integrate_message_index', __CLASS__ . '::messageIndex', false, __FILE__);
		add_integration_function('integrate_messageindex_buttons', __CLASS__ . '::showRatingOnMessageIndex', false, __FILE__);
		add_integration_function('integrate_admin_areas', __CLASS__ . '::adminAreas', false, __FILE__);
		add_integration_function('integrate_admin_search', __CLASS__ . '::adminSearch', false, __FILE__);
		add_integration_function('integrate_modify_modifications', __CLASS__ . '::modifyModifications', false, __FILE__);
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
		global $context;

		if (!empty($context['current_board']) && !empty($context['trb_ignored_boards']) && in_array($context['current_board'], $context['trb_ignored_boards']))
			return;

		// Отображение блока с рейтинговыми темами на главной странице форума
		if (empty($_REQUEST['board']) && empty($_REQUEST['topic']) && empty($_REQUEST['action'])) {
			self::getBestTopic();

			if (!empty($context['best_topic']))	{
				loadTemplate('TopicRatingBar');

				// Убедимся, что наш блок будет выше блоков портала
				if (isset($context['template_layers'][2]) && $context['template_layers'][2] == 'portal') {
					$context['template_layers'][]  = 'portal';
					$context['template_layers'][2] = 'best_topics';
				} else
					$context['template_layers'][] = 'best_topics';
			}
		}

		self::showRatingBar();
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
				tr.id, tr.total_votes, tr.total_value, t.id_last_msg, t.num_replies, mf.subject, ml.id_member,
				ml.poster_time, ml.subject AS last, COALESCE(mem.real_name, 0) AS real_name
			FROM {db_prefix}topic_ratings AS tr
				LEFT JOIN {db_prefix}topics AS t ON (t.id_topic = tr.id)
				LEFT JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
				LEFT JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
				LEFT JOIN {db_prefix}boards AS b ON (b.id_board = mf.id_board)
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = ml.id_member)
			WHERE t.id_member_started != 0' . (empty($context['trb_ignored_boards']) ? '' : '
				AND b.id_board NOT IN ({array_int:ignore_boards})') . '
				AND {query_wanna_see_board}
				AND t.locked = 0
			ORDER BY tr.total_value DESC
			LIMIT 1',
			array(
				'ignore_boards' => $context['trb_ignored_boards']
			)
		);

		$context['best_topic'] = [];
		while ($row = $smcFunc['db_fetch_assoc']($query)) {
			$subject   = shorten_subject($row['subject'], 50);
			$last_post = shorten_subject($row['last'], 36);

			$context['best_topic'] = array(
				'topic'     => '<a href="' . $scripturl . '?topic=' . $row['id'] . '.0" class="subject" title="' . $row['subject'] . '">' . $subject . '</a>',
				'rating'    => number_format($row['total_value'] / $row['total_votes'], 2),
				'replies'   => $row['num_replies'] + 1,
				'time'      => $row['poster_time'] > 0 ? timeformat($row['poster_time']) : $txt['not_applicable'],
				'last_post' => '<a href="' . $scripturl . '?topic=' . $row['id'] . '.msg' . $row['id_last_msg'] . '#new" title="' . $row['last'] . '">' . $last_post . '</a>',
				'member'    => '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>',
				'votes'     => $row['total_votes']
			);
		}

		$smcFunc['db_free_result']($query);
	}

	/**
	 * Добавляем отображение рейтинга тем внутри разделов
	 *
	 * @return void
	 */
	public static function showRatingOnMessageIndex()
	{
		global $context, $txt;

		if (empty($context['topics']))
			return;

		loadCssFile('trb_styles.css');

		$context['insert_after_template'] .= '
		<script>
			jQuery(document).ready(function($) {';

			foreach ($context['topics'] as $topic => $data) {
				$rating = ($data['tr_votes'] == 0) ? 0 : number_format($data['tr_value'] / $data['tr_votes'], 0);

				if (empty($rating))
					continue;

				$img = '';
				for ($i = 0; $i < $rating; $i++)
					$img .= '<span class="topic_stars">&nbsp;&nbsp;&nbsp;</span>';

				$context['insert_after_template'] .= '
				let starImg' . $topic . ' = $("span#msg_' . $context['topics'][$topic]['first_post']['id'] . '");
				starImg' . $topic . '.before(\'<span class="topic_stars_main" title="' . $txt['tr_average'] . ': ' . $rating . ' | ' . $txt['tr_votes'] . ': ' . $data['tr_votes'] . '">' . $img . '</span>\');';
			}

			$context['insert_after_template'] .= '
			});
		</script>';
	}

	/**
	 * Отображаем панель со звёздочками внутри темы
	 *
	 * @param integer $unit_width ширина звёздочки
	 * @return void
	 */
	private static function showRatingBar($unit_width = 25)
	{
		global $board_info, $smcFunc, $context, $modSettings;

		if (!empty($board_info['error']) || empty($context['current_topic']) || empty($context['topicinfo']['id_member_started']))
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

		$context['proper_user'] = $context['topicinfo']['id_member_started'] != $context['user']['id'] && allowedTo('rate_topics');

		loadTemplate('TopicRatingBar', 'trb_styles');
		$context['template_layers'][] = 'bar';
	}

	/**
	 * Добавляем свои actions
	 *
	 * @param array $actionArray
	 * @return void
	 */
	public static function actions(&$actionArray)
	{
		$actionArray['trb_rate'] = array('Class-TopicRatingBar.php', array(__CLASS__, 'ratingControl'));
		$actionArray['rating']   = array('Class-TopicRatingBar.php', array(__CLASS__, 'ratingTop'));
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

		$query = $smcFunc['db_query']('', '
			SELECT tr.id, tr.total_votes, tr.total_value, m.subject, b.id_board, b.name, mem.id_member, mem.id_group, mem.real_name, mg.group_name
			FROM {db_prefix}topic_ratings AS tr
				LEFT JOIN {db_prefix}topics AS t ON (t.id_topic = tr.id)
				LEFT JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
				LEFT JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = t.id_member_started)
				LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = mem.id_group)
			WHERE m.id_member != 0' . (empty($context['trb_ignored_boards']) ? '' : '
				AND b.id_board NOT IN ({array_int:ignore_boards})') . '
				AND {query_wanna_see_board}
				AND {query_wanna_see_message_board}
			ORDER BY tr.total_votes DESC, tr.total_value DESC
			LIMIT {int:limit}',
			array(
				'ignore_boards' => $context['trb_ignored_boards'],
				'limit'         => !empty($modSettings['tr_count_topics']) ? (int) $modSettings['tr_count_topics'] : 0
			)
		);

		$context['top_rating'] = [];
		while ($row = $smcFunc['db_fetch_assoc']($query))
			$context['top_rating'][$row['id']] = array(
				'topic'  => '<a href="' . $scripturl . '?topic=' . $row['id'] . '.0">' . $row['subject'] . '</a>',
				'board'  => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['name'] . '</a>',
				'author' => '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>',
				'group'  => empty($row['id_group']) ? $txt['tr_regular_members'] : $row['group_name'],
				'rating' => number_format($row['total_value'] / $row['total_votes'], 2),
				'votes'  => $row['total_votes']
			);

		$smcFunc['db_free_result']($query);
	}

	/**
	 * Прячем право на оценку тем для гостей
	 *
	 * @return void
	 */
	public static function loadIllegalGuestPermissions()
	{
		global $context;

		$context['non_guest_permissions'][] = 'rate_topics';
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
		$permissionList['membergroup']['rate_topics'] = array(false, 'general', 'view_basic_info');
	}

	/**
	 * Удаляем оценки при удалении темы
	 *
	 * @param array $topics
	 * @return void
	 */
	public static function removeTopics($topics)
	{
		global $smcFunc;

		if (empty($topics))
			return;

		$request = $smcFunc['db_query']('', '
			DELETE FROM {db_prefix}topic_ratings
			WHERE id IN ({array_int:topics})',
			array(
				'topics' => $topics
			)
		);
	}

	/**
	 * Добавляем выборку значений рейтинга при просмотре тем внутри разделов
	 *
	 * @param array $message_index_selects
	 * @param array $message_index_tables
	 * @return void
	 */
	public static function messageIndex(&$message_index_selects, &$message_index_tables)
	{
		global $modSettings;

		if (empty($modSettings['tr_mini_rating']))
			return;

		$message_index_selects[] = 'total_votes AS tr_votes, total_value AS tr_value';
		$message_index_tables[]  = 'LEFT JOIN {db_prefix}topic_ratings AS tr ON (tr.id = t.id_topic)';
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
		$settings_search[] = array(array(__CLASS__, 'settings'), 'area=modsettings;sa=topic_rating');
	}

	/**
	 * Подключаем функцию с настройками мода
	 *
	 * @param array $subActions
	 * @return void
	 */
	public static function modifyModifications(&$subActions)
	{
		$subActions['topic_rating'] = array(__CLASS__, 'settings');
	}

	/**
	 * Настройки мода
	 *
	 * @param boolean $return_config
	 *
	* @return array
	*/
	public static function settings($return_config = false)
	{
		global $context, $txt, $scripturl, $modSettings;

		$context['page_title']     = $txt['tr_title'];
		$context['settings_title'] = $txt['settings'];

		$context['post_url'] = $scripturl . '?action=admin;area=modsettings;save;sa=topic_rating';
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
