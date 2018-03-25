<?php
	ini_set('max_execution_time', 0);
	ini_set('memory_limit', -1);
	ini_set('date.timezone', 'Asia/Taipei');

	require_once(__DIR__ . '/phpQuery/phpQuery.php');


	class RssWorker {
		private $source;
		private $rss;
		private $key;

		public function __construct ($source, $rss, $key) {
			$this->source = $source;
			$this->rss = $rss;
			$this->key = $key;
		}

		public function run () {
			file_put_contents(__DIR__ . '/temp/r2.' . $this->key, json_encode($this->postProcess($this->filter($this->fetch()))));
		}

		private function meta () {
			$rss = $this->rss;
			$source = $this->source;
			$label = $rss['label'];

			if ($source === 'chinatimes') {
				if ($rss['category'] === '工商時報') {
					$source = 'commercialtimes';
				}

				$kind = $label === '時論廣場' ? 'opinion' : 'news';
				$category = $label;
			}
			else if ($source === 'appledaily') {
				$category = $rss['category'];

				if ($category === '論壇與專欄總覽') {
					$kind = $label === '蘋論' ? 'editorial' : 'opinion';
				}
				else {
					$kind = 'news';
				}

				$subcategory = $label;
			}
			else if ($source === 'libertytimes') {
				$kind = $label === '言論' ? 'opinion' : 'news';
				$category = $label;
			}
			else if ($source === 'udn') {
				$category = $rss['category'];

				if ($category === '評論') {
					$kind = $label === '社論' ? 'editorial' : 'opinion';
				}
				else {
					$kind = 'news';
				}

				switch ($category) {
					case '要聞':
						if ($label === '政治' ||
							$label === '綜合') {
							$subcategory = $label;
						}
						else {
							$set = $label;
						}
						break;
					case '社會':
						if ($label === '重案追緝' ||
							$label === '意外現場' ||
							$label === '情慾犯罪' ||
							$label === '利字當頭' ||
							$label === '冷暖人間' ||
							$label === '法律前線' ||
							$label === '社會萬象') {
							$subcategory = $label;
						}
						else {
							$set = $label;
						}
						break;
					case '地方':
						if ($label === '台灣百寶鄉' ||
							$label === '大台北' ||
							$label === '桃竹苗' ||
							$label === '中彰投' ||
							$label === '雲嘉南' ||
							$label === '高屏離島' ||
							$label === '基宜花東') {
							$subcategory = $label;
						}
						else {
							$set = $label;
						}
						break;
					case '全球':
						if ($label === '國際焦點' ||
							$label === '國際財經' ||
							$label === '國際萬象' ||
							$label === '美國新聞' ||
							$label === '全球觀點') {
							$subcategory = $label;
						}
						else {
							$set = $label;
						}
						break;
					case '兩岸':
						if ($label === '兩岸要聞') {
							$subcategory = $label;
						}
						else {
							$set = $label;
						}
					case '娛樂':
						$set = $label;
						break;
					default:
						$subcategory = $label;
				}
			}

			$meta = array(
					'source' => $source,
					'kind' => $kind,
					'category' => $category
				);

			if (isset($subcategory)) {
				$meta['subcategory'] = $subcategory;
			}

			if (isset($set)) {
				$meta['set'] = $set;
			}

			return $meta;
		}

		private function fetch () {
			$rss = $this->rss;
			$url = $rss['url'];

			try {
				$doc = phpQuery::newDocumentXML(str_replace(array('', '', '&'), array('', '', '&amp;'), file_get_contents($url)));
			} catch (Exception $e) {
				echo "Loading RSS Fialed: $url\n";
				$doc = array('channel item' => array());
			}

			$articles = array();
			$meta = $this->meta();
			$expire = time() - 86400;

			foreach($doc['channel item'] as $item) {
				$item = pq($item);
				$pubDate = $item['pubDate']->eq(0)->text();

				if (is_numeric($pubDate)) {
					$timestamp = intval($pubDate);
				}
				else {
					$timestamp = strtotime(str_replace(array('年', '月', '日'), array('/', '/', ''), $pubDate));
				}

				if ($timestamp < $expire) {
					continue;
				}

				$description = htmlspecialchars_decode(htmlspecialchars_decode(str_replace(array('<![CDATA[', ']]>'), '', $item['description']->html())));

				$image = $item['image url']->eq(0)->text();

				if ($image !== '') {
					$description .= '<br><img src="' . $image . '">';
				}

				$articles[] = array_merge($meta, array(
						'title' => html_entity_decode($item['title']->eq(0)->text()),
						'link' => $item['link']->eq(0)->text(),
						'timestamp' => $timestamp,
						'description' => $description
					));
			}

			usort($articles, function ($a, $b) {
					return strcmp($a['link'], $b['link']);
				});

			return $articles;
		}

		private function filter ($articles) {
			$articles2 = array();

			foreach ($articles as $article) {
				if ($article['source'] === 'libertytimes' && strpos($article['link'], '/paper/') === false) {
					continue;
				}

				$articles2[] = $article;
			}

			return $articles2;
		}

		private function postProcess ($articles) {
			foreach ($articles as &$article) {
				$source = $article['source'];
				$link = $article['link'];
				$description = $article['description'];

				if (substr($link, 0, 31) === 'http://feedproxy.google.com/~r/') {
					$description = preg_replace('/<img src="(https?:)?\/\/feeds.feedburner.com\/~r\/[^>]+>/', '', $description);
				}

				if ($article['kind'] === 'opinion') {
					switch ($source) {
						case 'chinatimes':
							if (substr($article['title'], 0, 9) === '社論－') {
								$article['kind'] = 'editorial';
							}
							break;
						case 'libertytimes':
							if (substr($article['title'], 0, 12) === '〈社論〉') {
								$article['kind'] = 'editorial';
							}
					}
				}

				switch ($source) {
					case 'commercialtimes':
					case 'chinatimes':
						$description = str_replace(array(
							'<img src="http://www.chinatimes.com/images/rss-logo-ctoppa.png">', '<img src="http://www.chinatimes.com/images/logo-1200x635.jpg">'), '', $description) . '...';
						break;
					case 'udn':
						$description = preg_replace(array(
							'/<div class="photo_pop">.*?<\/div>/s',
							'/<div class="video-container">.*?<\/div>/s',
							'/<link href="[^>]+>/'), '', $description);

						$description = str_replace(array('<h4>', '</h4>', '<a href="####" class="photo_pop_icon">分享</a>', '...'), array('<div>', '</div>', '', ''), $description);

						$description = preg_replace(
								'/<img src="http:\/\/pgw.udn.com.tw\/gw\/photo.php\?u=([^&]*)&[^"]*">/',
								'<img src="$1">',
								$description
							);

						break;
					case 'appledaily':
						$description = preg_replace('/(?:<br>)+<a href="http:\/\/www.appledaily.com.tw\/appledaily\/article\/.*?$/i', '', $description);
						$description = '<p>' . str_replace('<br>', '</p><p>', $description) . '</p>';
				}

				$description = preg_replace(
						array(
							'/　/',
							'/<!--(.*?)-->/s',
							'#<img([^>]*) src=["\']http://([^"\']*)["\']([^>]*)>#',
							'/\.\.\./',
							'/…/u'
						), 
						array(
							' ',
							'',
							'<img$1 src="https://i1.wp.com/$2"$3>',
							' ... ',
							' ... '
						),
						$description
					);

				$tidy = new tidy();
				$description = $tidy->repairString($description, array('show-body-only' => true), 'utf8');
				$article['description'] = $description;
			}

			return $articles;
		}
	}

	class ArticleWorker {
		private $article;
		private $key;
		private $html;

		public function __construct ($article, $key) {
			$this->article = $article;
			$this->key = $key;
		}

		public function run () {
			file_put_contents(__DIR__ . '/temp/a.' . $this->key, json_encode($this->parse($this->fetch())));
		}

		private function fetch() {
			$article = $this->article;

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $article['link']);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$html = curl_exec($ch);

			$source = $article['source'];

			if ($source === 'udn') {
				if (preg_match('/>window.location.href="(http[^"]+)"/', $html, $matches)) {
					curl_setopt($ch, CURLOPT_URL, $matches[1]);
					$html = curl_exec($ch);
				}
			}

			$link = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

			if (($pos = strpos($link, 'utm_source')) !== false) {
				$link = substr($link, 0, $pos - 1);
			}

			if ($source === 'appledaily') {
				if (substr($link, -2) === '//') {
					$link = substr($link, 0, -2);
				}
			}

			$article['link'] = $link;

			return $html;
		}

		private function parse ($html) {
			$source = $this->article['source'];
			return $this->$source($html);
		}

		private function udn ($html) {
			$article = $this->article;
			$doc = phpQuery::newDocument($html);
			$main = $doc['#story_body_content'];
			$main['script']->remove();


			$caption = array();

			foreach ($main['.photo_center, .photo_left, .photo_right'] as $div) {
				$div = pq($div);
				$caption[] = $div->find('h4')->text();
				$div->remove();
			}

			$caption = trim(implode("\n\n", $caption));

			if ($caption !== '') {
				$article['caption'] = $caption;
			}


			$content = array();

			foreach ($main->children('p, blockquote') as $block) {
				$block = pq($block);
				$text = trim($block->text());

				if ($text === '') {
					continue;
				}

				$content[] = $text;
			}

			$article['content'] = implode("\n\n", $content);


			$meta = $main['#story_bady_info .story_bady_info_author'];
			$meta['span']->remove();

			$pieces = explode(' ', trim($meta->text()));

			switch ($pieces[0]) {
				case '聯合報':
					break;
				case '聯合晚報':
					$article['source'] = 'uen';
					break;
				case '經濟日報':
					$article['source'] = 'edn';
					break;
				case '世界日報':
					$article['source'] = 'worldjournal';
					break;
				case '聯合新聞網':
				default:
					$article['source'] = 'udn.com';
			}

			if (isset($pieces[1])) {
				$meta = $pieces[1];
				$tokens = preg_split('/[／╱\/]+/u', $meta);
				$count = count($tokens);

				if ($count >= 3) {
					$authors = array($tokens[0]);
					$abouts = array();
					$m = $count - 1;

					for ($i = 1; $i < $m; ++$i) {
						$snippets = explode('、', $tokens[$i]);
						$abouts[] = $snippets[0];
						$authors[] = $snippets[1];
					}

					$abouts[] = $tokens[$m];
					$article['authors'] = $authors;
					$article['about'] = implode('、', $abouts);
				}
				else if ($count === 2) {
					$match = $tokens[0];
					$match2 = $tokens[1];

					if ($match === '文' || $match === '採訪整理') {
						$article['mode'] = $match;
						$article['authors'] = array($match2);
					}
					else {
						$match2 .= trim(implode(' ', array_slice($tokens, 2)));
						$category = $article['category'];

						if ($category === '評論' || substr($article['title'], 0, 15) === '財經觀點／') {
							$article['about'] = $match2;
						}
						else if ($category === '生活') {
							$article[preg_match('/(報導|編譯|電)$/u', $match2) ? 'mode' : 'about'] = $match2;
						}
						else {
							$article['mode'] = $match2;
						}

						if (preg_match('/(?:記者|特派員|編譯(?!(?:組|中心)))(.+)/u', $match, $matches)) {
							$match = $matches[1];
						}

						$authors = explode('、', $match);

						foreach ($authors as &$author) {
							$pos = strpos($author, '記者');

							if ($pos !== false) {
								$author = substr($author, $pos + 6);
							}

							$author = preg_replace(array('/編譯(?!(組|中心))/u'), '', $author);
						}

						$article['authors'] = $authors;
					}
				}
				else if ($meta === '本報訊' ||
  						 $meta === '台北訊' ||
						 $meta === '綜合報導' ||
						 $meta === '本報綜合報導' ||
						 $meta === '社論' ||
						 $meta === '聯合報社論' ||
						 $meta === '經濟日報社論' ||
						 $meta === '午後熱評' ||
						 $meta === '聯合晚報午後熱評' ||
						 $meta === '黑白集' ||
						 $meta === '聯合報黑白集') {
					$article['mode'] = $meta;
				}
				else if ($meta === '編譯') {
					$article['mode'] = $meta;
					$article['authors'] = array($pieces[2]);
				}
				else if ($meta === '記者') {
					$article['authors'] = array($pieces[2]);
				}
				else if (preg_match('/\d{1,2}日電/u', $meta)) {
					$article['mode'] = $meta;
				}
				else if (preg_match('/(?:今日登場：?|記者|特派員|作者：|編譯)(.+)/u', $meta, $matches)) {
					$match = $matches[1];
					$suffix = substr($match, -6);

					if ($suffix === '整理') {
						$article['mode'] = $suffix;
						$article['authors'] = array($match, 0, -6);
					}
					else {
						$article['authors'] = array($match);
					}
				}
				else if (preg_match('/(.+)，(.+)/u', $meta, $matches)) {
					$article['about'] = $matches[2];
					$article['authors'] = array($matches[1]);
				}
				else if (preg_match('/(.+)（(.+)）/u', $meta, $matches)) {
					$article['about'] = $matches[2];
					$article['authors'] = array($matches[1]);					
				}
				else {
					$article['authors'] = array($meta);
				}
			}

			return $article;
		}

		private function commercialtimes ($html) {
			return $this->chinatimes($html);
		}

		private function chinatimes ($html) {
			$article = $this->article;
			$start = strpos($html, '<body ');
			$doc = phpQuery::newDocument(substr($html, $start, strpos($html, '</body>') + 7 - $start));
			$main = $doc['.page_container article']->eq(0);


			$caption = array();

			foreach ($main['figcaption'] as $figcaption) {
				$figcaption = pq($figcaption);
				$caption[] = $figcaption->text();
			}

			$caption = implode("\n\n", $caption);

			if ($caption !== '') {
				$article['caption'] = $caption;
			}


			$content = array();

			foreach ($main['p'] as $p) {
				$p = pq($p);
				$text = trim($p->text());

				if ($text === '') {
					continue;
				}

				$content[] = $text;
			}

			$article['content'] = implode("\n\n", $content);


			$meta = $main['.rp_name']->eq(0)->text();

			if (($pos = strpos($meta, '　')) !== false) {
				if ($pos === 3) {
					$meta = substr($meta, 0, 3) . substr($meta, 6);
				}
				else {
					$meta = substr($meta, 0, $pos);
				}
			}

			if (substr($meta, 0, 3) === '（') {
				$authors = explode('、', substr($meta, 3, -3));

				foreach ($authors as &$author) {
					$pos = strpos($author, '：');

					if ($pos !== false) {
						$author = substr($author, $pos + 3);
					}
				}

				$article['authors'] = $authors;
			}
			else if (substr($meta, 0, 3) === '⊙') {
				$article['authors'] = array(substr($meta, 3));
			} 
			else if ($meta === '本報訊') {
				$article['mode'] = $meta;
			}
			else if ($count = preg_match_all('/([^／╱\/]+)[／╱\/]([^、\n]+)/u', $meta, $matches)) {
				if ($count > 1) {
					$article['authors'] = $matches[1];
					$article['mode'] = implode('、', $matches[2]);
				}
				else {
					$match = $matches[1][0];
					$match2 = $matches[2][0];

					if ($match === '報導') {
						$article['mode'] = $match;
						$match = $match2;
					}
					else {
						$article[$article['category'] === '時論廣場' ? 'about' : 'mode'] = $match2;
					}

					$article['authors'] = preg_split('/[ 、]/u', $match);
				}
			}
			else {
				$article['authors'] = array($meta);
			}

			return $article;
		}

		private function appledaily ($html) {
			$article = $this->article;
			$doc = phpQuery::newDocument(str_replace('<div class="ndArticle_margin">', '', $html));
			$main = $doc['.ndArticle_content'];


			$caption = array();

			foreach ($main->find('figcaption') as $element) {
				$element = pq($element);
				$text = $element->text();

				if ($text === '') {
					continue;
				}

				$caption[] = $text;
			}

			$caption = implode("\n\n", $caption);

			if ($caption !== '') {
				$article['caption'] = $caption;
			}


			$content = array();

			foreach ($main['h2, p'] as $p) {
				$p = pq($p);
				$p->find('style')->remove();
				$text = strip_tags(trim(str_replace('<br>', "\n", $p->html())));

				if ($text === '') {
					continue;
				}

				$content[] = $text;
			}

			$content = implode("\n\n", $content);
			$article['content'] = $content;


			if (preg_match('/（(.+)）/u', $article['title'], $matches)) {
				$article['authors'] = array($matches[1]);
				$ch = mb_substr($content, -1);

				if ($ch !== '。' && $ch !== '」' && $ch !== '！' && $ch !== '？') {
					$article['about'] = substr($content, strrpos($content, "\n\n") + 2);
				}
			}
			else if (preg_match('/【(.+)】/u', $content, $matches)) {
				$meta = $matches[1];

				if ($meta === '連線報導' ||
					$meta === '廣編特輯' ||
					$meta === '綜合報導' ||
					$meta === '特別企劃') {
					$article['mode'] = $meta;
				}
				else if (preg_match('/(.+)[╱╲\/](.+)/u', $meta, $matches)) {
					$article['mode'] = $matches[2];
					$article['authors'] = explode('、', $matches[1]);
				}
				else {
					$article['authors'] = array($meta);
				}
			}
			else if (preg_match_all('/(?:文|採訪)╱([^　˙• \n]+)/u', $content, $matches)) {
				$authors = array();

				foreach ($matches[1] as $match) {
					foreach (explode('、', $match) as $author) {
						if (($pos = strpos($author, '╱')) !== false) {
							$author = substr($author, $pos + 3);
						}

						$authors[] = $author;
					}
				}

				$article['authors'] = $authors;
			}
			else if (preg_match('/記者([^會表].+)/u', $content, $matches)) {
				$meta = $matches[1];

				if (preg_match('/([^採]+)(採訪整理)$/u', $meta, $matches)) {
					$article['mode'] = $matches[2];
					$article['authors'] = explode('、', trim($matches[1]));
				}
				else {
					$article['authors'] = explode('、', $meta);
				}
			}
			else {
				if (mb_strlen($content) > 10) {
					$content = mb_substr($content, -10);
				}

				$ch = mb_substr($content, -1);

				if ($ch !== '。' && $ch !== '」' && $ch !== '！' && $ch !== '？' && preg_match('/[。」！◎？]([^。」！◎？]+)$/u', $content, $matches)) {
					$meta = $matches[1];

					if (preg_match('/\((.+)\/(.+)\)/u', $meta, $matches)) {
						$article['mode'] = $matches[2];
						$article['authors'] = array($matches[1]);
					}
					else if (
						$meta === '翻攝網路' ||
						$meta === '法庭中心' ||
						$meta === '連線報導') {
						$article['mode'] = $meta;
					}
					else {
						$article['authors'] = array(str_replace(array('編譯', '記者'), '', $meta));
					}
				}
			}

			return $article;
		}

		private function libertytimes ($html) {
			$article = $this->article;
			$doc = phpQuery::newDocument($html);
			$main = $doc['.articlebody'];


			$caption = array();

			foreach ($main['.boxTitle li img'] as $img) {
				$img = pq($img);
				$caption[] = $img->attr('alt');
			}

			$caption = implode("\n\n", $caption);

			if ($caption !== '') {
				$article['caption'] = $caption;
			}


			$content = array();

			foreach ($main['.text']->children('h4, p') as $block) {
				$block = pq($block);
				$content[] = $block->text();
			}

			$content = implode("\n\n", $content);
			$article['content'] = $content;


			if (preg_match('/[〔﹝](.+)[〕﹞]/u', $content, $matches)) {
				$meta = $matches[1];

				if ($meta === '本報訊') {
					$article['mode'] = $meta;
				}
				else if (preg_match('/(.+)[／╱](.+)/u', $meta, $matches)) {
					$article['mode'] = $matches[2];
					$match = $matches[1];

					if (preg_match('/(?:記者|編譯|特派員)(.+)/u', $match, $matches)) {
						$match = preg_replace('/編譯/u', '', $matches[1]);
					}

					$article['authors'] = explode('、', $match);
				}
			}
			else if (preg_match('/(?:記者|文)(.+)／(.+)/u', $content, $matches)) {
				$article['mode'] = $matches[2];
				$article['authors'] = explode('、', preg_replace('/編譯/u', '', $matches[1]));
			}
			else if (preg_match('/◎(.+)/u', $content, $matches)) {
				$meta = trim($matches[1]);

				if (preg_match('/特派員(.+)/u', $meta, $matches)) {
					$meta = $matches[1];
				}

				$article['authors'] = array($meta);

				if (mb_strlen($content) > 60) {
					$content = mb_substr($content, -60);
				}

				if (preg_match('/（(.+)）/u', $content, $matches)) {
					$article['about'] = $matches[1];
				}
			}
			else {
				if (mb_strlen($content) > 120) {
					$content = mb_substr($content, -120);
				}

				if(preg_match('/（[^記]*記者([^）]+)）/u', $content, $matches)) {
					$article['authors'] = explode('、', $matches[1]);
				}
				else if (preg_match('/記者(.+)/u', $content, $matches)) {
					$article['authors'] = array($matches[1]);
				}
				else if (preg_match('/（([^）]+)）$/u', $content, $matches)) {
					$match = $matches[1];

					if (substr($match, 0, 6) === '編譯') {
						$article['authors'] = array(substr($match, 6));
					}
					else if (preg_match('/文：(.+)/u', $match, $matches)) {
						$article['authors'] = array(str_replace(array('編譯', '記者'), '', $matches[1]));
					}
					else if (preg_match('/作者([^為]+)為/u', $match, $matches)) {
						$article['authors'] = array($matches[1]);
						$article['about'] = $match;
					}
					else if (preg_match('/作者([^，]+)，(.+)/u', $match, $matches)) {
						$article['authors'] = array($matches[1]);
						$article['about'] = $matches[2];
					}
					else {
						$article['authors'] = array($match);
					}
				}
			}

			return $article;
		}
	}

	function dedup ($articles) {
		$articles2 = array();
		$map = array();
		$map2 = array();

		foreach ($articles as $article) {
			$link = $article['link'];
			$source = $article['source'];

			if ($source === 'appledaily') {
				$tokens = explode('/', $link);
				$tokens[5] = '*';
				$link = implode('/', $tokens);
			}

			if (isset($map[$link])) {
				continue;
			}

			$key = $source . '@' . $article['title'];

			if (isset($map2[$key])) {
				continue;
			}

			$map[$link] = true;
			$map2[$key] = true;
			$articles2[] = $article;
		}

		return $articles2;
	}

	function filter ($articles) {
		$articles2 = array();
		$sourceMap = array(
				'udn' => true,
				'edn' => true,
				'uen' => true,
				'chinatimes' => true,
				'commercialtimes' => true,
				'libertytimes' => true,
				'appledaily' => true
			);

		foreach ($articles as $article) {
			$source = $article['source'];

			if (isset($sourceMap[$source]) === false) {
				continue;
			}

			$title = $article['title'];

			if (preg_match('/^《TAIPEI TIMES 焦點》/u', $title) ||
				preg_match('/^《中英對照讀新聞》/u', $title) ||
				preg_match('/^中英對照讀新聞》/u', $title) ||
				preg_match('/^每日動一句/u', $title) ||
				preg_match('/^一(週|周)大事/u', $title) ||
				preg_match('/各報重點新聞一覽/u', $title) ||
				preg_match('/重要財經新聞一覽/u', $title) ||
				preg_match('/晚安新聞/u', $title) ||
				preg_match('/星座理財/u', $title) ||
				preg_match('/福利熊 蘋果樂園/u', $title) ||
				preg_match('/^《蘋果日報》最鄉民的影音頻道/u', $title)) {
				continue;
			}

			$articles2[] = $article;
		}

		return $articles2;
	}

	function score ($articles) {
		foreach ($articles as &$article) {
			$category = $article['category'];

			if (isset($article['subcategory'])) {
				$subcategory = $article['subcategory'];
			}

			switch ($article['source']) {
				case 'chinatimes':
				case 'commercialtimes':
					$international = 200;
					switch ($category) {
						case '焦點要聞':
							$key = '要聞';
							break;
						case '時論廣場':
							$key = '評論';
							break;
						case '兩岸國際':
							$key = '國際';
							break;
						case '財經焦點':
							$key = '財經';
							break;
						default:
							$key = substr($category, 0, 6);
					}
					break;
				case 'appledaily':
					$international = 2000;
					switch ($category) {
						case '財經總覽':
							$key = '財經';
							break;
						case '國際總覽':
							$key = '國際';
							break;
						case '論壇與專欄總覽':
							$key = '評論';
							break;
						default:
							switch ($subcategory) {
								case '頭條':
									$key = '要聞';
									break;
								case '地方綜合':
									$key = '地方';
									break;
								default:
									$key = $subcategory;
							}
					}
					break;
				case 'libertytimes':
					$international = 2000;
					switch ($category) {
						case '頭版':
							$key = '要聞';
							break;
						case '言論':
							$key = '評論';
							break;
						case '台北都會':
						case '北部新聞':
						case '中部新聞':
						case '南部新聞':
							$key = '地方';
							break;
						default:
							$key = $category;
					}
					break;
				case 'uen':
				case 'edn':
				case 'udn':
					$international = 500;
					switch ($category) {
						case '產經':
							$key = '財經';
							break;
						case '全球':
							$key = '國際';
							break;
						case '兩岸':
							$key = '中國';
							break;
						case '要聞':
							if (isset($subcategory) && $subcategory === '政治') {
								$key = $subcategory;
								break;
							}
						default:
							$key = $category;
					}
			}

			$scoreMap = array(
					'國際' => $international,
					'要聞' => 200,
					'財經' => 30,
					'政治' => 30,
					'社會' => 30,
					'地方' => 30,
					'生活' => 30,
					'中國' => 30,
					'評論' => 20,
					'投訴' => 20,
					'娛樂' => 10,
					'工商' => 2,
				);

			$article['score'] = isset($scoreMap[$key]) === true ? $scoreMap[$key] : 0;
		}

		return $articles;
	}

	function order ($articles) {
		$map = array();

		foreach ($articles as &$article) {
			$source = $article['source'];

			if (isset($map[$source])) {
				++$map[$source];
			}
			else {
				$map[$source] = 1;
			}

			$article['order'] = $map[$source];
		}

		return $articles;
	}



	$start_time = time();


	$max = 30;
	$count = 0;

	foreach (json_decode(file_get_contents(__DIR__ . '/rssMap.json'), true) as $source => $items) {
		foreach ($items as $item) {
			$pid = pcntl_fork();

			if ($pid === 0) {
				(new RssWorker($source, $item, $count))->run();
				die();
			}

			++$count;

			if ($count % $max === 0) {
				while (pcntl_wait($status) !== -1);
			}
		}
	}

	while (pcntl_wait($status) !== -1);


	$articles = array();

	for ($i = 0; $i < $count; ++$i) {
		$articles = array_merge($articles, json_decode(file_get_contents(__DIR__ . '/temp/r2.' . $i), true));
	}

	$articles = dedup($articles);


	$count = 0;

	foreach ($articles as $article) {
		$pid = pcntl_fork();

		if ($pid === 0) {
			(new ArticleWorker($article, $count))->run();
			die();
		}

		++$count;

		if ($count % $max === 0) {
			while (pcntl_wait($status) !== -1);
		}
	}

	while (pcntl_wait($status) !== -1);


	$articles = array();

	for ($i = 0; $i < $count; ++$i) {
		$articles[] = json_decode(file_get_contents(__DIR__ . '/temp/a.' . $i), true);
	}

	$articles = order(score(filter(dedup($articles))));


	file_put_contents(__DIR__ . '/articles.json', json_encode($articles));


	$spent_time = time() - $start_time;

	echo "Spent: $spent_time sec\n";
?>
