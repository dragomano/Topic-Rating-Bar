<?php

/**
 * @package Topic Rating Bar
 * @link https://custom.simplemachines.org/mods/index.php?mod=3236
 * @author Bugo https://dragomano.ru/mods/topic-rating-bar
 * @copyright 2011-2026 Bugo
 * @license https://opensource.org/licenses/BSD-3-Clause BSD
 */

if (! defined('SMF'))
	die('No direct access...');

final class TopicRatingBar
{
	public const UNIT_WIDTH = 25;

	public function hooks(): void
	{
		add_integration_function('integrate_load_theme', self::class . '::loadTheme#', false);
		add_integration_function('integrate_menu_buttons', self::class . '::showBestTopic#', false);
		add_integration_function('integrate_menu_buttons', self::class . '::showRatingBar#', false);
		add_integration_function('integrate_actions', self::class . '::actions#', false);
		add_integration_function('integrate_load_illegal_guest_permissions', self::class . '::loadIllegalGuestPermissions#', false);
		add_integration_function('integrate_load_permissions', self::class . '::loadPermissions#', false);
		add_integration_function('integrate_remove_topics', self::class . '::removeTopics#', false);
		add_integration_function('integrate_message_index', self::class . '::messageIndex#', false);
		add_integration_function('integrate_messageindex_buttons', self::class . '::showRatingOnMessageIndex#', false);
		add_integration_function('integrate_admin_areas', self::class . '::adminAreas#', false);
		add_integration_function('integrate_admin_search', self::class . '::adminSearch#', false);
		add_integration_function('integrate_modify_modifications', self::class . '::modifyModifications#', false);
		add_integration_function('integrate_credits', self::class . '::credits#', false);
	}

	public function loadTheme(): void
	{
		global $context, $modSettings;

		loadLanguage('TopicRatingBar/');

		$context['trb_ignored_boards'] = [];
		if (! empty($modSettings['tr_ignored_boards'])) {
			$context['trb_ignored_boards'] = explode(',', $modSettings['tr_ignored_boards']);
		}

		if (! empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 2) {
			clean_cache();
		}
	}

	public function actions(array &$actionArray): void
	{
		$actionArray['trb_rate'] = [false, [$this, 'ratingControl']];
		$actionArray['rating']   = [false, [$this, 'ratingTop']];
	}

	public function loadIllegalGuestPermissions(): void
	{
		global $context;

		$context['non_guest_permissions'][] = 'rate_topics';
	}

	public function loadPermissions(array &$permissionGroups, array &$permissionList): void
	{
		$permissionList['membergroup']['rate_topics'] = [false, 'general', 'view_basic_info'];
	}

	public function removeTopics(array $topics): void
	{
		global $smcFunc;

		if (empty($topics))
			return;

		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}topic_ratings
			WHERE id IN ({array_int:topics})',
			[
				'topics' => $topics,
			],
		);
	}

	public function messageIndex(array &$message_index_selects, array &$message_index_tables): void
	{
		global $modSettings;

		if (empty($modSettings['tr_mini_rating']))
			return;

		$message_index_selects[] = 'total_votes AS tr_votes, total_value AS tr_value';
		$message_index_tables[]  = 'LEFT JOIN {db_prefix}topic_ratings AS tr ON (tr.id = t.id_topic)';
	}

	public function adminAreas(array &$admin_areas): void
	{
		global $txt;

		$admin_areas['config']['areas']['modsettings']['subsections']['topic_rating'] = [$txt['tr_title']];
	}

	public function adminSearch(array &$language_files, array &$include_files, array &$settings_search): void
	{
		$settings_search[] = [[$this, 'settings'], 'area=modsettings;sa=topic_rating'];
	}

	public function modifyModifications(array &$subActions): void
	{
		$subActions['topic_rating'] = [$this, 'settings'];
	}

	public function credits(): void
	{
		global $txt, $context;

		$link = $txt['lang_dictionary'] === 'ru' ? 'https://dragomano.ru/mods/topic-rating-bar' : 'https://custom.simplemachines.org/mods/index.php?mod=3236';

		$context['credits_modifications'][] = '<a href="' . $link . '" target="_blank" rel="noopener">Topic Rating Bar</a> &copy; 2011&ndash;' . date('Y') . ', Bugo';
	}

	/**
	 * @param bool $return_config
	 * @return array|void
	 */
	public function settings(bool $return_config = false)
	{
		global $context, $txt, $scripturl, $modSettings;

		$context['page_title']     = $txt['tr_title'];
		$context['settings_title'] = $txt['settings'];
		$context['post_url']       = $scripturl . '?action=admin;area=modsettings;save;sa=topic_rating';

		if (! isset($modSettings['tr_count_topics'])) {
			updateSettings(['tr_count_topics' => 30]);
		}

		$txt['tr_count_topics'] = sprintf($txt['tr_count_topics'], $scripturl);

		$config_vars = [
			['select', 'tr_rate_system', $txt['tr_system_array']],
			['check', 'tr_show_best_topic'],
			['check', 'tr_mini_rating'],
			['int', 'tr_count_topics'],
			['boards', 'tr_ignored_boards'],
			['title', 'edit_permissions'],
			['permissions', 'rate_topics'],
		];

		if ($return_config) {
			return $config_vars;
		}

		$context[$context['admin_menu_name']]['tab_data']['description'] = $txt['tr_desc'];

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

	/**
	 * @deprecated
	 */
	public static function getBestTopic(): void
	{
		self::prepareBestTopic();
	}

	public static function prepareBestTopic(): void
	{
		global $smcFunc, $context, $scripturl, $txt;

		$query = $smcFunc['db_query']('', /** @lang text */ '
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
			[
				'ignore_boards' => $context['trb_ignored_boards'],
			],
		);

		$context['best_topic'] = [];
		while ($row = $smcFunc['db_fetch_assoc']($query)) {
			$subject  = shorten_subject($row['subject'], 50);
			$lastPost = shorten_subject($row['last'], 36);

			$context['best_topic'] = [
				'topic'     => '<a href="' . $scripturl . '?topic=' . $row['id'] . '.0" class="subject" title="' . $row['subject'] . '">' . $subject . '</a>',
				'rating'    => number_format($row['total_value'] / $row['total_votes'], 2),
				'replies'   => $row['num_replies'] + 1,
				'time'      => $row['poster_time'] > 0 ? timeformat($row['poster_time']) : $txt['not_applicable'],
				'last_post' => '<a href="' . $scripturl . '?topic=' . $row['id'] . '.msg' . $row['id_last_msg'] . '#new" title="' . $row['last'] . '">' . $lastPost . '</a>',
				'member'    => '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>',
				'votes'     => $row['total_votes'],
			];
		}

		$smcFunc['db_free_result']($query);
	}

	/**
	 * Adds the topic rating display to the message index
	 *
	 * @hook integrate_messageindex_buttons
	 */
	public function showRatingOnMessageIndex(): void
	{
		global $modSettings, $context, $txt;

		if (empty($modSettings['tr_mini_rating']) || empty($context['topics']))
			return;

		loadCSSFile('trb_styles.css');

		$context['insert_after_template'] .= '
		<script>
			document.addEventListener("DOMContentLoaded", function() {';

		foreach ($context['topics'] as $topic => $data) {
			$rating = empty($data['tr_votes']) ? 0 : number_format($data['tr_value'] / $data['tr_votes']);

			if (empty($rating))
				continue;

			$img = str_repeat('<span class="topic_stars">&nbsp;&nbsp;&nbsp;</span>', $rating);

			$context['insert_after_template'] .= '
				let starImg' . $topic . ' = document.querySelector("span#msg_' . $context['topics'][$topic]['first_post']['id'] . '");
				starImg' . $topic . '.insertAdjacentHTML("beforebegin", \'<span class="topic_stars_main" title="' . $txt['tr_average'] . ': ' . $rating . ' | ' . $txt['tr_votes'] . ': ' . $data['tr_votes'] . '">' . $img . '</span>\');';
		}

		$context['insert_after_template'] .= '
			});
		</script>';
	}

	/**
	 * Handles a rating submission
	 *
	 * Accepts POST only and requires a session token. Returns JSON for a fetch request,
	 * and redirects back to the topic for a regular form submission (works without JavaScript).
	 */
	public function ratingControl(): void
	{
		global $modSettings, $context, $smcFunc;

		$units  = empty($modSettings['tr_rate_system']) ? 5 : 10;
		$isAjax = ! empty($_SERVER['HTTP_X_REQUESTED_WITH']) || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

		$topic    = (int) ($_POST['topic'] ?? 0);
		$voteSent = (int) ($_POST['stars'] ?? 0);
		$userId   = (int) $context['user']['id'];

		if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
			if ($isAjax) {
				$this->ratingJson(405, ['success' => false, 'error' => 'method_not_allowed']);
			}

			redirectexit();
		}

		// With $is_fatal = false, checkSession() returns an error string, or an empty string on success
		if (checkSession('post', '', false) !== '') {
			// Without JavaScript, let SMF display its own session error page
			if (! $isAjax) {
				checkSession('post');
			}

			$this->ratingJson(403, ['success' => false, 'error' => 'session']);
		}

		if ($userId === 0) {
			if (! $isAjax && $topic > 0) {
				redirectexit('topic=' . $topic . '.0');
			}

			$this->ratingJson(403, ['success' => false, 'error' => 'not_logged_in']);
		}

		if ($topic === 0 || $voteSent < 1 || $voteSent > $units) {
			if (! $isAjax && $topic > 0) {
				redirectexit('topic=' . $topic . '.0');
			}

			$this->ratingJson(400, ['success' => false, 'error' => 'invalid_request']);
		}

		// Rating permission and restrictions that used to be checked only when rendering the form
		if (! allowedTo('rate_topics')) {
			if (! $isAjax) {
				redirectexit('topic=' . $topic . '.0');
			}

			$this->ratingJson(403, ['success' => false, 'error' => 'no_permission']);
		}

		$query = $smcFunc['db_query']('', '
			SELECT t.id_member_started, mf.id_board
			FROM {db_prefix}topics AS t
				LEFT JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
			WHERE t.id_topic = {int:topic}
			LIMIT 1',
			[
				'topic' => $topic,
			],
		);

		$topicRow = $smcFunc['db_fetch_assoc']($query);

		$smcFunc['db_free_result']($query);

		if (empty($topicRow)) {
			if (! $isAjax) {
				redirectexit();
			}

			$this->ratingJson(400, ['success' => false, 'error' => 'invalid_request']);
		}

		// Users cannot rate their own topic — the same condition as in the template
		if ((int) $topicRow['id_member_started'] === $userId) {
			if (! $isAjax) {
				redirectexit('topic=' . $topic . '.0');
			}

			$this->ratingJson(403, ['success' => false, 'error' => 'own_topic']);
		}

		$ignoredBoards = array_map('intval', $context['trb_ignored_boards'] ?? []);

		if (in_array((int) $topicRow['id_board'], $ignoredBoards, true)) {
			if (! $isAjax) {
				redirectexit('topic=' . $topic . '.0');
			}

			$this->ratingJson(400, ['success' => false, 'error' => 'board_not_allowed']);
		}

		$ratingData     = $this->getTopicRatingData($topic);
		$checkedUserIds = $this->getParsedUserIds($ratingData['user_ids']);

		if (in_array($userId, $checkedUserIds, true)) {
			if (! $isAjax) {
				redirectexit('topic=' . $topic . '.0');
			}

			$this->ratingJson(
				409,
				['success' => false, 'error' => 'already_voted'] + $this->ratingPayload($ratingData['total_votes'], $ratingData['total_value'], $units),
			);
		}

		$votes = $ratingData['total_votes'] + 1;
		$total = $ratingData['total_value'] + $voteSent;

		$checkedUserIds[] = $userId;

		$smcFunc['db_insert']('replace',
			'{db_prefix}topic_ratings',
			[
				'id'          => 'int',
				'total_votes' => 'int',
				'total_value' => 'int',
				'user_ids'    => 'string',
			],
			[
				$topic,
				$votes,
				$total,
				json_encode($checkedUserIds),
			],
			['id'],
		);

		if (! $isAjax) {
			redirectexit('topic=' . $topic . '.0');
		}

		$this->ratingJson(200, ['success' => true] + $this->ratingPayload($votes, $total, $units));
	}

	/**
	 * Prepares the data for the top rated topics table
	 */
	public function ratingTop(): void
	{
		global $context, $txt, $scripturl, $modSettings, $smcFunc;

		loadTemplate('TopicRatingBar', 'trb_styles');

		$context['sub_template']  = 'rating';
		$context['page_title']    = $txt['tr_top_stat'];
		$context['canonical_url'] = $scripturl . '?action=rating';

		$context['linktree'][] = [
			'name' => $context['page_title'],
			'url'  => $context['canonical_url'],
		];

		$query = $smcFunc['db_query']('', /** @lang text */ '
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
			ORDER BY tr.total_votes DESC, tr.total_value DESC
			LIMIT {int:limit}',
			[
				'ignore_boards' => $context['trb_ignored_boards'],
				'limit'         => empty($modSettings['tr_count_topics']) ? 0 : (int) $modSettings['tr_count_topics'],
			],
		);

		$context['top_rating'] = [];
		while ($row = $smcFunc['db_fetch_assoc']($query)) {
			$context['top_rating'][$row['id']] = [
				'topic'  => '<a href="' . $scripturl . '?topic=' . $row['id'] . '.0">' . $row['subject'] . '</a>',
				'board'  => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['name'] . '</a>',
				'author' => '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>',
				'group'  => empty($row['id_group']) ? $txt['tr_regular_members'] : $row['group_name'],
				'rating' => number_format($row['total_value'] / $row['total_votes'], 2),
				'votes'  => $row['total_votes'],
			];
		}

		$smcFunc['db_free_result']($query);
	}

	/**
	 * Displays the top rated topics block on the forum index
	 */
	public function showBestTopic(array $buttons): void
	{
		global $modSettings, $context;

		if (empty($modSettings['tr_show_best_topic']))
			return;

		if (isset($buttons['portal']) ? $context['current_action'] === 'forum' : empty($_REQUEST['action'])) {
			$this->prepareBestTopic();

			if (! empty($context['best_topic']))	{
				loadTemplate('TopicRatingBar');

				// Make sure our block is placed above the portal blocks
				if (isset($context['template_layers'][2]) && $context['template_layers'][2] === 'portal') {
					$context['template_layers'][]  = 'portal';
					$context['template_layers'][2] = 'best_topics';
				} else {
					$context['template_layers'][] = 'best_topics';
				}
			}
		}
	}

	/**
	 * Displays the star rating bar inside a topic
	 */
	public function showRatingBar(): void
	{
		global $context, $board_info, $modSettings;

		if (! empty($context['current_board']) && ! empty($context['trb_ignored_boards']) && in_array($context['current_board'], $context['trb_ignored_boards']))
			return;

		if (! empty($board_info['error']) || empty($context['current_topic']) || empty($context['topicinfo']['id_member_started']))
			return;

		$ratingData = $this->getTopicRatingData();
		$rating     = ($ratingData['total_votes'] == 0) ? 0 : number_format($ratingData['total_value'] / $ratingData['total_votes']);
		$users      = $this->getParsedUserIds($ratingData['user_ids']);
		$voted      = ! empty($users) && in_array($context['user']['id'], $users);

		$context['rating_bar'] = [
			'current'      => $rating,
			'rating_width' => $rating * self::UNIT_WIDTH,
			'units'        => empty($modSettings['tr_rate_system']) ? 5 : 10,
			'unit_width'   => self::UNIT_WIDTH,
			'users'        => $users,
			'voted'        => $voted,
		];

		if (empty($context['rating_bar']) || empty($context['subject']))
			return;

		$context['proper_user'] = (int) $context['topicinfo']['id_member_started'] !== $context['user']['id'] && allowedTo('rate_topics');

		loadTemplate('TopicRatingBar', 'trb_styles');

		$context['template_layers'][] = 'bar';
	}

	/**
	 * Data for updating the rating bar
	 */
	private function ratingPayload(int $votes, int $total, int $units): array
	{
		$average = $votes > 0 ? (int) round($total / $votes) : 0;

		return [
			'average' => $average,
			'votes'   => $votes,
			'width'   => $average * self::UNIT_WIDTH,
			'units'   => $units,
		];
	}

	/**
	 * Sends a JSON response and terminates the request
	 */
	private function ratingJson(int $status, array $data): void
	{
		header('Content-Type: application/json; charset=utf-8', true, $status);
		header('X-Content-Type-Options: nosniff');

		echo json_encode($data, JSON_UNESCAPED_UNICODE);

		exit;
	}

	/**
	 * Gets the current rating data of a topic
	 */
	private function getTopicRatingData(int $topic = 0): array
	{
		global $smcFunc, $context;

		$query = $smcFunc['db_query']('', '
			SELECT total_votes, total_value, user_ids
			FROM {db_prefix}topic_ratings
			WHERE id = {int:topic_id}
			LIMIT 1',
			[
				'topic_id' => $topic ?: $context['current_topic'],
			],
		);

		$result = $smcFunc['db_fetch_assoc']($query);

		$smcFunc['db_free_result']($query);

		if (empty($result)) {
			$result = [
				'total_votes' => 0,
				'total_value' => 0,
				'user_ids'    => '',
			];
		}

		return $result;
	}

	/**
	 * Gets an array of user IDs
	 */
	private function getParsedUserIds(?string $data): array
	{
		if (empty($data)) {
			return [];
		}

		return is_array($result = json_decode($data, true)) ? $result : unserialize($data);
	}
}
