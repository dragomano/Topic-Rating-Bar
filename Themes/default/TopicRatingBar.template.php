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

	if (! empty($context['top_rating']))	{
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

	$canVote = ! empty($context['proper_user']) && empty($context['rating_bar']['voted']);

	if (! empty($context['proper_user'])) {
		echo $header;

		if ($canVote)
			echo '
		<form method="post" action="', $scripturl, '?action=trb_rate" id="trb_form', $context['current_topic'], '" class="trb_rate_form">
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<input type="hidden" name="topic" value="', $context['current_topic'], '">';

		echo '
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

		if ($canVote) {
			for ($i = 1; $i <= $context['rating_bar']['units']; $i++) {
				echo '
				<li data-star="', $i, '">
					<button type="submit" name="stars" value="', $i, '" title="', htmlspecialchars($rates[$i-1]), '" class="r', $i, '-unit rater">', $i, '</button>
				</li>';
			}
		}

		echo '
			</ul>';

		if ($canVote)
			echo '
		</form>';

		echo $footer;
	} elseif (! empty($context['rating_bar']['current'])) {
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

	if ($canVote)
		echo /** @lang text */ '
	<script>
		document.addEventListener("DOMContentLoaded", function () {
			const form = document.getElementById("trb_form' . $context['current_topic'] . '");

			if (!form) {
				return;
			}

			// Without JavaScript, the browser submits the form itself
			form.addEventListener("submit", async function (event) {
				if (!event.submitter || event.submitter.name !== "stars") {
					return;
				}

				event.preventDefault();

				const data = new FormData(form);
				data.set("stars", event.submitter.value);

				ajax_indicator(true);

				try {
					const response = await fetch(form.action, {
						method: "POST",
						body: data,
						credentials: "same-origin",
						headers: {
							"X-Requested-With": "fetch",
							"Accept": "application/json"
						}
					});

					if (!response.ok) {
						form.submit();
						return;
					}

					const rating = await response.json();
					const bar = document.getElementById("unit_ul' . $context['current_topic'] . '");
					const current = bar.querySelector(".current-rating");

					current.style.width = rating.width + "px";
					current.querySelector(".average").textContent = rating.average;
					current.querySelector(".votes").textContent = rating.votes;
					bar.title = ' . JavaScriptEscape($txt['tr_currently']) . ' + rating.average + "/" + rating.units;

					bar.querySelectorAll("li[data-star]").forEach(function (item) {
						item.remove();
					});
				} catch (error) {
					form.submit();
				} finally {
					ajax_indicator(false);
				}
			});
		});
	</script>';
}

function template_bar_below() {}

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

function template_best_topics_below() {}
