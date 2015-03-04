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
