<?php
	if (isset($_SERVER['PHP_AUTH_USER']) === false || $_SERVER['PHP_AUTH_USER'] !== 'taipeitaiwan' || $_SERVER['PHP_AUTH_PW'] !== 'kpcityooo') {
        header('WWW-Authenticate: Basic realm="Vexed.Me"');
    	header('HTTP/1.0 401 Unauthorized');
	    die();
	}

	$picks = isset($_GET['a']) ? explode(',', $_GET['a']) : array();
	$size = isset($_GET['k']) ? intval($_GET['k']) : 400;
?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1" >
<meta charset="UTF-8" >
<title>Daily Debug</title>
<!--[if lt IE 9]>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html5shiv/3.7.2/html5shiv-printshiv.min.js" ></script>
<![endif]-->
<link href="https://cdnjs.cloudflare.com/ajax/libs/normalize/3.0.2/normalize.min.css" rel="stylesheet" >
<style>
	body {
		background: #EEE;
	}

	body > header {
		margin: 12px;
	}

	body > header > h1 {
		display: inline-block;
		margin: 0;
	}

	body > table {
		background: #FFF;
		border-collapse: collapse;
		margin: 12px;
	}

	body > table tr > td {
		line-height: 1.4;
		padding: 6px 8px;
	}

	body > table tr > .index {
		width: 30px;
	}

	body > table tr > .source {
		width: 70px;
	}

	body > table tr > .title {
		width: 350px;
	}

	body > table tr > .authors {
		width: 110px;
	}

	body > table tr > .mode {
		width: 80px;
	}

	body > table tr > .about {
		width: 80px;
	}

	body > table tr > .score {
		width: 40px;
	}

	body > table tr > .order {
		width: 40px;
	}
</style>
</head>
<body>
<?php
	$sourceMap = array(
			'udn' => '聯合報',
			'uen' => '聯合晚報',
			'edn' => '經濟日報',
			'chinatimes' => '中國時報',
			'commercialtimes' => '工商時報',
			'libertytimes' => '自由時報',
			'appledaily' => '蘋果日報'
		);

	$articles = json_decode(file_get_contents(__DIR__ . '/articles.json'), true);
	$keywords = json_decode(file_get_contents(__DIR__ . '/keywords.json'), true);
?>
<header>
	<h1>Daily Debug 3</h1>
</header>
<?php
	require_once(__DIR__ . '/common/reduce.php');

	foreach ($picks as $key) {
		$article = $articles[$key];
		$keywords2 = reduce($keywords[$key], $size);

		$common = isset($common) === true ?
			array_intersect($common, array_keys($keywords2)) :
			array_keys($keywords2);

		$snippets = array();

		foreach ($keywords2 as $keyword => $score){
			$snippets[] = "$keyword $score";
		}
?>
<table>
	<tr>
		<td class="index" ><?php echo $key ?></td>
		<td class="source" ><?php echo $sourceMap[$article['source']] ?></td>
		<td class="title" ><a href="<?php echo $article['link'] ?>" target="_blank" ><?php echo $article['title'] ?></a></td>
		<td class="authors" ><?php if (isset($article['authors'])) { echo implode(', ', $article['authors']); } ?></td>
		<td class="mode" ><?php if (isset($article['mode'])) { echo $article['mode']; } ?></td>
		<td class="about" ><?php if (isset($article['about'])) { echo $article['about']; } ?></td>
		<td class="score" ><?php echo $article['score']; ?></td>
		<td class="order" ><?php echo $article['order']; ?></td>
	</tr>
	<tr>
		<td class="keyword" colspan="8" ><?php echo implode(', ', $snippets); ?></td>
	</tr>
</table>
<?php
	}

	if (isset($common)) {
?>
<table>
	<tr>
		<td><?php echo count($common) ?></td>
		<td><?php echo implode(', ', $common) ?></td>
	</tr>
</table>
<?php
	}
?>
</body>
</html>
