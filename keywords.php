<?php
	ini_set('max_execution_time', 0);
	ini_set('memory_limit', -1);


	function clean ($articles) {
		foreach ($articles as &$article) {
			$title = $article['title'];

			if (preg_match('/【壹週刊】(.+)/u', $title, $matches)) {
				$article['title'] = $matches[1];
				$content = $article['content'];
				$article['content'] = substr($content, 0, strpos($content, "\n\n"));
				$article['caption'] = '';
			}
			else {
				$article['title'] = preg_replace(array(
						'/周刊專訪／/u',
						'/地方掃描－/u', 
						'/〈台股青紅燈〉/u'
					), '', $title);
			}
		}

		return $articles;
	}

	require_once(__DIR__ . '/common/preProcess.php');

	require_once(__DIR__ . '/common/reduce.php');

	function keywords ($article) {
		$stops = array(
				' ', "\n", "\r", "\t",
				'.', ',', ';', ':', '?', '!', 
				'(', ')', '[', ']', '{', '}', '\'', '"', '<', '>', 
				'-', '_', '@', '#', '$', '%', '&', '~', '|', '\\', '/', '`', '^',
				'+', '=', '*', 
				'。', '、', ',', '…', '．',
				'「', '」', '《', '》', '〈', '〉', '【', '】',
				'★'
			);

		for($i = 8192; $i <= 8207; ++$i) {
			$stops[] = html_entity_decode("&#$i;");
		} 

		$stops[] = html_entity_decode('&#8239;');

		$weights = array(
				'set' => array(120),
				'title' => array(100),
				'content' => array(80, 60, 40, 20, 10, 5),
				'caption' => array(50, 30, 10, 5)
			);

		$map = array();

		foreach ($weights as $key => $scores) {
			if (isset($article[$key]) === false) {
				continue;
			}

			$snippets = explode("\n\n", preProcess($article[$key]));
			$n = count($snippets);
			$p = count($scores) - 1;

			for ($i = 0; $i < $n; ++$i) {
				$snippet = $snippets[$i];
				$m = mb_strlen($snippet, 'UTF-8') - 1;

				for ($j = 0; $j < $m; ++$j) {
					$term = mb_substr($snippet, $j, 2, 'UTF-8');
					$pass = false;

					foreach ($stops as $stop) {
						if (strpos($term, $stop) !== false) {
							$pass = true;
							break;
						}
					}

					if ($pass === true) {
						continue;
					}

					$score = $scores[$i > $p ? $p : $i];

					if (isset($map[$term])) {
						$map[$term] += $score;
					}
					else {
						$map[$term] = $score;
					}
				}
			}
		}

		arsort($map);

		return reduce($map, 100);
	}



	$start_time = time();


	$map = array();

	foreach (clean(json_decode(file_get_contents(__DIR__ . '/articles.json'), true)) as $article) {
		$map[] = keywords($article);
	}

	file_put_contents(__DIR__ . '/keywords.json', json_encode($map));


	$spent_time = time() - $start_time;

	echo "Spent: $spent_time sec\n";
?>
