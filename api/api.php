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

// -----------------------
// Authorization functions
// -----------------------

$roletree = json_decode(file_get_contents('roles.json'));

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
		$newroles = array_merge($newroles, roles($roletree->{$role}->{'is-a'}));
	}
	return array_unique($newroles, SORT_NUMERIC);
}

function http_error($code, $msg) {
	http_response_code($code);
	echo($msg);
	exit();
}

?>