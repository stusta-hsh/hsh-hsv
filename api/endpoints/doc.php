<?php

require('../api.php');

// Verwertung der Eingabe
switch ($_GET['q']) {
	case 'endpoints': output(endpoints()); break;
	case 'functions': output(functions()); break;
	case 'doc': output(doc()); break;
	default: break;
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

}

function doc() {

}

?>