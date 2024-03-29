<?php

require('../api.php');

// Determine API function 
switch ($_GET['q']) {
	case 'me': output(me()); break;
	case 'u': output(u()); break;
	case 'login': output(login()); break;
	case 'create': output(create()); break;
	case 'request': output(request()); break;
	case 'verify': output(verify()); break;
	case 'register': output(register()); break;
	case 'reset_password': output(reset_password()); break;
	case 'merge': output(merge()); break;
	case 'suggest': output(suggest()); break;
	default: http_error(400, "the requested function \"$_GET[q]\" doesn't exist"); break;
}

// --------------
// API functions
// --------------

function me() {
	$myid = authenticate();
	$me = q_firstRow("SELECT * FROM users WHERE id=$myid");
	return $me;
}

function u() {
	authenticate();
	$date = $_GET['date'] ?? date('Y-m-d');
	return qp_firstRow("SELECT u.id, u.name, u.first_name as firstName, u.last_name as lastName,
			r.house, r.floor, r.room, r.date as movedIn, r.end as movedOut, u.email
		FROM users u LEFT JOIN rooms r ON (r.user = u.id AND '$date' BETWEEN r.date AND (CASE WHEN r.end IS NULL THEN '$date' ELSE r.end END))
		WHERE u.id = ?", 'i', $_GET['u']);
}

function login() {
	$post = param_post();
	$email = require_param($post['email']);				// The request must contain the users email address
	$password = require_param($post['password']);		// as well as the password (in plaintext, sent over https)

	// Query the hashed password of the user
	$hash = qp_firstField("SELECT password FROM users WHERE email = ?", "s", $email);

	// Hash the recieved password and compare with the saved hash
	if(password_verify($password, $hash)) {
		// ** User authenticated **
		session_name('hshsession');
		if ($DEBUG) { session_set_cookie_params(0, '/', '127.0.0.1', true, true); }
		else { session_set_cookie_params(0, '/', '.stusta.de', true, true); }
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
	$name = require_param($post['name']);					// The request must contain at least a name for the new user
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
	transaction_start();
	$insertId = dm_prepared(
		"INSERT INTO user_requests (name, first_name, last_name, email, password, verification, house, floor, room, moved_in) VALUES (?,?,?,?,?,?,?,?,?,?)",
		"ssssssiiis", $name, $firstName, $lastName, $email, $hash, $verificationCode, $house, $floor, $room, $movedIn);

	if (!$insertId) { http_error(400, "Request denied. Probably the email already exists."); }

	// Send verification email to user
	if(!verificationMail($email, $name, $insertId, $verificationCode)) {
		transaction_rollback();
		http_error(500, "Request verification email could not be sent");
	}
	transaction_commit();
	
	// Exclude all columns with sensitive data
	http_response_code(201);
	return q_firstRow("SELECT id, date, name, first_name, last_name, email, house, floor, room, moved_in FROM user_requests WHERE id = $insertId");
}

function verify() {
	$post = param_post();
	$request = require_param($post['request']);
	$c_msg = require_param($post['code']);

	// Retrieve the correct verification code
	$c_req = qp_firstField("SELECT verification FROM user_requests WHERE id = ?", "i", $request);

	// Check whether the provided code matches the one stored on the server
	if (strcmp($c_msg, $c_req) != 0) { http_error(400, "Invalid verification link"); }
	
	dm_prepared("UPDATE user_requests SET verified = 1 WHERE id = ?", "i", $request);

	http_response_code(204);
	return;
}

function register() {
	if ($_SERVER['REQUEST_METHOD'] == 'POST') { return register_post(); }
	else { return register_get(); }
}

function register_get() {
	if (!authorize(2, 3, 4, 18)) { http_error(403, "You are not authorized to register users"); }

	if (array_key_exists('id', $_GET)) {
		$output = qp_firstRow("SELECT id, date, name, first_name as firstName, last_name as lastName, email, house, floor, room, moved_in as movedIn
			FROM user_requests WHERE verified = 1 AND id = ?", i, $_GET['id']);

		if (!$output) { http_error(409, "Request $_GET[id] doesn't exist. Probably it still needs to be verified or it already has been registered."); }

		$suggestions = array();
		if ($output['room']) { 
			$suggestions = array_merge($suggestions, q_fetch("SELECT u.id, u.name, u.first_name, u.last_name, r.house, r.floor, r.room, 100 AS rang
				FROM users u LEFT JOIN rooms r ON (r.user = u.id)
				WHERE u.password = '' AND r.house = $output[house] AND r.floor = $output[floor] AND r.room = $output[room]"));
		}
		$suggestions = array_merge($suggestions, q_fetch("SELECT u.id, u.name, u.first_name, u.last_name, r.house, r.floor, r.room, 50 AS rang
			FROM users u LEFT JOIN rooms r ON (r.user = u.id) WHERE u.password = '' AND u.last_name = '$output[lastName]'"));
		$suggestions = array_merge($suggestions, q_fetch("SELECT u.id, u.name, u.first_name, u.last_name, r.house, r.floor, r.room, 10 AS rang
			FROM users u LEFT JOIN rooms r ON (r.user = u.id) WHERE u.password = '' AND u.name = '$output[name]'"));
		$output['suggestions'] = $suggestions;
		
		return $output;
	} else {
		return q_fetch("SELECT id, date, name, first_name as firstName, last_name as lastName, email, house, floor, room, moved_in as movedIn
			FROM user_requests WHERE verified = 1");
	}
}

function register_post() {
	if (!authorize(2, 3, 4, 18)) { http_error(403, "You are not authorized to register users"); }

	$post = param_post();
	$req_id = require_param($post['request']);				// The request, that should now become a user entry

	transaction_start();									// Prevent database integrity errors

	// Get the request from the database and check validity
	$request = qp_firstRow("SELECT * FROM user_requests WHERE id = ?", 'i', $req_id);
	if (!$request || $request['verified'] == 0) { 
		http_error(409, "Request $reqId doesn't exist. Probably it still needs to be verified or it already has been registered.");
	}

	// The values of the new user entry
	$req_name = $request['name'];
	$req_firstName = $request['first_name'];
	$req_lastName = $request['last_name'];
	$req_pw = $request['password'];
	$req_email = $request['email'];
	$req_room = true;

	// If a ghost account is given, edit the existing user entry
	if (array_key_exists('ghost', $post)) {
		$id = require_param($post['ghost']['id']);
		$keep = require_param($post['ghost']['keep']);		// A list of attributes, that should not be overwritten by the request

		$ghost = qp_firstRow("SELECT * FROM users WHERE id = ?", 'i', $id);

		// Ensure, that the given ghost account is actually a ghost
		if (!$ghost || $ghost['password'] != null) {
			http_error(409, "User $id doesn't exist or is not a ghost!");
		}

		// If an attribute appears in the keep-list, set it to its curent value
		foreach ($keep as $k) {
			switch ($k) {
				case 'name': $req_name = $ghost['name']; break;
				case 'firstName': $req_firstName = $ghost['first_name']; break;
				case 'lastName': $req_lastName = $ghost['last_name']; break;
				case 'room': $req_room = false; break;
				default: break;
			}
		}

		dm_prepared("UPDATE users SET name=?, first_name=?, last_name=?, password=?, email=? WHERE id = ?",
			'sssssi', $req_name, $req_firstName, $req_lastName, $req_pw, $req_email, $id);
	}
	// If no ghost account is given, simply create the new user entry
	else {
		dm_prepared("INSERT INTO users (name, first_name, last_name, password, email) VALUES (?,?,?,?,?)",
			'sssss', $req_name, $req_firstName, $req_lastName, $req_pw, $req_email);
	}
	
	// Delete the reqest and finish
	dm_prepared("DELETE FROM user_requests WHERE id=?", 's', $req_id);
	transaction_commit();

	http_response_code(204);
	return;
}

function reset_password() {
	$user = authenticate();
	$password = require_param($_POST['password']);

	$hash = password_hash($password, PASSWORD_DEFAULT);
	query("UPDATE users SET password = '$hash' WHERE id = $user");
	return true;
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

// --------------
// Helpers
// --------------

function verificationMail($email, $name, $id, $code) {
	global $DEBUG;
	if ($DEBUG) { return true; }

	$subject = "Your registration request at HSH";
	$message = "Hello $name,\r\n
		to complete your registration at the HSH page, click this link: <a>hsh.stusta.de/api/user/verify?user=$id&code=$code</a>\r\n
		After that, your request will shortly be accepted.\r\n";
	$headers = "from: noreply@stusta.de";

	return (mail($email, $subject, $message, $headers));
}
?>