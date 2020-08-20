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
		http_error(401, "The provided credentials don't match");
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
	$name = require_param($_POST['name']);
	$email = require_param($_POST['email']);
	$password = require_param($_POST['password']);
	$firstName = $_POST['firstName'] ?: "";
	$lastName = $_POST['lastName'] ?: "";

	if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
		http_error(400, "Invalid Email format");
	}
	
	$hash = password_hash($password, PASSWORD_DEFAULT);
	$code = bin2hex(random_bytes(10));

	$insertId = dm_prepared("INSERT INTO users (name, first_name, last_name, password, email) VALUES (?,?,?,'$hash',?)", "ssss", $name, $firstName, $lastName, $email);
	if(!insertId) { http_error(400, "User could not be registered. Probably the email already exists."); }

	query("INSERT INTO user_verification (user, code) VALUES ($insertId, $code)");

	$subject = "Your registration at HSH";
	$message = "Hello $name,\r\nto complete your registration at the HSH page, click this link: <a>hsh.stusta.de/api/user/?q=verify&code=$code</a>";
	$headers = "from: noreply@stusta.de";
	if(!mail($email, $subject, $message, $headers)) {
		http_error(500, "Registration email could not be sent");
	}

	return q_firstRow("SELECT * FROM users WHERE id = $insertId");
}

function reset_password() {
	$user = authenticate();
	$password = require_param($_POST['password']);

	$hash = password_hash($password, PASSWORD_DEFAULT);
	query("UPDATE users SET password = '$hash' WHERE id = $user");
	return true;
}

/*function merge() {
	$merger = authenticate();
	
	$u1 = require_param($_POST['primary_user']);
	$u2 = require_param($_POST['secondary_user']);


}*/
?>