<?php

require('../api.php');

// Determine API function 
switch ($_GET['q']) {
	case 'me': output(me()); break;
	case 'login': output(login()); break;
	case 'create': output(create()); break;
	case 'request': output(request()); break;
	case 'register': output(register()); break;
	case 'verify': output(verify()); break;
	case 'reset_password': output(reset_password()); break;
	case 'merge': output(merge()); break;
	case 'suggest': output(suggest()); break;
	default: http_error(400, "the requested function \"$_GET[q]\" doesn't exist"); break;
}

// --------------
// API-Funktionen
// --------------

function me() {
	$myid = authenticate();
	$me = q_firstRow("SELECT * FROM users WHERE id=$myid");
	return $me;
}

function login() {
	$email = require_param($_POST['email']);				// The request must contain the users email address
	$password = require_param($_POST['password']);			// as well as the password (in plaintext, sent over https)

	// Query the hashed password of the user
	$hash = qp_firstField("SELECT password FROM users WHERE email = ?", "s", $email);

	// Hash the recieved password and compare with the saved hash
	if(password_verify($password, $hash)) {
		// ** User authenticated **
		session_name('hshsession');
		session_set_cookie_params(0, '/', '.stusta.de', true, true);
		session_start();
		
		$date = date('Y-m-d');
		$user = qp_firstRow("SELECT id, name, first_name, last_name, email FROM users WHERE email = ?", "s", $email);
		$room = qp_firstRow("SELECT house, floor, room, date, end FROM rooms r WHERE r.user = ? AND '$date' BETWEEN r.date AND (CASE WHEN r.end IS NULL THEN '$date' ELSE r.end END)", "i", $user);
		
		$_SESSION['id'] = $user['id'];
		$_SESSION['user'] = $user;
		$_SESSION['room'] = $room;
		
		return true;
	}
	else {
		http_error(401, "The provided credentials don't match");
	}
}

function create() {
	if (!authorize(2, 3, 4, 18)) { http_error(403, "You are not authorized to create users"); }

	$post = param_post();
	$name = require_param($post['name']);	// The request must contain at least a name for the new user
	$firstName = $post['firstName'] ?: "";
	$lastName = $post['lastName'] ?: "";
	$email = $post['email'] ?: "";
	
	$insertId = dm_prepared("INSERT INTO users (name, first_name, last_name, email) VALUES (?,?,?,?)", "ssss", $name, $firstName, $lastName, $email);
	http_response_code(201);
	return q_firstRow("SELECT * FROM users WHERE id = $insertId");
}

function request() {
	$post = param_post();
	$name = require_param($post['name']);					// The request must contain a name
	$email = require_param($post['email']);					// and the user credentials
	$password = require_param($post['password']);
	$firstName = $post['firstName'] ?: "";
	$lastName = $post['lastName'] ?: "";

	$house = $floor = $room = $movedIn = null;				// Avoid PHP Notices
	if (array_key_exists('room', $post)) {					// Optional room object. If included, it must contain all variables
		$house = require_param($post['room']['house']);
		$floor = require_param($post['room']['floor']);
		$room = require_param($post['room']['room']);
		$movedIn = require_param($post['room']['movedIn']);
	}

	if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {		// Check for valid email format
		http_error(400, "Email not in a valid format");
	}
	
	$hash = password_hash($password, PASSWORD_DEFAULT);		// Create the password hash, that will be stored in the database
	$verificationCode = bin2hex(random_bytes(10));			// Create the code, the user must provide to verify his request

	// Insert to database
	$insertId = dm_prepared(
		"INSERT INTO user_requests (name, first_name, last_name, email, password, verification, house, floor, room, moved_in) VALUES (?,?,?,?,?,?,?,?,?,?)",
		"ssssssiiis", $name, $firstName, $lastName, $email, $hash, $verificationCode, $house, $floor, $room, $movedIn);

	if (!$insertId) { http_error(400, "Request denied. Probably the email already exists."); }

	// Send verification email to user
	query("INSERT INTO user_verification (user, code) VALUES ($insertId, '$code')");
	$subject = "Your registration at HSH";
	$message = "Hello $name,\r\nto complete your registration at the HSH page, click this link: <a>hsh.stusta.de/api/user/verify?user=$insertId&code=$code</a>";
	$headers = "from: noreply@stusta.de";
	/*if(!mail($email, $subject, $message, $headers)) {
		transaction_rollback();
		http_error(500, "Registration email could not be sent");
	}*/
	
	// Exclude all columns with sensitive data
	http_response_code(201);
	return q_firstRow("SELECT id, date, name, first_name, last_name, email, house, floor, room, moved_in FROM user_requests WHERE id = $insertId");
}

function verify() {
	$user = require_param($_GET['user']);
	$code = require_param($_GET['code']);

	transaction_start();
	$servercode = qp_firstField("SELECT code FROM user_verification WHERE user = ?", "i", $user);
	if ($servercode == $code) {
		dm_prepared("UPDATE users SET verified = 1 WHERE id = ?", "i", $user);
		dm_prepared("DELETE FROM user_verification WHERE user = ?", "i", $user);
		transaction_commit();
		return "You have successfully verified your HSH account.";
	}
	else {
		transaction_rollback();
		http_error(400, "Invalid verification link");
	}
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

	// insert in user table
	transaction_start();
	$insertId = dm_prepared("INSERT INTO users (name, first_name, last_name, password, email) VALUES (?,?,?,'$hash',?)", "ssss", $name, $firstName, $lastName, $email);
	if(!insertId) {
		transaction_rollback();
		http_error(400, "User could not be registered. Probably the email already exists.");
	}

	// Send verification email to user
	query("INSERT INTO user_verification (user, code) VALUES ($insertId, '$code')");
	$subject = "Your registration at HSH";
	$message = "Hello $name,\r\nto complete your registration at the HSH page, click this link: <a>hsh.stusta.de/api/user/verify?user=$insertId&code=$code</a>";
	$headers = "from: noreply@stusta.de";
	/*if(!mail($email, $subject, $message, $headers)) {
		transaction_rollback();
		http_error(500, "Registration email could not be sent");
	}*/

	transaction_commit();
	return q_firstRow("SELECT * FROM users WHERE id = $insertId");
	return true;
}

function reset_password() {
	$user = authenticate();
	$password = require_param($_POST['password']);

	$hash = password_hash($password, PASSWORD_DEFAULT);
	query("UPDATE users SET password = '$hash' WHERE id = $user");
	return true;
}

function merge() {
	authorize(0);
	//$merger = authenticate();
	
	$uid1 = require_param($_POST['primary_user']);
	$uid2 = require_param($_POST['secondary_user']);

	$user1 = qp_firstRow("SELECT * FROM users WHERE id=?", "i", $uid1);
	$user2 = qp_firstRow("SELECT * FROM users WHERE id=?", "i", $uid2);

	merge_property($user1, $user2, 'name');
	merge_property($user1, $user2, 'first_name');
	merge_property($user1, $user2, 'last_name');
	merge_property($user1, $user2, 'password');
	merge_property($user1, $user2, 'email');
	merge_property($user1, $user2, 'verified');

	dm_prepared("UPDATE users SET name=?, first_name=?, last_name=?, password=?, email=?, verified=? WHERE id=?", 'sssssii', 
		$user1['name'], $user1['first_name'], $user1['last_name'], $user1['password'], $user1['email'], $user1['verified'], $user1['id']);
	dm_prepared("DELETE FROM users WHERE id=?", "i", $uid2);
}

function merge_property(&$o1, &$o2, $property) {
	if ($o1[$property] == $o2[$property])
		return true;

	if ($o1[$property] == null || $o1[$property] == "" || $o1[$property] == 0) {
		$o1[$property] = $o2[$property];
		return true;
	}

	if ($o2[$property] == null || $o2[$property] == "" || $o2[$property] == 0) {
		$o2[$property] = $o1[$property];
		return true;
	}

	return false;
}

function suggest() {
	$query = require_param($_GET['query']);
	$date = $_GET['date'] ?? date('Y-m-d');

	return q_fetch("SELECT u.id, u.name, r.house, r.floor, r.room
	FROM users u LEFT JOIN rooms r ON (r.user = u.id AND '$date' BETWEEN r.date AND (CASE WHEN r.end IS NULL THEN '" . date('Y-m-d') . "' ELSE r.end END))
	WHERE
		CONCAT (u.first_name, ' ', u.last_name, ' ', u.name, ' ',
			(CASE WHEN r.house IS NULL THEN '' ELSE CONCAT(r.house, '/', LPAD(r.floor, 2, 0), LPAD(r.room, 2, 0)) END))
		LIKE '%$query%'
	ORDER BY u.name
	LIMIT 10");
}
?>