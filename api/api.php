<?php

require('db.php');
$roletree = json_decode(file_get_contents('roles.json'));

function output($obj) {
	header('Content-Type: application/json');
	echo(json_encode($obj));
}

function require_param($param) {
	if ($param) { return $param; }
	else {
		http_response_code(400);
		exit();
	}
}


function authenticate() {
	session_name('hshsession');
	session_start();
	if ($_SESSION['id']) {
		return $_SESSION['id'];
	}
	else {
		http_response_code(401);
		exit();
	}
}

function authorize($scope) {
	$user = authenticate();												// Ermittle Benutzer
	$date = date('Y-m-d');
	$roles = roles(q_firstColumn(										// Ermittle alle Rollen des Benutzers
		"SELECT u.role FROM user_roles u WHERE user = $user AND '$date' BETWEEN u.start AND u.end"));

	foreach ($roles as $role) { if ($role == $scope) { return true; } }	// Trifft eine dieser Rollen auf die geforderte zu?
	return false;														// Wenn keine zutraf, ist der Nutzer nicht autorisiert
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

?>