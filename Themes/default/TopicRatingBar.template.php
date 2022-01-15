<?php

function template_rating()
{
	global $settings, $txt, $context;

	echo '
	<div class="cat_bar">
		<h3 class="catbg">
			<img alt="*" class="icon" width="16" src="', $settings['default_images_url'], '/trb/statistics.png">
			', $txt['tr_top_topics'], '
		</h3>
	</div>';

	if (!empty($context['top_rating']))	{
		echo '
	<p class="information">', $txt['tr_top_desc'], '</p>
	<div class="tborder topic_table centertext">
		<table class="trb table_grid">
			<thead>
				<tr class="title_bar">
					<th>', $txt['topic'], '</th>
					<th>', $txt['board'], '</th>
					<th>', $txt['author'], '</th>
					<th>', $txt['position'], '</th>
					<th>', $txt['tr_average'], '</th>
					<th>', $txt['tr_votes'], '</th>
				</tr>
			</thead>
			<tbody>';

		foreach ($context['top_rating'] as $data) {
			echo '
				<tr class="windowbg">
					<td>', $data['topic'], '</td>
					<td>', $data['board'], '</td>
					<td>', $data['author'], '</td>
					<td>', $data['group'], '</td>
					<td>', $data['rating'], '</td>
					<td>', $data['votes'], '</td>
				</tr>';
		}
		echo '
			</tbody>
		</table>
	</div>
	<script src="', $settings['default_theme_url'], '/scripts/jquery.tablesorter.min.js"></script>
	<script>
		jQuery(document).ready(function($) {
			$(".table_grid").tablesorter();
		});
	</script>';
	}
	else
		echo '
	<p class="information">', $txt['tr_top_empty'], '</p>';

	$link = $context['user']['language'] === 'russian' ? 'https://dragomano.ru/mods/topic-rating-bar' : 'https://custom.simplemachines.org/mods/index.php?mod=3236';

	echo '
	<br class="clear">
	<div class="smalltext centertext"><a href="' . $link . '" target="_blank" rel="noopener">Topic Rating Bar</a></div>';
}

function template_bar_above()
{
	global $modSettings, $txt, $scripturl, $settings, $context;

	$rates = explode("|", empty($modSettings['tr_rate_system']) ? $txt['tr_rates'] : $txt['tr_rates_10']);

	if (empty($rates))
		return;

	$count = is_array($rates) ? count($rates) : 0;

	$header = '
	<div class="roundframe topic_rating_div">
		<a href="' . $scripturl . '?action=rating">
			<img class="icon" alt="" title="' . $txt['tr_top_stat'] . '" src="' . $settings['default_images_url'] . '/trb/statistics.png">
		</a>';

	$footer = '
	</div>';

	if (!empty($context['proper_user'])) {
		echo $header, '
		<ul id="unit_ul', $context['current_topic'], '" class="unit-rating" style="width:', $context['rating_bar']['unit_width'] * $context['rating_bar']['units'], 'px" title="', $txt['tr_currently'], $context['rating_bar']['current'], '/', $context['rating_bar']['units'], '">
			<li class="current-rating hreview-aggregate" style="width:', $context['rating_bar']['rating_width'], 'px">
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
		</ul>', $footer;
	} elseif (!empty($context['rating_bar']['current'])) {
		echo $header, '
		<ul id="unit_ul', $context['current_topic'], '" class="unit-rating" style="width:', $context['rating_bar']['unit_width'] * $context['rating_bar']['units'], 'px" title="', $txt['tr_currently'], $context['rating_bar']['current'], '/', $context['rating_bar']['units'], '">
			<li class="current-rating hreview-aggregate" style="width:', $context['rating_bar']['rating_width'], 'px">
				<span class="item"><span class="fn">', $context['subject'], '</span></span>
				<span class="rating">
					<span class="average">', $context['rating_bar']['current'], '</span>
					<span class="worst">0</span>
					<span class="best">', $count, '</span>
				</span>
				<span class="votes">', is_array($context['rating_bar']['users']) ? count($context['rating_bar']['users']) : 0, '</span>
			</li>
		</ul>', $footer;
	}

	if (!empty($context['proper_user']) && empty($context['rating_bar']['voted']))
		echo '
	<script>
		let work = smf_scripturl + "?action=trb_rate";
		jQuery(document).ready(function($) {
			$("#unit_ul', $context['current_topic'], ' li > span").on("click", function() {
				ajax_indicator(true);
				let rating = $(this).text();
				$.post(work, {stars: rating, topic: ', $context['current_topic'], ', user: ', $context['user']['id'], '});
				$("#unit_ul', $context['current_topic'], '").replaceWith(\'<ul id="unit_ul', $context['current_topic'], '" class="unit-rating" style="width:', $context['rating_bar']['unit_width'] * $context['rating_bar']['units'], 'px">\' +
			\'<li class="current-rating hreview-aggregate" style="width:\' + (rating * ', $context['rating_bar']['unit_width'], ') + \'px">\' +
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
	global $txt, $settings, $context, $scripturl;

	echo '
	<div id="best_topics">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['tr_best_topic'], '</h3>
		</div>
		<div>
			<div class="up_contain">
				<div class="board_icon">
					<img alt="*" src="', $settings['default_images_url'], '/trb/best_topic.png">
				</div>
				<div class="info">
					<p class="floatleft board_description">', $context['best_topic']['topic'], '<br>
					', $txt['tr_rating'], ' ', $context['best_topic']['rating'], '</p>
					<p class="floatright"><a href="', $scripturl, '?action=rating">', $txt['tr_other_topics'], '</a></p>
				</div>
				<div class="board_stats">
					<p>', $txt['posts'], ': ', $context['best_topic']['replies'], '<br>', $txt['tr_votes'], ': ', $context['best_topic']['votes'], '</p>
				</div>
				<div class="lastpost lpr_border">
					<p><strong>', $txt['last_post'], ':</strong> ', $context['best_topic']['time'], '<br>
					', $context['best_topic']['last_post'], ' ', $txt['by'], ' ', $context['best_topic']['member'], '</p>
				</div>
			</div>
		</div>
	</div>';
}

function template_best_topics_below()
{
}
