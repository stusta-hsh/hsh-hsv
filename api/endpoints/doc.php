<?php

require('../api.php');

// Verwertung der Eingabe
switch ($_GET['q']) {
	case 'endpoints': output(endpoints()); break;
	case 'functions': output(functions()); break;
	case 'doc': output(doc()); break;
	default: http_error(400, "the requested function \"$_GET[q]\" doesn't exist"); break;
}

// --------------
// API-Funktionen
// --------------

function endpoints() {
	$files = array_filter(scandir('../docs'), function($v) { return substr($v, -3) == '.md'; });
	return array_map(function($f) {
		$file = file("../docs/$f");
		$desc = "";
		for($i = 2; substr($file[$i], 0, 2) != '##'; $i++) {
			$desc .= substr($file[$i], 0, -1);
		}
		return array(
			'name' => substr($f, 0, -3),
			'desc' => $desc
		);
	}, $files);
}

function functions() {
	$endpoint = require_param($_GET['endpoint']);
	$file = file("../docs/$endpoint.md");
	if (!$file) { http_error(400, "The endpoint \"$endpoint\" doesn't exist."); }

	$i = 0;
	$functions = array();
	while (substr($file[$i], 0, 11) != '## Overview') { $i++; }
	$i++;

	while (substr($file[$i], 0, 1) == '*') {
		$colon = strpos($file[$i], ':');
		array_push($functions, array(
			'name' => substr($file[$i], 3, $colon - 4),
			'desc' => substr($file[$i], $colon + 2, -1)
		));
		$i++;
	}

	return $functions;
}

function doc() {

}

?>