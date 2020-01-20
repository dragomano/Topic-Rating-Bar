<?php

function template_rating()
{
	global $settings, $context, $txt;

	echo '
	<div class="cat_bar">
		<h3 class="catbg">
			<span class="ie6_header floatleft">
				<img class="icon" width="16" src="', $settings['default_images_url'], '/trb/statistics.png" alt="" />
				', $txt['tr_top_topics'], '
			</span>
		</h3>
	</div>';

	if (!empty($context['top_rating']))	{
		echo '
	<p class="information">', $txt['tr_top_desc'], '</p>
	<div class="tborder topic_table centertext">
		<table class="table_grid">
			<thead>
				<tr class="catbg">
					<th class="first_th" scope="col">', $txt['topic'], '</th>
					<th scope="col">', $txt['board'], '</th>
					<th scope="col">', $txt['author'], '</th>
					<th scope="col">', $txt['position'], '</th>
					<th scope="col">', $txt['tr_average'], '</th>
					<th class="last_th" scope="col">', $txt['tr_votes'], '</th>
				</tr>
			</thead>
			<tbody>';

		foreach ($context['top_rating'] as $id => $data) {
			echo '
				<tr>
					<td class="windowbg2">', $data['topic'], '</td>
					<td class="windowbg">', $data['board'], '</td>
					<td class="windowbg2">', $data['author'], '</td>
					<td class="windowbg">', $data['group'], '</td>
					<td class="windowbg2">', $data['rating'], '</td>
					<td class="windowbg">', $data['votes'], '</td>
				</tr>';
		}
		echo '
			</tbody>
		</table>
	</div>
	<script type="text/javascript">window.jQuery || document.write(unescape(\'%3Cscript src="//cdn.jsdelivr.net/jquery/3/jquery.min.js"%3E%3C/script%3E\'))</script>
	<script type="text/javascript" src="', $settings['default_theme_url'], '/scripts/jquery.tablesorter.min.js"></script>
	<script type="text/javascript"><!-- // --><![CDATA[
		jQuery(document).ready(function($) {
			$(".table_grid").tablesorter();
		});
	// ]]></script>';
	} else
		echo '
	<p class="information">', $txt['tr_top_empty'], '</p>';

	echo '
	<br class="clear" />
	<div class="smalltext centertext"><a href="https://dragomano.ru/mods/topic-rating-bar" target="_blank">Topic Rating Bar</a></div>';
}

function template_bar_above()
{
	global $context, $modSettings, $txt, $scripturl, $settings;

	$rates = explode("|", empty($modSettings['tr_rate_system']) ? $txt['tr_rates'] : $txt['tr_rates_10']);

	if (empty($rates))
		return;

	$count = is_array($rates) ? count($rates) : 0;

	$header = '
	<div class="title_barIC">
		<span class="ie6_header ' . ($context['right_to_left'] ? 'floatright' : 'floatleft') . '">
			<a href="' . $scripturl . '?action=rating">
				<img class="icon" alt="" title="' . $txt['tr_top_stat'] . '" src="' . $settings['default_images_url'] . '/trb/statistics.png" />
			</a>
		</span>';

	$footer = '
	</div>';

	if (!empty($context['proper_user'])) {
		echo $header, '
	<ul id="unit_ul', $context['current_topic'], '" class="unit-rating" style="width:', $context['rating_bar']['unit_width'] * $context['rating_bar']['units'], 'px;">
		<li class="current-rating hreview-aggregate" style="width:', $context['rating_bar']['rating_width'], 'px;" title="', $txt['tr_currently'], $context['rating_bar']['current'], '/', $context['rating_bar']['units'], '">
			<span class="item"><span class="fn">', $context['subject'], '</span></span>
			<span class="rating">
				<span class="average">', $context['rating_bar']['current'], '</span>
				<span class="worst">0</span>
				<span class="best">', $count, '</span>
			</span>
			<span class="votes">', is_array($context['rating_bar']['users']) ? count($context['rating_bar']['users']) : 0, '</span>
		</li>';

		for ($i = 1; $i <= $context['rating_bar']['units']; $i++) {
			if (empty($context['rating_bar']['voted']))
				echo '
		<li>
			<span title="', $rates[$i-1], '" class="r', $i, '-unit rater">', $i, '</span>
		</li>';
		}

		echo '
	</ul>
	<span class="title">', empty($context['rating_bar']['voted']) ? $txt['tr_rate_pl'] : $txt['tr_currently'], '&nbsp;</span>', $footer;
	} elseif ($context['rating_bar']['current'] > 0) {
		echo $header, '
	<ul id="unit_ul', $context['current_topic'], '" class="unit-rating" style="width:', $context['rating_bar']['unit_width'] * $context['rating_bar']['units'], 'px;" title="', $txt['tr_currently'], $context['rating_bar']['current'], '/', $context['rating_bar']['units'], '">
		<li class="current-rating hreview-aggregate" style="width:', $context['rating_bar']['rating_width'], 'px;">
			<span class="item"><span class="fn">', $context['subject'], '</span></span>
			<span class="rating">
				<span class="average">', $context['rating_bar']['current'], '</span>
				<span class="worst">0</span>
				<span class="best">', $count, '</span>
			</span>
			<span class="votes">', is_array($context['rating_bar']['users']) ? count($context['rating_bar']['users']) : 0, '</span>
		</li>
	</ul>
	<span class="title">', $txt['tr_currently'], '&nbsp;</span>', $footer;
	}

	echo '
	<script type="text/javascript">window.jQuery || document.write(unescape(\'%3Cscript src="//cdn.jsdelivr.net/jquery/3/jquery.min.js"%3E%3C/script%3E\'))</script>
	<script type="text/javascript">
		var work = "', $scripturl, '?action=trb_rate";
		jQuery(document).ready(function($) {
			$("#unit_ul', $context['current_topic'], ' li > span").on("click", function() {
				ajax_indicator(true);
				var rating = $(this).text();
				$.post(work, {stars: rating, topic: ', $context['current_topic'], ', user: ', $context['user']['id'], '});
				$("#unit_ul', $context['current_topic'], '").replaceWith(\'<ul id="unit_ul', $context['current_topic'], '" class="unit-rating" style="width:', $context['rating_bar']['unit_width'] * $context['rating_bar']['units'], 'px;">\' +
			\'<li class="current-rating hreview-aggregate" style="width:\' + (rating * ', $context['rating_bar']['unit_width'], ') + \'px;">\' +
				\'<span class="item"><span class="fn">', $context['subject'], '</span></span>\' +
				\'<span class="rating">\' +
					\'<span class="average">\' + rating + \'</span>\' +
				\'</span>\' +
				\'<span class="votes">', is_array($context['rating_bar']['users']) ? count($context['rating_bar']['users']) : 0, '</span>\' +
			\'</li></ul>\').blur();
				setTimeout(function() {
					ajax_indicator(false);
				}, 500);
			});
		});
	</script>';
}

function template_bar_below()
{
}

function template_best_topics_above()
{
	global $txt, $settings, $scripturl, $context;

	echo '
	<div id="best_topics">
		<table class="table_list">
			<tbody class="header">
				<tr>
					<td colspan="4">
						<div class="cat_bar">
							<h3 class="catbg">', $txt['tr_best_topic'], '</h3>
						</div>
					</td>
				</tr>
			</tbody>
			<tbody class="content">
				<tr class="windowbg2">
					<td rowspan="2" class="icon windowbg">
						<img alt="" src="', $settings['default_images_url'], '/trb/best_topic.png" />
					</td>
					<td class="info">
						', $context['best_topic']['topic'], '
						<p class="smalltext">', $txt['tr_rating'], ' ', $context['best_topic']['rating'], '</p>
					</td>
					<td class="stats windowbg">
						<p>', $txt['posts'], ': ', $context['best_topic']['replies'], '<br />
						', $txt['tr_votes'], ': ', $context['best_topic']['votes'], '
						</p>
					</td>
					<td class="lastpost">
						<p><strong>', $txt['last_post'], '</strong> ', $txt['by'], ' ', $context['best_topic']['member'], '<br />
						', $txt['in'], ' ', $context['best_topic']['last_post'], '<br />
						', $context['best_topic']['time'], '</p>
					</td>
				</tr>
				<tr>
					<td class="children windowbg" colspan="3">
						<a href="', $scripturl, '?action=rating">', $txt['tr_other_topics'], '</a>
					</td>
				</tr>
			</tbody>
			<tbody class="divider">
				<tr>
					<td colspan="4"></td>
				</tr>
			</tbody>
		</table>
	</div>';
}

function template_best_topics_below()
{
}

function template_callback_tr_ignored_boards()
{
	global $context;

	echo '
		<dt></dt><dd></dd></dl>
		<ul class="ignoreboards floatleft" style="margin-top: -30px">';

	$i = 0;
	$limit = ceil($context['num_boards'] / 2);

	foreach ($context['categories'] as $category) {
		if ($i == $limit) {
			echo '
		</ul>
		<ul class="ignoreboards floatright" style="margin-top: -30px">';

			$i++;
		}

		echo '
			<li class="category">
				<strong>', $category['name'], '</strong>
				<ul>';

		foreach ($category['boards'] as $board)	{
			if ($i == $limit)
				echo '
				</ul>
			</li>
		</ul>
		<ul class="ignoreboards floatright">
			<li class="category">
				<ul>';

			echo '
					<li class="board" style="margin-', $context['right_to_left'] ? 'right' : 'left', ': ', $board['child_level'], 'em;">
						<label for="ignore_board', $board['id'], '"><input type="checkbox" id="ignore_board', $board['id'], '" name="ignore_board[', $board['id'], ']" value="', $board['id'], '"', $board['selected'] ? ' checked="checked"' : '', ' class="input_check" /> ', $board['name'], '</label>
					</li>';

			$i++;
		}

		echo '
				</ul>
			</li>';
	}

	echo '
		</ul>
		<br class="clear" />
		<dl><dt></dt><dd></dd>';
}
