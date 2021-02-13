<?php

require('../api.php');

// Determine API function 
switch ($_GET['q']) {
	case 'all': output(all()); break;
	case 'ep': output(endpoint()); break;
	case 'fun': output(fun()); break;
	case 'd': output(shortcut()); break;
	default: http_error(400, "the requested function \"$_GET[q]\" doesn't exist"); break;
}

// --------------
// API functions
// --------------

function all() {
	// Find all markdown files in the docs directory
	$files = array_filter(scandir('../docs'), function($v) { return strcmp(substr($v, -3), '.md') == 0; });

	// In every file, the first lines contain a short description of the endpoint
	return array_map(function($f) {
		$file = file("../docs/$f");
		$desc = "";

		//Start at the second line
		for($i = 1; substr($file[$i], 0, 2) != '##'; $i++) {
			$desc .= trim($file[$i]) . " ";
		}
		return array(
			'name' => substr($f, 0, -3),
			'desc' => trim($desc)
		);
	}, $files);
}

function endpoint() {
	// If called from the shortcut, the requested endpoint stands in the original URL
	if ($_GET['u']) {
		$file = docfile(explode('/', $_GET['u'])[0]);
	} else { // If called directly, the requested endpoint is a GET parameter
		$file = docfile(require_param($_GET['endpoint']));
	}

	$i = 0;
	$functions = array();

	// Find the overview section
	while ($file[$i] && substr($file[$i], 0, 11) != '## Overview') { $i++; }
	$i++;

	// Every line of the overview section contains an API function and a short description
	while (strcmp($file[$i][0], '*') == 0) {
		$colon = strpos($file[$i], ':'); // A colon seperates the name and description
		array_push($functions, array(
			'name' => substr($file[$i], 3, $colon - 4),
			'desc' => substr($file[$i], $colon + 2, -1)
		));
		$i++;
	}

	return $functions;
}

function fun() {
	// If called from the the shortcut, the requested endpoint and function stand in the original URL
	if ($_GET['u']) {
		$u = explode('/', $_GET['u']);
		$file = docfile($u[0]);

		// The shortcut URL is the same as the URL of the API function,
		// so you have to find the section about it first
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
	while ($file[$i] && strcmp(substr($file[$i], 0, $l), "### $fun") != 0) { $i++; }
	if (!$file[$i]) { http_error(400, "The documentation to the function $fun doesn't exist in this endpoint."); }
	$i++;

	$desc = "";
	while (strcmp(substr($file[$i], 0, 1), '*') != 0) {
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
		case 0:
			return all();
		case 1: 
			if (strlen($shortcut[0]) == 0) { return all(); }
			return endpoint();
		default:
			if (strlen($shortcut[1]) == 0) { return endpoint(); }
			return fun();
	}
}

// --------------
// Helpers
// --------------

function docfile($endpoint) {
	$file = file("../docs/$endpoint.md");
	if (!$file) { http_error(400, "The documentation of the endpoint \"$endpoint\" doesn't exist."); }
	return $file;
}

function multilineAttribute($file, &$i, $name) {
	$params = array();
	while ($file[$i+1][0] == "\t") {
		$i++;
		$star = strpos($file[$i], '*');
		$colon = strpos($file[$i], ':');
		
		if (!$star) {
			if (strcmp(trim($file[$i]), "") == 0) { continue; }

			$last =& $params;
			while (array_key_last($last) != 'desc') {
				$last =& $last[array_key_last($last)];
			}
			$last['desc'] .= " " . trim($file[$i]);
		}
		else {
			$push =& $params;
			for ($j = 1; $j < $star; $j++) {
				$push =& $push[array_key_last($push)]['params'];
			}
			if (!isset($push)) { $push = array(); }
			array_push($push, array(
				$name => substr($file[$i], $star + 3, $colon - $star - 4),
				'desc' => substr($file[$i], $colon + 2, -1)
			));
		}
	}
	return $params;
}

function findFun($file) {
	$i = 0;
	while ($file[$i] && substr($file[$i], 0, 13+strlen($_GET['u'])) != "*\tURI: `/api/" . $_GET['u']) { $i++; }
	if (!$file[$i]) { http_error(400, "The documentation to the function $_GET[u] doesn't exist in this endpoint."); }
	while (substr($file[$i], 0, 3) != '###') { $i--; }
	return substr($file[$i], 4, -1);
}
?>