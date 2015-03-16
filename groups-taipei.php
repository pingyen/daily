<?php
	ini_set('max_execution_time', 0);
	ini_set('memory_limit', -1);


	require_once(__DIR__ . '/common/reduce.php');

	require_once(__DIR__ . '/common/map.php');

	require_once(__DIR__ . '/common/neighbor.php');

	require_once(__DIR__ . '/common/merge.php');

	require_once(__DIR__ . '/common/group.php');

	require_once(__DIR__ . '/common/preProcess.php');

	function compute (&$item, $patterns) {
		if (isset($item['count'])) {
			return $item['count'];
		}

		$content = $item['title'] . "\n" . $item['content'];

		if (isset($item['set'])) {
			$content = $item['set'] . "\n" . $content;
		}

		if (isset($item['caption'])) {
			$content = $item['caption'] . "\n" . $content;
		}

		$content = preProcess($content);
		$count = 0;

		foreach ($patterns as $positive => $negatives) {
			if (preg_match_all($positive . 'u', $content, $matches)) {
				if ($negatives === null) {
					$count += count($matches[0]);
					continue;
				}

				foreach ($matches[0] as $match) {
					$deny = false;

					foreach ($negatives as $negative) {
						if (preg_match($negative . 'u', $match)) {
							$deny = true;
							break;
						}
					}

					if ($deny === false) {
						++$count;
					}
				}
			}
		}

		$item['count'] = $count;

		return $count;
	}

	function match (&$item, $patterns) {
		if (isset($item['mode'])) {
			$mode = preProcess($item['mode']);

			if ($mode === '廣編特輯' ||
				$mode === '特別企劃') {
				return false;
			}
		}

		if (isset($item['authors'])) {
			$authors = $item['authors'];

			if ($item['source'] === 'chinatimes' &&
				count($authors) === 1 &&
				$authors[0] === '吳亦雯') {
				return false;
			}
		}

		$count = compute($item, $patterns);

		if (isset($mode) && strpos($mode, '台北') !== false && $count >= 4) {
			return true;
		}

		return $count >= 5;
	}



	$start_time = time();


	$articles = json_decode(file_get_contents(__DIR__ . '/articles.json'), true);
	$patterns = json_decode(file_get_contents(__DIR__ . '/taipei.json'));
	$positives = array();
	$negatives = array();

	foreach ($articles as $key => $article) {
		if (match($article, $patterns) === true) {
			$positives[$key] = true;
		}
		else {
			$negatives[$key] = true;
		}
	}

	$keywords = json_decode(file_get_contents(__DIR__ . '/keywords.json'), true);
	$map = merge(map($keywords, $negatives, 20, 10), map($keywords, $negatives, 100, 40));
	$map = merge($map, neighbor($articles, $keywords, $negatives, 0, 20, 8));
	$map = merge($map, neighbor($articles, $keywords, $negatives, 0, 100, 30));
	$map = merge($map, neighbor($articles, $keywords, $negatives, 1, 20, 6));
	$map = merge($map, neighbor($articles, $keywords, $negatives, 1, 100, 20));
	$groups = group($map);

	foreach (array_keys($articles) as $key) {
		if (isset($negatives[$key]) === false && isset($map[$key]) === false) {
			$groups[] = array($key => true);
		}
	}

	usort($groups, function ($a, $b) {
			global $articles, $patterns;

			$count = 0;

			foreach (array_keys($a) as $key) {
				$count += compute($articles[$key], $patterns);
			}

			$count2 = 0;

			foreach (array_keys($b) as $key) {
				$count2 += compute($articles[$key], $patterns);
			}

			return $count2 - $count;
		});

	foreach ($groups as &$group) {
		uksort($group, function ($a, $b) {
				global $articles, $patterns;

				return compute($articles[$b], $patterns) - compute($articles[$a], $patterns);
			});
	}

	unset($group);

	file_put_contents(__DIR__ . '/groups/taipei.json', json_encode($groups));

	$groups2 = array();
	$groups3 = array();

	foreach ($groups as $group) {
		$group2 = array();
		$group3 = array();

		foreach (array_keys($group) as $key) {
			if ($articles[$key]['source'] !== 'uen') {
				$group2[$key] = true;
			}
			else {
				$group3[$key] = true;
			}
		}

		if (count($group2)) {
			$groups2[] = $group2;
		}

		if (count($group3)) {
			$groups3[] = $group3;
		}
	}

	file_put_contents(__DIR__ . '/groups/taipei-morning.json', json_encode($groups2));
	file_put_contents(__DIR__ . '/groups/taipei-evening.json', json_encode($groups3));

	$map = merge(map($keywords, $positives, 20, 10), map($keywords, $positives, 100, 40));
	$map = merge($map, neighbor($articles, $keywords, $positives, 0, 20, 8));
	$map = merge($map, neighbor($articles, $keywords, $positives, 0, 100, 30));
	$map = merge($map, neighbor($articles, $keywords, $positives, 1, 20, 6));
	$map = merge($map, neighbor($articles, $keywords, $positives, 1, 100, 20));
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

	unset($group);

	file_put_contents(__DIR__ . '/groups/taipei-negative.json', json_encode($groups));

	$groups2 = array();
	$groups3 = array();

	foreach ($groups as $group) {
		$group2 = array();
		$group3 = array();

		foreach (array_keys($group) as $key) {
			if ($articles[$key]['source'] !== 'uen') {
				$group2[$key] = true;
			}
			else {
				$group3[$key] = true;
			}
		}

		if (count($group2)) {
			$groups2[] = $group2;
		}

		if (count($group3)) {
			$groups3[] = $group3;
		}
	}	

	file_put_contents(__DIR__ . '/groups/taipei-negative-morning.json', json_encode($groups2));
	file_put_contents(__DIR__ . '/groups/taipei-negative-evening.json', json_encode($groups3));


	$spent_time = time() - $start_time;

	echo "Spent: $spent_time sec\n";
?>
