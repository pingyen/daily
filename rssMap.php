<?php
	ini_set('max_execution_time', 0);	
	ini_set('memory_limit', -1);
	
	require(__DIR__ . '/phpQuery/phpQuery.php');


	class WorkerThread extends Thread {
		private $source;

		public function __construct ($source) {
			$this->source = $source;
		}

		public function run () {
			$source = $this->source;
			file_put_contents(__DIR__ . '/temp/r.' . $source, json_encode($this->$source()));
		}

		private function udn () {
			$url = 'http://udn.com/rssfeed/lists/2';
			$doc = phpQuery::newDocument(file_get_contents($url));
			$map = array();

			foreach ($doc['#rss_list .group'] as $group) {
				$group = pq($group);
				$category = $group['h3']->text();

				if ($category !== '要聞' &&
					$category !== '社會' &&
					$category !== '地方' &&
					$category !== '全球' &&
					$category !== '兩岸' &&
					$category !== '產經' &&
					$category !== '生活' &&
					$category !== '娛樂' &&
					$category !== '評論') {
					continue;
				}

				foreach ($group['dl dt a'] as $anchor) {
					$anchor = pq($anchor);
					$label = $anchor->text();

					if ($category === '全球' && (
						$label === '奇聞不要看' ||
						$label === '全球觀點')) {
						continue;
					}

					if ($category === '評論' && $label === 'udn鳴人堂') {
						continue;
					}

					if ($category === '娛樂' && (
						$label === '熱門星聞' ||
						$label === '星級評論' ||
						$label === '藝人動態' ||
						$label === '國際星情' ||
						$label === '電影世界' ||
						$label === '廣電頻道' ||
						$label === '流行音樂' ||
						$label === '徵婚啟事'
						)) {
						continue;
					}

					if ($category === '兩岸' && (
						$label == '陸港傳真' ||
						$label == '兩岸經貿' ||
						$label == '台商情報'
						)) {
						continue;
					}

					if ($category === '產經' && $label !== '財經焦點') {
						continue;
					}

					if ($category === '生活' && $label !== '生活新聞') {
						continue;
					}

					$map[] = array(
							'category' => $category,
							'label' => $label,
							'url' => 'http://udn.com' . $anchor->attr('href')
						);
				}
			}

			return $map;
		}

		private function chinatimes () {
			$url = 'http://www.chinatimes.com/syndication/rss';
			$tokens = explode('<hr>', file_get_contents($url));
			$doc = phpQuery::newDocument($tokens[1]);

			$map = array();

			foreach ($doc['ul > li'] as $group) {
				$group = pq($group);
				$category = $group->html();
				$category = trim(substr($category, 0, strpos($category, ' ')));

				if ($category !== '中國時報') {
					continue;
				}

				foreach ($group['.rssli'] as $li) {
					$li = pq($li);
					$label = $li['span']->eq(0)->text();

					if ($label !== '焦點要聞' &&
						$label !== '生活新聞' &&
						$label !== '社會新聞' &&
						$label !== '兩岸國際' &&
						$label !== '財經焦點' &&
						$label !== '時論廣場' &&
						$label !== '地方新聞') {
						continue;
					}

					$map[] = array(
							'category' => $category,
							'label' => $label,
							'url' => $li['a']->attr('href')
						);
				}
			}

			return $map;
		}

		private function appledaily () {
			$url = 'http://www.appledaily.com.tw/rss';
			$doc = phpQuery::newDocument(file_get_contents($url));

			$map = array();

			foreach ($doc['.each_level'] as $section) {
				$section = pq($section);
				$category = str_replace(' ', '', $section['h2 > a']->text());

				if ($category !== '要聞總覽' &&
					$category !== '國際總覽' &&
					$category !== '財經總覽' &&
					$category !== '論壇與專欄總覽') {
					continue;
				}

				foreach ($section['ul li a'] as $anchor) {
					$anchor = pq($anchor);
					$label = $anchor->text();

					if ($category === '要聞總覽' &&
						$label !== '頭條' &&
						$label !== '要聞' &&
						$label !== '政治' &&
						$label !== '社會' &&
						$label !== '生活' &&
						$label !== '地方綜合' &&
						$label !== '投訴') {
						continue;
					}

					if ($category === '國際總覽' && $label == '雙語天下') {
						continue;
					}

					$map[] = array(
							'category' => $category,
							'label' => $label,
							'url' => 'http://www.appledaily.com.tw' . $anchor->attr('href')
						);
				}
			}

			return $map;
		}

		private function libertytimes () {
			$doc = phpQuery::newDocument(file_get_contents('http://news.ltn.com.tw/service?p=8'));
			$map = array();

			foreach ($doc['.Txml tr']->slice(1) as $tr) {
				$tr = pq($tr);
				$td = $tr['td'];
				$label = substr($td->eq(0)->text(), 3);

				if ($label !== '頭版' &&
					$label !== '政治' &&
					$label !== '社會' &&
					$label !== '生活' &&
					$label !== '言論' &&
					$label !== '國際' &&
					$label !== '財經' &&
					$label !== '地方' &&
					$label !== '台北都會' &&
					$label !== '北部新聞' &&
					$label !== '中部新聞' &&
					$label !== '南部新聞') {
					continue;
				}

				$map[] = array(
						'label' => $label,
						'url' => $td->eq(2)->find('a')->attr('href')
					);
			}

			return $map;
		}
	}



	$start_time = time();


	$workers = array();
	$sources = array('udn', 'chinatimes', 'appledaily', 'libertytimes');

	foreach ($sources as $source) {
		$worker = new WorkerThread($source);
		$worker->start();
		$workers[] = $worker;
	}

	foreach ($workers as $worker) {
		$worker->join();
	}

	$map = array();

	foreach ($sources as $source) {
		$map[$source] = json_decode(file_get_contents(__DIR__ . '/temp/r.' . $source), true);
	}

	file_put_contents(__DIR__ . '/rssMap.json', json_encode($map));


	$spent_time = time() - $start_time;

	echo "Spent: $spent_time sec\n";
?>
