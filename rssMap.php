<?php
	ini_set('max_execution_time', 0);	
	ini_set('memory_limit', -1);
	
	require(__DIR__ . '/phpQuery/phpQuery.php');


	class RssWorker {
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
				$category = trim($group['h3 > a']->text());

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
							'url' => $anchor->attr('href')
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

			foreach ($doc['.newrss_left > li'] as $group) {
				$group = pq($group);
				$category = $group->html();
				$category = trim(substr($category, 0, strpos($category, ' ')));

				if ($category !== '中國時報' && $category !== '工商時報') {
					continue;
				}

				foreach ($group['.rssli'] as $li) {
					$li = pq($li);
					$label = trim($li['span']->eq(0)->text());

					if ($label !== '焦點要聞' &&
						$label !== '生活新聞' &&
						$label !== '社會新聞' &&
						$label !== '兩岸國際' &&
						$label !== '財經焦點' &&
						$label !== '時論廣場' &&
						$label !== '地方新聞' &&
						$label !== '工商總覽' &&
						$label !== '財經要聞' &&
						$label !== '全球財經' &&
						$label !== '產業．科技') {
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

		private function libertytimes () {
			$doc = phpQuery::newDocument(file_get_contents('http://news.ltn.com.tw/service/8'));
			$map = array();

			foreach ($doc['.ltnrss tr']->slice(1) as $tr) {
				$tr = pq($tr);
				$td = $tr['td'];
				$label = $td->eq(0)->text();

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

	$sources = array('udn', 'chinatimes', 'libertytimes');

	foreach ($sources as $source) {
		$pid = pcntl_fork();

		if ($pid === 0) {
			(new RssWorker($source))->run();
			die();
		}
	}

	while (pcntl_wait($status) !== -1);

	$map = array();

	foreach ($sources as $source) {
		$map[$source] = json_decode(file_get_contents(__DIR__ . '/temp/r.' . $source), true);
	}

	file_put_contents(__DIR__ . '/rssMap.json', json_encode($map));


	$spent_time = time() - $start_time;

	echo "Spent: $spent_time sec\n";
?>
