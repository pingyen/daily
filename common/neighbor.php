<?php
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
?>
