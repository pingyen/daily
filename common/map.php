<?php
	function map ($keywords, $skips, $size, $common) {
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

			for ($j = $i + 1; $j < $n; ++$j) {
				if (isset($skips[$j])) {
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
