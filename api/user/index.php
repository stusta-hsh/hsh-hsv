<?php

include('../api.php');

// Verwertung der Eingabe
switch ($_GET['q']) {
	case 'login': output(login()); break;
	case 'create': output(create()); break;
	case 'register': output(register()); break;
	case 'reset_password': output(reset_password()); break;
	default: break;
}

// --------------
// API-Funktionen
// --------------

function login() {
	$email = require_param($_POST['email']);				// The request must contain the users email address
	$password = require_param($_POST['password']);			// as well as the password (in plaintext, sent over https)

	// Query the hashed password of the user
	$hash = qp_firstField("SELECT password FROM users WHERE email = ?", "s", $email);

	if(password_verify($password, $hash)) {					// Hash the recieved password and compare with the saved hash
		// ** User authenticated **
		session_name('hshsession');
		session_set_cookie_params(0, '/', '.stusta.de', true, true);
		session_start();
		
		$user = qp_firstRow("SELECT id, name, first_name, last_name, email FROM users WHERE email = ?", "s", $email);
		$_SESSION['id'] = $user['id'];
		$_SESSION['user'] = $user;
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

function register() {
	$user = require_param($_GET['user']);
	$password = require_param($_POST['password']);

	//don't reset the password of already registeres users
	if(!qp_firstField("SELECT (CASE WHEN password = '' THEN 1 ELSE 0 END) FROM users WHERE id = ?", "i", $user)) {
		http_response_code(401);
		exit;
	}

	$hash = password_hash($password, PASSWORD_DEFAULT);
	dm_prepared("UPDATE users SET password = '$hash' WHERE id = ?", "i", $user);
	return true;
}

function reset_password() {
	$user = authenticate();
	$password = require_param($_POST['password']);

	$hash = password_hash($password, PASSWORD_DEFAULT);
	query("UPDATE users SET password = '$hash' WHERE id = $user");
	return true;
}
?>