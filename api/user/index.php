<?php

include('../api.php');

// Verwertung der Eingabe
switch ($_GET['q']) {
	case 'login': output(login()); break;
	case 'create': output(create()); break;
	default: break;
}

// --------------
// API-Funktionen
// --------------

function login() {
	$user = require_param($_POST['user']);					// The request must contain the user id
	$password = require_param($_POST['password']);			// as well as the password (in plaintext, sent over https)

	// Query the hashed password of the user
	$hash = qp_firstField("SELECT password FROM users WHERE id = ?", "s", $user);

	if(password_verify($password, $hash)) {					// Hash the recieved password and compare with the saved hash
		// ** User authenticated **
		session_name('hshsession');
		session_set_cookie_params(0, '/', '.stusta.de', true, true);
		session_start();
		
		$_SESSION['id'] = $user;
		$_SESSION['user'] = qp_firstRow("SELECT name, first_name, last_name, email FROM users WHERE id = ?", "s", $user);
		$_SESSION['room'] = qp_firstRow("SELECT house, floor, room, date, end FROM rooms r WHERE r.user = ? AND '$date' BETWEEN r.date AND (CASE WHEN r.end IS NULL THEN '$date' ELSE r.end END)", "s", $user);
		
		return true;
	}
	else {
		// ** User not authenticated **
		http_response_code(401);
		exit;
	}
}

function create() {
	$name = require_param($_POST['name']);					// The request must contain at least a name for the new user
	$firstName = $_POST['firstName'] ?: "";
	$lastName = $_POST['lastName'] ?: "";
	$email = $_POST['email'] ?: "";
	
	$insertId = dm_prepared("INSERT INTO users (name, first_name, last_name, email) VALUES (?,?,?,?)", "ssss", $name, $firstName, $lastName, $email);
	return q_firstRow("SELECT * FROM users WHERE id = $insertId");
}
?>