<?php
/**
* Topic Rating Bar © 2011-2013, Bugo
* Subs-TopicRating.php
* License http://opensource.org/licenses/artistic-license-2.0
* http://dragomano.ru/page/topic-rating-bar and
* http://custom.simplemachines.org/mods/index.php?mod=3236
*/

if (!defined('SMF'))
	die('Hacking attempt...');

function trb_rating_load_theme()
{
	global $modSettings;

	loadLanguage('TopicRating/');
	
	if (!empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 2)
		clean_cache();
}

// Loading from integrate_menu_buttons
function trb_rating_preload()
{
	global $context, $modSettings, $smcFunc, $board_info;
	
	if (empty($_REQUEST['board']) && empty($_REQUEST['topic']) && empty($_REQUEST['action']) && !WIRELESS || $context['current_action'] == 'forum')	{
		trb_rating_best_topic();
		
		if (!empty($context['best_topic']))	{
			loadTemplate('TopicRating');
			
			if (isset($context['template_layers'][2]) && $context['template_layers'][2] == 'portal') {
				$context['template_layers'][]  = 'portal';
				$context['template_layers'][2] = 'best_topics';
			}
			else
				$context['template_layers'][] = 'best_topics';
		}
	}

	if (!empty($context['current_board']) && !WIRELESS)	{
		$context['rating_bar']   = '';
		$context['topic_rating'] = '';
		$rating_ignore_boards    = array();
		
		if (!empty($modSettings['tr_ignore_boards']))
			$rating_ignore_boards = explode(",", $modSettings['tr_ignore_boards']);

		if (!empty($modSettings['recycle_board']))
			$rating_ignore_boards[] = $modSettings['recycle_board'];

		if (!in_array($context['current_board'], $rating_ignore_boards)) {
			// Message Index
			if (!empty($_REQUEST['board']) && !empty($modSettings['tr_mini_rating'])) {
				if (empty($context['no_topic_listing']) && !isset($_REQUEST['action']))	{
					if (!empty($context['topics']))	{
						$topics = array_keys($context['topics']);
						
						$query = $smcFunc['db_query']('', '
							SELECT id, total_votes, total_value
							FROM {db_prefix}topic_ratings
							WHERE id IN ({array_int:topics})
							LIMIT ' . count($topics),
							array(
								'topics' => $topics,
							)
						);
						
						while ($row = $smcFunc['db_fetch_assoc']($query)) {
							$context['topic_rating'][$row['id']] = array(
								'votes' => $row['total_votes'],
								'value' => $row['total_value'],
							);
						}

						$smcFunc['db_free_result']($query);
					}
				}
			}
			
			// Display
			if (!empty($_REQUEST['topic']) && empty($context['current_action']) && empty($board_info['error'])) {
				loadTemplate(false, 'trb_styles');
				$context['rating_bar'] = array();
				trb_rating_bar($context['current_topic']);
			}
		}
	}
}

// Loading from rating_preload (see above)
function trb_rating_bar($id = 0, $units = 5, $unit_width = 25)
{ 
	global $smcFunc, $context, $topicinfo;

	if (empty($id))
		return;
	
	$query = $smcFunc['db_query']('', '
		SELECT total_votes, total_value, user_ids
		FROM {db_prefix}topic_ratings
		WHERE id = {int:topic_id}
		LIMIT 1',
		array(
			'topic_id' => $id,
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
		'units'        => $units,
		'unit_width'   => $unit_width,
		'users'        => $users,
		'voted'        => $voted,
	);
	
	if (empty($context['rating_bar']) || empty($context['subject']))
		return;
		
	$context['proper_user'] = !$context['user']['is_guest'] && ($topicinfo['id_member_started'] != $context['user']['id']) && allowedTo('rate_topics');
	
	loadTemplate('TopicRating');
	$context['template_layers'][] = 'bar';
}

// Loading from integrate_messageindex_buttons
function trb_rating_messageindex()
{
	global $context, $txt, $settings;
	
	if (!empty($context['topic_rating'])) {
		$cdn = $txt['lang_dictionary'] == 'ru' ? 'http://yandex.st/jquery/1.10.2/jquery.min.js' : 'http://ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js';

		$context['insert_after_template'] .= '
	<script type="text/javascript">window.jQuery || document.write(unescape(\'%3Cscript src="' . $cdn . '"%3E%3C/script%3E\'))</script>
	<script type="text/javascript" src="http://code.jquery.com/jquery-migrate-1.2.1.js"></script>
	<script type="text/javascript"><!-- // --><![CDATA[
		$star = jQuery.noConflict();
		$star(document).ready(function($){';
		
		foreach ($context['topic_rating'] as $topic => $data) {
			$rating = ($data['votes'] == 0) ? 0 : number_format($data['value'] / $data['votes'], 0);
			$img = '';
					
			for ($i = 0; $i < $rating; $i++)
				$img .= '<img src="' . $settings['default_images_url'] . '/trb/one_star.png" alt="" />';

			$context['insert_after_template'] .= '
			var starImg' . $topic . ' = $star("span#msg_' . $context['topics'][$topic]['first_post']['id'] . '");
			starImg' . $topic . '.before(\'<span class="floatright" style="margin-right: 50px" title="' . $txt['tr_average'] . ': ' . $rating . ' | ' . $txt['tr_votes'] . ': ' . $data['votes'] . '">' . $img . '</span>\');';
		}
			
		$context['insert_after_template'] .= '
		});
	// ]]></script>';
	}
}

// Loading from integrate_actions
function trb_rating_actions(&$actionArray)
{
	$actionArray['trb_rate'] = array('Subs-TopicRating.php', 'trb_rating_control');
	$actionArray['rating']   = array('Subs-TopicRating.php', 'trb_rating_top');
}

// Loading from rating_actions (see above)
function trb_rating_control()
{
	global $txt, $context, $topicinfo, $smcFunc;
	
	checkSession('request');

	$vote_sent   = (int) $_REQUEST['stars'];
	$topic       = (int) $_REQUEST['topic'];
	$user_id_num = (int) $context['user']['id'];
	$units       = (int) $_REQUEST['scale'];
	$referer     = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';

	if (empty($vote_sent) || empty($topic) || empty($user_id_num) || empty($units))
		redirectexit($referer);
		
	if (!allowedTo('rate_topics') || empty($referer))
		fatal_lang_error('cannot_rate_topics', false);
		
	if ($context['user']['id'] == $topicinfo['id_member_started'])
		fatal_lang_error('tr_error', false);

	$query = $smcFunc['db_query']('', '
		SELECT total_votes, total_value, user_ids
		FROM {db_prefix}topic_ratings
		WHERE id = {int:topic}
		LIMIT 1',
		array(
			'topic' => $topic,
		)
	);
	
	$numbers = $smcFunc['db_fetch_assoc']($query);
	
	$smcFunc['db_free_result']($query);
	
	$check_user_id = @unserialize($numbers['user_ids']);
	$voted = empty($check_user_id) ? false : in_array($user_id_num, $check_user_id);
	$count = $numbers['total_votes'];
	$current_rating = $numbers['total_value'];
	$total = $vote_sent + $current_rating;
	$total == 0 ? $votes = 0 : $votes = $count + 1;

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
					'user_ids'    => 'string',
				),
				array(
					$topic,
					$votes,
					$total,
					$users,
				),
				array('id')
			);
		}
		
		redirectexit($referer);
	}
}

// Loading from rating_actions (see above)
function trb_rating_top()
{
	global $context, $txt, $scripturl, $smcFunc, $modSettings;
	
	loadTemplate('TopicRating', 'trb_styles');
	$context['sub_template']  = 'rating';
	$context['page_title']    = $txt['tr_top_stat'];
	$context['canonical_url'] = $scripturl . '?action=rating';
	
	$context['linktree'][] = array(
		'name' => $context['page_title'],
		'url'  => $context['canonical_url'],
	);
	
	$context['top_rating'] = array();
	
	$limit = !empty($modSettings['tr_count_topics']) ? (int) $modSettings['tr_count_topics'] : 0;
	
	$ignore_boards = array();
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
			'ignore_boards' => $ignore_boards,
		)
	);
	
	while ($row = $smcFunc['db_fetch_assoc']($query))
		$context['top_rating'][$row['id']] = array(
			'topic'  => '<a href="' . $scripturl . '?topic=' . $row['id'] . '.0" target="_blank">' . $row['subject'] . '</a>',
			'board'  => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0" target="_blank">' . $row['name'] . '</a>',
			'author' => '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '" target="_blank">' . $row['real_name'] . '</a>',
			'group'  => empty($row['id_group']) ? $txt['tr_regular_members'] : $row['group_name'],
			'rating' => number_format($row['total_value'] / $row['total_votes'], 2),
			'votes'  => $row['total_votes'],
		);
	
	$smcFunc['db_free_result']($query);
}

// Loading from integrate_load_permissions
function trb_rating_permissions(&$permissionGroups, &$permissionList)
{
	global $context;

	$context['non_guest_permissions'][] = 'rate_topics';
	$permissionList['membergroup']['rate_topics'] = array(false, 'general', 'view_basic_info');
}

// The best topic (loading from rating_preload)
function trb_rating_best_topic()
{
	global $modSettings, $smcFunc, $context, $scripturl, $txt;
	
	if (empty($modSettings['tr_show_best_topic']))
		return;
	
	$ignore_boards = array();
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
			'ignore_boards' => $ignore_boards,
		)
	);
	
	$context['best_topic'] = array();
	while ($row = $smcFunc['db_fetch_assoc']($query)) {
		$subject = shorten_subject($row['last'], 36);
		$context['best_topic'] = array(
			'topic'     => '<a href="' . $scripturl . '?topic=' . $row['id'] . '.0" class="subject">' . $row['subject'] . '</a>',
			'rating'    => number_format($row['total_value'] / $row['total_votes'], 2),
			'replies'   => $row['num_replies'] + 1,
			'time'      => $row['poster_time'] > 0 ? timeformat($row['poster_time']) : $txt['not_applicable'],
			'last_post' => '<a href="' . $scripturl . '?topic=' . $row['id'] . '.msg' . $row['id_last_msg'] . '#new" title="' . $row['last'] . '">' . $subject . '</a>',
			'member'    => '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '" target="_blank">' . $row['real_name'] . '</a>',
			'votes'     => $row['total_votes'],
		);
	}
	
	$smcFunc['db_free_result']($query);
}

?>