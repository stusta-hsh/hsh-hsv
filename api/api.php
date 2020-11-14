<?php

require('db.php');

// ------------
// IO-Functions
// ------------

function output($obj) {
	header('Content-Type: application/json');
	echo(json_encode($obj));
}

function require_param($param) {
	if ($param) { return $param; }
	else {
		http_error(400, "The request misses a required argument.");
	}
}

function param_post() {
	$post = json_decode(file_get_contents('php://input'), true);
	
	if (json_last_error() == JSON_ERROR_NONE) { return $post; }
	else {
		http_error(400, "JSON decode error: " . json_last_error_msg());
	}
}

function http_error($code, $msg) {
	http_response_code($code);
	echo($msg);
	exit();
}

// -----------------------
// Authorization functions
// -----------------------

// we need to use the parent directory here, because this line gets invoked from one directory below (endpoints)
$roletree = json_decode(file_get_contents('../roles.json'), TRUE);

function authenticate() {
	session_name('hshsession');
	session_start();
	if ($_SESSION['id']) {
		return $_SESSION['id'];
	}
	else {
		http_error(401, "User needs to be logged in for that action");
	}
}

function authorize($scope) {
	$user = authenticate();												// identify the user
	$date = date('Y-m-d');
	$roles = roles(q_firstColumn(										// identify all roles of the user
		"SELECT u.role FROM user_roles u WHERE user = $user AND '$date' BETWEEN u.start AND u.end"));

	foreach ($roles as $role) { if ($role == $scope) { return true; } }	// Does one of the roles match with the required one?
	return false;														// If no role does, the user isn't authorized
}

function roles($roles) {
	if ($roles == null) { return array(); }
	global $roletree;
	$newroles = array();
	foreach ($roles as $role) {
		$newroles[] = $role;
		if (!array_key_exists('is-a', $roletree[$role])) { continue; }
		$newroles = array_merge($newroles, roles($roletree[$role]['is-a']));
	}
	return array_unique($newroles, SORT_NUMERIC);
}
?>