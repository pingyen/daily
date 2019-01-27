<?php
	ini_set('max_execution_time', 0);
	ini_set('memory_limit', -1);


	require_once(__DIR__ . '/common/reduce.php');

	require_once(__DIR__ . '/common/map.php');

	require_once(__DIR__ . '/common/neighbor.php');

	require_once(__DIR__ . '/common/merge.php');

	require_once(__DIR__ . '/common/group.php');



	$start_time = time();


	$articles = json_decode(file_get_contents(__DIR__ . '/articles.json'), true);
	$skips = array();

	foreach ($articles as $key => $article) {
		if (isset($article['mode']) === true) {
			$mode = $article['mode'];

			if ($mode === '廣編特輯' || $mode === '特別企劃') {
				$skips[$key] = true;
				continue;
			}
		}

		if ($article['source'] === 'appledaily' &&
			($article['category'] === 'opinion' ||
			 substr($article['title'], 0, 18) === '國際夯人誌：' ||
			 substr($article['title'], 0, 15) === '【蘋中人】')) {
			$skips[$key] = true;
			continue;
		}
	}

	$keywords = json_decode(file_get_contents(__DIR__ . '/keywords.json'), true);
	$map = merge(map($keywords, $skips, 100, 25), map($keywords, $skips, 400, 80));
	$map = merge($map, neighbor($articles, $keywords, $skips, 0, 400, 50));
	$groups = group($map);

	$scores = array();
	$orders = array();

	foreach ($groups as $group) {
		$score = 0;
		$order = 0;

		foreach (array_keys($group) as $key) {
			$article = $articles[$key];
			$score += $article['score'];
			$order += $article['order'];
		}

		$scores[] = $score;
		$orders[] = $order;
	}

	$keys = array_keys($groups);

	usort($keys, function ($a, $b) {
			global $scores, $orders;

			$score = $scores[$a];
			$order = $scores[$a];
			$score2 = $scores[$b];
			$order2 = $scores[$b];

			return $score === $score2 ? $order - $order2 : $score2 - $score;
		});

	$groups2 = array();

	foreach ($keys as $key) {
		if ($scores[$key] < 30) {
			continue;
		}

		$groups2[] = $groups[$key];
	}

	foreach ($groups2 as &$group) {
		uksort($group, function ($a, $b) {
				global $articles;

				$article = $articles[$a];
				$article2 = $articles[$b];
				$score = $article2['score'] - $article['score'];

				return $score === 0 ? $article['order'] - $article2['order'] : $score;
			});
	}

	file_put_contents(__DIR__ . '/groups/taiwan.json', json_encode($groups2));


	$spent_time = time() - $start_time;

	echo "Spent: $spent_time sec\n";
?>
