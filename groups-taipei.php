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

	function preProcess ($content) {
		$content = str_replace(
				array(
					'（', '）', '〔', '〕', '｛', '｝', 
					'﹒', '，', '；', '：',
					'－', '？', '！', '＠', '＃', '＄', '％', '＆', '｜', '＼',
					'／', '＋', '＝', '＊', '～', '｀', '＇', '＂', '＜', '＞',
					'︿', '＿', '　',
					'０', '１', '２', '３', '４', '５', '６', '７', '８', '９',
					'ａ', 'ｂ', 'ｃ', 'ｄ', 'ｅ', 'ｆ', 'ｇ', 'ｈ', 'ｉ', 'ｊ',
					'ｋ', 'ｌ', 'ｍ', 'ｎ', 'ｏ', 'ｐ', 'ｑ', 'ｒ', 'ｓ', 'ｔ',
					'ｕ', 'ｖ', 'ｗ', 'ｘ', 'ｙ', 'ｚ',
					'Ａ', 'Ｂ', 'Ｃ', 'Ｄ', 'Ｅ', 'Ｆ', 'Ｇ', 'Ｈ', 'Ｉ', 'Ｊ',
					'Ｋ', 'Ｌ', 'Ｍ', 'Ｎ', 'Ｏ', 'Ｐ', 'Ｑ', 'Ｒ', 'Ｓ', 'Ｔ',
					'Ｕ', 'Ｖ', 'Ｗ', 'Ｘ', 'Ｙ', 'Ｚ',
					'○',
					'·', '˙', '●', '•',
					'　', '×', '╱', '◎'
				),
				array(
					'(', ')', '[', ']', '{', '}', 
					'.', ',', ';', ':',
					'-', '?', '!', '@', '#', '$', '%', '&', '|', '\\',
					'/', '+', '=', '*', '~', '`', '\'', '"', '<', '>',
					'^', '_', ' ',
					'0', '1', '2', '3', '4', '5', '6', '7', '8', '9',
					'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j',
					'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't',
					'u', 'v', 'w', 'x', 'y', 'z',
					'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J',
					'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T',
					'U', 'V', 'W', 'X', 'Y', 'Z',
					'0',
					'.', '.', '.', '.',
					' ', 'x', '/', '@'
				),
				$content
			);

		$content = mb_strtolower($content);
		$content = str_replace(
				array(
					'臺',
					'一', '二', '三', '四', '五', '六', '七', '八', '九',
					'柯p', '柯:'
				),
				array(
					'台',
					1, 2, 3, 4, 5, 6, 7, 8, 9,
					'柯文哲', '柯文哲:'
				), 
				$content
			);

		return $content;
	}

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
	$map = merge($map, neighbor($articles, $keywords, $negatives, 0, 10, 4));
	$map = merge($map, neighbor($articles, $keywords, $negatives, 0, 100, 30));
	$map = merge($map, neighbor($articles, $keywords, $negatives, 1, 10, 3));
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
	$map = merge($map, neighbor($articles, $keywords, $positives, 0, 10, 4));
	$map = merge($map, neighbor($articles, $keywords, $positives, 0, 100, 30));
	$map = merge($map, neighbor($articles, $keywords, $positives, 1, 10, 3));
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
