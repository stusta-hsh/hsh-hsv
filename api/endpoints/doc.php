<?php

require('../api.php');

// Verwertung der Eingabe
switch ($_GET['q']) {
	case 'all': output(all()); break;
	case 'ep': output(endpoint()); break;
	case 'fun': output(fun()); break;
	case 'd': output(shortcut()); break;
	default: http_error(400, "the requested function \"$_GET[q]\" doesn't exist"); break;
}

// --------------
// API-Funktionen
// --------------

function all() {
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

function endpoint() {
	if ($_GET['u']) {
		$file = docfile(explode('/', $_GET['u'])[1]);
	} else {
		$file = docfile(require_param($_GET['endpoint']));
	}

	// Find overview section
	$i = 0;
	$functions = array();
	while ($file[$i] && substr($file[$i], 0, 11) != '## Overview') { $i++; }
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

function fun() {
	if ($_GET['u']) {
		$u = explode('/', $_GET['u']);
		$file = docfile($u[1]);
		$fun = findFun($file);
	} else {
		$file = docfile(require_param($_GET['endpoint']));
		$fun = require_param($_GET['function']);
	}

	$output = array(
		'name' => $fun
	);

	// Find the section about the required function
	$i = 0; $l = strlen($fun) + 4;
	while ($file[$i] && substr($file[$i], 0, $l) != "### $fun") { $i++; }
	if (!$file[$i]) { http_error(400, "The function $fun doesn't exist in this endpoint."); }
	$i++;

	$desc = "";
	while (substr($file[$i], 0, 1) != '*') {
		$desc .= substr($file[$i], 0, -1) . " ";
		$i++;
	}
	$output['desc'] = substr($desc, 0, -1);

	while (trim($file[$i]) != '') {
		$colon = strpos($file[$i], ':');
		switch (substr($file[$i], 2, $colon - 2)) {
			case 'URI': $output['uri'] = substr($file[$i], $colon + 3, -2); break;
			case 'Method': $output['method'] = substr($file[$i], $colon + 3, -2); break;
			case 'Authorisation': $output['auth'] = substr($file[$i], $colon + 2, -1); break;
			case 'Parameters': $output['params'] = multilineAttribute($file, $i, 'name'); break;
			case 'Returns': $output['returns'] = multilineAttribute($file, $i, 'code'); break;
			default: break;
		}
		$i++;
	}

	return $output;
}

function shortcut() {
	$shortcut = explode('/', $_GET['u']);
	switch (count($shortcut)) {
		case 1:
			return all();
		case 2: 
			if (strlen($shortcut[1]) == 0) { return all(); }
			return endpoint();
		default:
			if (strlen($shortcut[2]) == 0) { return endpoint(); }
			return fun();
	}
}

// --------------
// Helpers
// --------------

function docfile($endpoint) {
	$file = file("../docs/$endpoint.md");
	if (!$file) { http_error(400, "The endpoint \"$endpoint\" doesn't exist."); }
	return $file;
}

function multilineAttribute($file, &$i, $name) {
	$params = array();
	while ($file[$i+1][0] == "\t") {
		$i++;
		if ($file[$i][1] != "*") {
			$params[array_key_last($params)]['desc'] .= " " . substr($file[$i], 2, -1);
			continue;
		}
		$colon = strpos($file[$i], ':');
		array_push($params, array(
			$name => substr($file[$i], 4, $colon - 5),
			'desc' => substr($file[$i], $colon + 2, -1)
		));
	}
	return $params;
}

function findFun($file) {
	$i = 0;
	while ($file[$i] && substr($file[$i], 0, 12+strlen($_GET['u'])) != "*\tURI: `/api" . $_GET['u']) { $i++; }
	if (!$file[$i]) { http_error(400, "The function $_GET[u] doesn't exist in this endpoint."); }
	while (substr($file[$i], 0, 3) != '###') { $i--; }
	return substr($file[$i], 4, -1);
}
?>