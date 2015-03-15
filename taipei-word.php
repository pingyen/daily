<?php
	ini_set('max_execution_time', 0);	
	ini_set('memory_limit', -1);
	ini_set('date.timezone', 'Asia/Taipei');

	require(__DIR__ . '/PHPWord/src/PhpWord/Autoloader.php');

	\PhpOffice\PhpWord\Autoloader::register();


	$mtime = filemtime(__DIR__ . '/groups/taipei.json');

	$time = null;

	if (isset($_GET['t'])) {
		$time = $_GET['t'];

		if ($time !== 'morning' && $time !== 'evening') {
			$time = null;
		}
	}

	$file = 'taipei' . ($time === null ? '' : "-$time") . '.docx';
	$cachePath = __DIR__ . '/cache/' . $file;

	if (file_exists($cachePath) !== true || filemtime($cachePath) <= $mtime || (isset($_GET['c']) === true && intval($_GET['c']) === 0)) {
		$sourceMap = array(
				'udn' => array(
						'title' => '聯合報',
						'link' => 'http://udn.com'
					),
				'edn' => array(
						'title' => '經濟日報',
						'link' => 'http://udn.com'
					),
				'uen' => array(
						'title' => '聯合晚報',
						'link' => 'http://udn.com'
					),
				'chinatimes' => array(
						'title' => '中國時報',
						'link' => 'http://www.chinatimes.com'
					),
				'commercialtimes' => array(
						'title' => '工商時報',
						'link' => 'http://www.chinatimes.com'
					),
				'libertytimes' => array(
						'title' => '自由時報',
						'link' => 'http://www.ltn.com.tw'
					),
				'appledaily' => array(
						'title' => '蘋果日報',
						'link' => 'http://www.appledaily.com.tw'
					)
			);

		$articles = json_decode(file_get_contents(__DIR__ . '/articles.json'), true);


		$phpWord = new \PhpOffice\PhpWord\PhpWord();
		$section = $phpWord->addSection();


		$phpWord->addTitleStyle('ttStyle', array('bold' => true, 'size' => 24, 'name' => 'Heiti TC'), array('spaceAfter' => 160));

		if ($time === null) {
			$label = '報紙';
		}
		else if ($time === 'morning') {
			$label = '日報';
		}
		else {
			$label = '晚報';
		}

		$section->addTitle('台北市政府' . date(' Y 年 n 月 j 日', $mtime) . $label . '新聞', 'ttStyle');

		$phpWord->addTitleStyle('ttStyle2', array('bold' => true, 'size' => 20, 'name' => 'Heiti TC'), array('spaceBefore' => 240, 'spaceAfter' => 240));
		$phpWord->addLinkStyle('lnStyle', array('size' => 14, 'color' => '000000', 'name' => 'Heiti TC'));
		$cellStyle = array('borderSize' => 6, 'borderColor' => '000000', 'cellMargin' => 80);

		if ($time !== 'evening') {
			$section->addTitle('報紙頭條', 'ttStyle2');
			$table = $section->addTable();

			foreach (json_decode(file_get_contents(__DIR__ . '/headlines.json'), true) as $key => $article) {
				$source = $sourceMap[$key];
				$table->addRow(200);
				$table->addCell(1400, $cellStyle)->addLink(htmlspecialchars($source['link']), htmlspecialchars($source['title']), 'lnStyle');
				$table->addCell(7200, $cellStyle)->addLink(htmlspecialchars($article['link']), htmlspecialchars($article['title']), 'lnStyle');
			}

			$section->addTextBreak();
		}

		$section->addTitle('其它要聞', 'ttStyle2');
		$count = 0;

		$table = $section->addTable('tbStyle');

		foreach (json_decode(file_get_contents(__DIR__ . '/groups/taipei-negative' . ($time === null ? '' : "-$time") . '.json'), true) as $map) {
			if ($count === 12) {
				break;
			}

			++$count;

			$n = log(count($map));

			if ($n < 1) {
				$n = 1;
			}

			$map = array_slice($map, 0, $n, true);

			$table->addRow(200);
			$cell = $table->addCell(1400, $cellStyle);
			$cell2 = $table->addCell(7200, $cellStyle);

			foreach(array_keys($map) as $key) {
				$article = $articles[$key];
				$source = $sourceMap[$article['source']];
				$cell->addLink(htmlspecialchars($source['link']), htmlspecialchars($source['title']), 'lnStyle');
				$cell2->addLink(htmlspecialchars($article['link']), htmlspecialchars($article['title']), 'lnStyle');
			}
		}

		$section->addTextBreak();

		$taipei = json_decode(file_get_contents(__DIR__ . '/groups/taipei' . ($time === null ? '' : "-$time") . '.json'), true);

		$section->addTitle('台北市新聞', 'ttStyle2');
		$table = $section->addTable('tbStyle');

		foreach ($taipei as $map) {
			$table->addRow(200);
			$cell = $table->addCell(1400, $cellStyle);
			$cell2 = $table->addCell(7200, $cellStyle);

			foreach(array_keys($map) as $key) {
				$article = $articles[$key];
				$source = $sourceMap[$article['source']];
				$cell->addLink(htmlspecialchars($source['link']), htmlspecialchars($source['title']), 'lnStyle');
				$cell2->addLink(htmlspecialchars($article['link']), htmlspecialchars($article['title']), 'lnStyle');
			}
		}

		$section->addTextBreak();

		$section->addTitle('台北市新聞內文', 'ttStyle2');

		$kindMap = array(
				'news' => '報導',
				'editorial' => '社論',
				'opinion' => '投書'
			);

		$dept = json_decode(file_get_contents(__DIR__ . '/taipei-dept.json', true));

		$phpWord->addFontStyle('fnStyle', array('size' => 10, 'name' => 'Heiti TC'));
		$phpWord->addFontStyle('fnStyle2', array('size' => 14, 'name' => 'Heiti TC'));

		$m = count($taipei) - 1;
		$i = 0;

		foreach ($taipei as $map) {
			$table = $section->addTable();
			$keys = array_keys($map);

			foreach($keys as $key) {
				$article = $articles[$key];
				$source = $sourceMap[$article['source']];
				$table->addRow(200);
				$table->addCell(1400, $cellStyle)->addLink(htmlspecialchars($source['link']), htmlspecialchars($source['title']), 'lnStyle');
				$table->addCell(5400, $cellStyle)->addLink(htmlspecialchars($article['link']), htmlspecialchars($article['title']), 'lnStyle');

				$cell = $table->addCell(1200, $cellStyle);

				if (isset($article['authors'])) {
					foreach($article['authors'] as $author) {
						$cell->addText($author, 'fnStyle');
					}
				}

				$table->addCell(600, $cellStyle)->addText($kindMap[$article['kind']], 'fnStyle');
			}

			$table->addRow(200);
			$table->addCell(1400, $cellStyle)->addText('相關局處', 'fnStyle2');

			$depts = array();

			foreach ($dept as $key => $patterns) {
				if (match(content($article), $patterns) === true) {
					$depts[] = $key;
				}
			}

			$table->addCell(7200, $cellStyle + array('gridSpan' => 3))->addText(implode('、', $depts), 'fnStyle2');

			$table->addRow(200);
			$cell = $table->addCell(8600, $cellStyle + array('gridSpan' => 4));

			foreach($keys as $key) {
				$article = $articles[$key];
				$source = $sourceMap[$article['source']];

				$cell->addTextBreak();
				$cell->addLink(htmlspecialchars($article['link']), htmlspecialchars($source['title'] . ' - ' .$article['title']), 'lnStyle');
				$cell->addTextBreak();

				foreach(explode("\n\n", $article['content']) as $p) {
					$cell->addText(htmlspecialchars($p), 'fnStyle');
					$cell->addTextBreak();				
				}
			}

			if ($i !== $m) {
				$section->addPageBreak();
			}

			++$i;
		}

		$objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord);
		$objWriter->save($cachePath);
	}

	header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
	header('Content-Disposition: attachment; filename="' . $file . '"');
	readfile($cachePath);

	require_once(__DIR__ . '/common/preProcess.php');

	function content (&$article) {
		if (isset($article['content2'])) {
			return $article['content2'];
		}

		$content = $article['title'] . "\n" . $article['content'];

		if (isset($article['set'])) {
			$content = $article['set'] . "\n" . $content;
		}

		if (isset($article['caption'])) {
			$content = $article['caption'] . "\n" . $content;
		}

		$content = preProcess($content);

		$article['content2'] = $content;

		return $content;
	}

	function match ($content, $patterns) {
		foreach ($patterns as $positive => $negatives) {
			if (preg_match_all($positive . 'u', $content, $matches)) {
				if ($negatives === null) {
					return true;
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
						return true;
					}
				}
			}
		}

		return false;
	}
?>
