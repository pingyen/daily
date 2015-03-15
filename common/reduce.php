<?php
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
?>
