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
			}
		}
	}

	$keywords = json_decode(file_get_contents(__DIR__ . '/keywords.json'), true);
	$map = merge(map($keywords, $skips, 100, 25), map($keywords, $skips, 400, 80));
	$map = merge($map, neighbor($articles, $keywords, $skips, 0, 400, 50));
	$groups = group($map);

	usort($groups, function ($a, $b) {
			global $articles;

			$score = 0;
			$order = 0;

			foreach (array_keys($a) as $key) {
				$article = $articles[$key];
				$score += $article['score'];
				$order += $article['order'];
			}

			$score2 = 0;
			$order2 = 0;

			foreach (array_keys($b) as $key) {
				$article = $articles[$key];
				$score2 += $article['score'];
				$order2 += $article['order'];
			}

			return $score === $score2 ? $order - $order2 : $score2 - $score;
		});

	foreach ($groups as &$group) {
		uksort($group, function ($a, $b) {
				global $articles;

				$article = $articles[$a];
				$article2 = $articles[$b];
				$score = $article2['score'] - $article['score'];

				return $score === 0 ? $article['order'] - $article2['order'] : $score;
			});
	}

	file_put_contents(__DIR__ . '/groups/taiwan.json', json_encode($groups));


	$spent_time = time() - $start_time;

	echo "Spent: $spent_time sec\n";
?>
