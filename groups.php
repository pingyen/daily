<?php
	ini_set('max_execution_time', 0);
	ini_set('memory_limit', -1);


	function reduce ($map, $size) {
		$cut = 0;

		foreach ($map as $term => $score) {
			if ($cut > $size && $score !== $base) {
				--$cut;
				break;
			}

			++$cut;

			if ($cut === $size) {
				$base = $score;
			}
		}

		return array_slice($map, 0, $cut, true);
	}

	function map ($keywords, $skips, $size, $common) {
		$n = count($keywords);
		$m = $n - 1;
		$caches = array();

		for ($i = 0; $i < $n; ++$i) {
			if (isset($skips[$i])) {
				continue;
			}

			$caches[$i] = array_keys(reduce($keywords[$i], $size));
		}

		$map = array();

		for ($i = 0; $i < $m; ++$i) {
			if (isset($skips[$i])) {
				continue;
			}

			for ($j = $i + 1; $j < $n; ++$j) {
				if (isset($skips[$j])) {
					continue;
				}

				if (count(array_intersect($caches[$i], $caches[$j])) >= $common) {
					$map[$i][$j] = true;
					$map[$j][$i] = true;
				}
			}
		}

		return $map;
	}

	function neighbor ($articles, $keywords, $skips, $same, $size, $common) {
		$n = count($keywords);
		$m = $n - 1;
		$caches = array();

		for ($i = 0; $i < $n; ++$i) {
			if (isset($skips[$i])) {
				continue;
			}

			$caches[$i] = array_keys(reduce($keywords[$i], $size));
		}

		$map = array();

		for ($i = 0; $i < $m; ++$i) {
			if (isset($skips[$i])) {
				continue;
			}

			$article = $articles[$i];

			for ($j = $i + 1, $k = $i + 3; $j <= $k && $j < $n; ++$j) {
				if (isset($skips[$j])) {
					continue;
				}

				$article2 = $articles[$j];

				if ($article['source'] !== $article2['source']) {
					continue;
				}

				if ($article['category'] !== $article2['category']) {
					continue;
				}

				$isset = isset($article['subcategory']);
				$isset2 = isset($article2['subcategory']);

				if (($isset === true &&
					 $isset2 === true &&
					 $article['subcategory'] !== $article2['subcategory']) ||
					$isset !== $isset2) {
					continue;
				}

				if ($same !== 0 && (
					isset($article['authors']) === false ||
					isset($article2['authors']) === false ||
					count(array_intersect($article['authors'], $article2['authors'])) < $same
					)) {
					continue;
				}

				if (count(array_intersect($caches[$i], $caches[$j])) >= $common) {
					$map[$i][$j] = true;
					$map[$j][$i] = true;
				}
			}
		}

		return $map;
	}

	function merge ($map, $map2) {
		foreach ($map2 as $parent => $children) {
			$ref =& $map[$parent];

			if (isset($ref)) {
				foreach (array_keys($children) as $child) {
					$ref[$child] = true;
				}
			}
			else {
				$ref = $children;
			}
		}

		return $map;
	}

	function group ($map) {
		$groups = array();

		foreach ($map as $parent => $children) {
			$ref =& $groups[];
			$ref = $children;
			$ref[$parent] = true;
		}

		while (true) {
			$groups2 = array();
			$n = count($groups);
			$m = $n - 1;

			for ($i = 0; $i < $m; ++$i) {
				if (isset($groups[$i]) === false) {
					continue;
				}

				for ($j = $i + 1; $j < $n; ++$j) {
					if (isset($groups[$j]) === false) {
						continue;
					}

					$group = $groups[$i];
					$group2 = $groups[$j];
					$group3 = $group + $group2;

					if (count($group3) < count($group) + count($group2)) {
						$groups2[] = $group3;
						unset($groups[$i]);
						unset($groups[$j]);
						break;
					}
				}
			}

			for ($i = 0; $i < $n; ++$i) {
				if (isset($groups[$i]) === false) {
					continue;
				}

				$groups2[] = $groups[$i];
			}

			if ($n === count($groups2)) {
				break;
			}

			$groups = $groups2;
		}

		return $groups2;
	}



	$start_time = time();


	$articles = json_decode(file_get_contents(__DIR__ . '/articles.json'), true);
	$keywords = json_decode(file_get_contents(__DIR__ . '/keywords.json'), true);
	$map = merge(map($keywords, array(), 20, 10), map($keywords, array(), 100, 40));
	$map = merge($map, neighbor($articles, $keywords, array(), 0, 10, 4));
	$map = merge($map, neighbor($articles, $keywords, array(), 0, 100, 30));
	$map = merge($map, neighbor($articles, $keywords, array(), 1, 10, 3));
	$map = merge($map, neighbor($articles, $keywords, array(), 1, 100, 20));
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
