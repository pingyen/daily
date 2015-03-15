<?php
	function merge ($map, $map2) {
		foreach ($map2 as $parent => $children) {
			$ref =& $map[$parent];

			if (isset($ref)) {
				foreach (array_keys($children) as $child) {
					$ref[$child] = true;
				}
			}
			else {
				$ref = $children;
			}
		}

		return $map;
	}
?>
