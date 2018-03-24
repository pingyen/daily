<?php
	ini_set('max_execution_time', 0);
	ini_set('memory_limit', -1);
	ini_set('date.timezone', 'Asia/Taipei');

	require(__DIR__ . '/phpQuery/phpQuery.php');


	class Worker {
		private $category;
		private $source;
		private $time;

		public function __construct ($category, $source) {
			$this->category = $category;
			$this->source = $source;

			$time = time();

			if (($time + 28800) % 86400 < 21600) {
				$time -= 86400;
			}

			$this->time = $time;
		}

		public function run () {
			$category = $this->category;
			$source = $this->source;
			file_put_contents(__DIR__ . "/temp/h.$category.$source", json_encode($this->$category()));
		}

		private function primary () {
			switch ($this->source) {
				case 'appledaily':
					return $this->appledaily('politics', '各報頭條搶先報', array(
							'udn' => '聯合報頭條',
							'appledaily' => '蘋果日報頭條',
							'chinatimes' => '中國時報頭條',
							'libertytimes' => '自由時報頭條'
						));
				case 'ettoday':
					return $this->ettoday(5, '四大報頭版頭', array(
							'udn' => '【聯合報】',
							'appledaily' => '【蘋果日報】',
							'chinatimes' => '【中國時報】',
							'libertytimes' => '【自由時報】'
						));
				case 'cna':
					return $this->cna(array(
							'udn' => '聯合報：',
							'appledaily' => '蘋果日報：',
							'chinatimes' => '中國時報：',
							'libertytimes' => '自由時報：'
						));
			}

			return array();
		}

		private function finance () {
			switch ($this->source) {
				case 'appledaily':
					return $this->appledaily('finance', '財經焦點搶先看', array(
							'edn' => '經濟日報-',
							'commercialtimes' => '工商時報-'
						));
				case 'ettoday':
					return $this->ettoday(17, '財經早報頭版頭', array(
							'edn' => '【經濟日報】',
							'commercialtimes' => '【工商時報】'
						));
			}

			return array();
		}

		private function blue () {
			return $this->cdnews(array(
					'udn' => '《聯合報》',
					'chinatimes' => '《中國時報》',
					'edn' => '《經濟日報》',
					'commercialtimes' => '《工商時報》'
				));
		}

		private function appledaily ($type, $keyword, $targets) {
			$time = $this->time;

			$nums = array(
					'1' => '一',
					'2' => '二',
					'3' => '三',
					'4' => '四',
					'5' => '五',
					'6' => '六',
					'7' => '七',
					'8' => '八',
					'9' => '九',
					'10' => '十',
					'11' => '十一',
					'12' => '十二',
					'13' => '十三',
					'14' => '十四',
					'15' => '十五',
					'16' => '十六',
					'17' => '十七',
					'18' => '十八',
					'19' => '十九',
					'20' => '二十',
					'21' => '二十一',
					'22' => '二十二',
					'23' => '二十三',
					'24' => '二十四',
					'25' => '二十五',
					'26' => '二十六',
					'27' => '二十七',
					'28' => '二十八',
					'29' => '二十九',
					'30' => '三十',
					'31' => '三十一'
				);

			$prefix = $nums[date('n', $time)] . '月' . $nums[date('j', $time)] . '日' . $keyword;

			for ($i = 1; $i <= 10; ++$i) {
				$doc = phpQuery::newDocument(file_get_contents("http://www.appledaily.com.tw/realtimenews/section/$type/$i"));

				foreach ($doc['.rtddt a'] as $anchor) {
					$anchor = pq($anchor);
					$title = trim($anchor['h1 > font']->text());

					if (substr($title, 0, strlen($prefix)) === $prefix) {
						$url = 'http://www.appledaily.com.tw' . $anchor->attr('href');
						break;
					}
				}

				if (isset($url)) {
					break;
				}
			}

			if (isset($url) === false) {
				return array();
			}


			$headlines = array();

			$doc = phpQuery::newDocument(file_get_contents($url));
			$nodes = $doc['strong'];
			$size = $nodes->size();

			for ($i = 0; $i < $size; ++$i) {
				$strong = $nodes->eq($i);
				$content = $strong->text();

				foreach ($targets as $key => $value) {
					$len = strlen($value);

					if (substr($content, 0, $len) === $value) {
						if (strlen($content) === $len) {
							++$i;
							$title = $nodes->eq($i)->text();
						}
						else {
							$title = substr($content, $len);
						}

						$headlines[$key] = $title;
						break;
					}
				}
			}

			return $headlines;
		}

		private function ettoday ($type, $keyword, $targets) {
			$time = $this->time;
			$date = date('Y-n-j', $time);
			$prefix = date('md', $time) . $keyword;

			for ($i = 1; $i <= 10; ++$i) {
				$doc = phpQuery::newDocument(file_get_contents("http://www.ettoday.net/news/news-list-$date-$type-$i.htm"));

				foreach ($doc['#all-news-list h3 a'] as $anchor) {
					$anchor = pq($anchor);
					$title = trim($anchor->text());

					if (substr($title, 0, strlen($prefix)) === $prefix) {
						$url = $anchor->attr('href');
						break;
					}
				}

				if (isset($url)) {
					break;
				}
			}

			if (isset($url) === false) {
				return array();
			}


			$headlines = array();

			$doc = phpQuery::newDocument(file_get_contents($url));
			$lines = explode("\n", $doc['.story']->children()->eq(0)->text());
			$n = count($lines);

			for ($i = 0; $i < $n; ++$i) {
				$line = trim($lines[$i]);

				foreach ($targets as $key => $value) {
					if ($line === $value) {
						++$i;
						$headlines[$key] = trim($lines[$i]);
						continue;
					}
				}
			}

			return $headlines;
		}

		private function cdnews ($targets) {
			$headlines = array();
			$date = '（' . date('Y-m-d', $this->time) . '）';

			for ($i = 1; $i <= 10; ++$i) {
				$doc = phpQuery::newDocument(file_get_contents('http://www.cdnews.com.tw/cdnews_site/coluOutline.jsp?coluid=144&page=' . $i));

				foreach ($doc['.pictxt_row > .righttxt > h3'] as $h3) {
					$h3 = pq($h3);
					$anchor = $h3['a'];
					$title = trim($anchor->text());
					$anchor->remove();

					if ($h3->text() !== $date) {
						continue;
					}

					foreach ($targets as $key => $value) {
						$len = strlen($value);

						if (substr($title, 0, $len) === $value) {
							$headlines[$key] = str_replace('　', ' ', substr($title, $len + ($title[$len] === ':' ? 1 : 3)));
							continue;
						}
					}
				}
			}

			return $headlines;
		}

		private function cna ($targets) {
			for ($i = 1; $i <= 30; ++$i) {
				$doc = phpQuery::newDocument(file_get_contents('http://www.cna.com.tw/news/firstnews/' . date('Ymd', time() - (intval(date('H')) < 8 ? 86400 : 0)) . '500' . $i . '.aspx'));
				$main = $doc['.news_content_new'];
				$title = $main['h1'];

				if (strpos($title, '台灣各報頭條速報') !== false) {
					break;
				}
			}

			if ($i === 31) {
				return array();
			}


			$headlines = array();

			foreach (explode('<br><br>', $main['.box_2 p']->html()) as $token) {
				foreach ($targets as $key => $value) {
					$len = strlen($value);

					if (substr($token, 0, $len) === $value) {
						$headlines[$key] = substr($token, $len);
						continue;
					}
				}
			}

			return $headlines;
		}
	}



	$start_time = time();


	$sourceMap = array(
			'primary' => array('appledaily', 'ettoday', 'cna'),
			'finance' => array('appledaily', 'ettoday'),
			'blue' =>  array('cdnews')
		);

	foreach ($sourceMap as $category => $sources) {
		foreach ($sources as $source) {
			if (! pcntl_fork()) {
				(new Worker($category, $source))->run();

				exit;
			}
		}
	}

	while (pcntl_waitpid(0, $status) !== -1) {
		pcntl_wexitstatus($status);
	}

	$mergeMap = array();

	foreach ($sourceMap as $category => $sources) {
		foreach ($sources as $source) {
			foreach (json_decode(file_get_contents(__DIR__ . "/temp/h.$category.$source"), true) as $key => $title) {
				if (isset($mergeMap[$key])) {
					$mergeMap[$key][$title] = true;
				}
				else {
					$mergeMap[$key] = array($title => true);
				}
			}
		}
	}

	$path = __DIR__ . '/headlines.json';
	$headlines = file_exists($path) ? json_decode(file_get_contents($path), true) : array();
	$articles = json_decode(file_get_contents(__DIR__ . '/articles.json'), true);

	foreach ($mergeMap as $source => $map) {
		$caught = false;
		$ref =& $headlines[$source];

		foreach (array_keys($map) as $title) {
			foreach ($articles as $article) {
				if ($article['source'] !== $source) {
					continue;
				}

				if ($source === 'appledaily') {
					if ($article['subcategory'] !== '頭條') {
						continue;
					}
				}
				else if ($source === 'chinatimes') {
					if ($article['category'] !== '焦點要聞') {
						continue;
					}
				}
				else if ($source === 'commercialtimes') {
					if ($article['category'] !== '財經要聞') {
						continue;
					}
				}
				else if ($source === 'libertytimes') {
					if ($article['category'] !== '頭版') {
						continue;
					}
				}
				else if ($source === 'udn') {
					if ($article['category'] !== '要聞') {
						continue;
					}
				}

				if ($article['title'] === $title) {
					if (isset($ref) === false || isset($ref['timestamp']) === false || $article['timestamp'] > $ref['timestamp']) {
						$caught = true;
						$ref = $article;
					}
					break;
				}
			}

			if ($caught === true) {
				break;
			}
		}

		if (isset($ref) === false || ($caught === false && $ref['title'] !== $title)) {
			$ref = array(
					'title' => $title,
					'link' => 'https://www.google.com.tw/search?q=' . rawurlencode($title)
				);
		}
	}

	file_put_contents(__DIR__ . '/headlines.json', json_encode($headlines));


	$spent_time = time() - $start_time;

	echo "Spent: $spent_time sec\n";
?>
