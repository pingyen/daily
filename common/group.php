<?php
	function group ($map) {
		$groups = array();

		foreach ($map as $parent => $children) {
			$ref =& $groups[];
			$ref = $children;
			$ref[$parent] = true;
		}

		while (true) {
			$groups2 = array();
			$n = count($groups);
			$m = $n - 1;

			for ($i = 0; $i < $m; ++$i) {
				if (isset($groups[$i]) === false) {
					continue;
				}

				for ($j = $i + 1; $j < $n; ++$j) {
					if (isset($groups[$j]) === false) {
						continue;
					}

					$group = $groups[$i];
					$group2 = $groups[$j];
					$group3 = $group + $group2;

					if (count($group3) < count($group) + count($group2)) {
						$groups2[] = $group3;
						unset($groups[$i]);
						unset($groups[$j]);
						break;
					}
				}
			}

			for ($i = 0; $i < $n; ++$i) {
				if (isset($groups[$i]) === false) {
					continue;
				}

				$groups2[] = $groups[$i];
			}

			if ($n === count($groups2)) {
				break;
			}

			$groups = $groups2;
		}

		return $groups2;
	}
?>
