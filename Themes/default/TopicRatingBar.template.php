<?php

function template_rating(): void
{
	global $txt, $context;

	echo '
	<div class="cat_bar">
		<h3 class="catbg">
			<i class="main_icons general"></i> ', $txt['tr_top_topics'], '
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
		echo /** @lang text */ '
			</tbody>
		</table>
	</div>
	<link href="https://cdn.jsdelivr.net/npm/tablesort@5/tablesort.min.css" rel="stylesheet">
	<script src="https://cdn.jsdelivr.net/npm/tablesort@5/src/tablesort.min.js"></script>
	<script>
		new Tablesort(document.querySelector(".table_grid"));
	</script>';
	} else {
		echo '
	<p class="information">', $txt['tr_top_empty'], '</p>';
	}

	echo '
	<br class="clear">';
}

function template_bar_above(): void
{
	global $modSettings, $txt, $scripturl, $context;

	$rates = explode("|", empty($modSettings['tr_rate_system']) ? $txt['tr_rates'] : $txt['tr_rates_10']);

	if (empty($rates))
		return;

	$count = is_array($rates) ? count($rates) : 0;

	$header = '
	<div class="roundframe topic_rating_div">
		<a href="' . $scripturl . '?action=rating" title="' . $txt['tr_top_stat'] . '">
			<i class="main_icons general"></i>
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
		echo /** @lang text */ '
	<script>
		let work = smf_scripturl + "?action=trb_rate";
		document.addEventListener("DOMContentLoaded", function() {
			let elements = document.querySelectorAll("#unit_ul' . $context['current_topic'] . ' li > span");
			elements.forEach(function(element) {
				element.addEventListener("click", function() {
					ajax_indicator(true);
					let rating = this.textContent;
					let xhr = new XMLHttpRequest();
					xhr.open("POST", work, true);
					xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded; charset=UTF-8");
					xhr.send("stars=" + rating + "&topic=' . $context['current_topic'] . '&user=' . $context['user']['id'] . '");
					xhr.onload = function() {
						let unitUl = document.getElementById("unit_ul' . $context['current_topic'] . '");
						unitUl.innerHTML = \'<ul id="unit_ul' . $context['current_topic'] . '" class="unit-rating" style="width:' . $context['rating_bar']['unit_width'] * $context['rating_bar']['units'] . 'px">\' +
							\'<li class="current-rating hreview-aggregate" style="width:\' + (rating * ' . $context['rating_bar']['unit_width'] . ') + \'px">\' +
							\'<span class="item"><span class="fn">' . htmlspecialchars($context['subject']) . '</span></span>\' +
							\'<span class="rating">\' +
							\'<span class="average">\' + rating + \'</span>\' +
							\'</span>\' +
							\'<span class="votes">' . (is_array($context['rating_bar']['users']) ? count($context['rating_bar']['users']) : 0) . '</span>\' +
							\'</li></ul>\';
						ajax_indicator(false);
					};
				});
			});
		});
	</script>';
}

function template_bar_below()
{
}

function template_best_topics_above(): void
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
					<p>', $txt['replies'], ': ', $context['best_topic']['replies'], '<br>', $txt['tr_votes'], ': ', $context['best_topic']['votes'], '</p>
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
