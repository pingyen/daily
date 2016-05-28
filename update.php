<?php
	shell_exec('php rssMap.php');

	for ($i = 0; $i < 10; ++$i) {
		$result = shell_exec('php articles.php');

		if (substr($result, 0, 7) === 'Spent: ') {
			break;
		}
	}

	shell_exec('php keywords.php');
	shell_exec('php groups.php');
	shell_exec('php groups-taipei.php');
	shell_exec('php groups-economic.php');
	shell_exec('php headlines.php');
?>
