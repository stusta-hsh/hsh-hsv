<?php 

include('../api.php');

session_name('hshsession');
session_start();

// Verwertung der Eingabe
switch ($_GET['q']) {
	case 'currAccountingDate': output(currAccountingDate()); break;
	case 'surrAccountingDates': output(surrAccountingDates()); break;
	case 'categories': output(categories()); break;
	case 'accounts': output(accounts()); break;
	case 'invoice': output(invoice()); break;
	default: http_error(400, "the requested endpoint \"$_GET[q]\" doesn't exist"); exit;
}

// --------------
// API-Funktionen
// --------------

function currAccountingDate() {
	$floor = $_GET['floor'] ?? $_SESSION['room']['floor'];
	$date = date('Y-m-d');
	return qp_firstField("SELECT MAX(date) FROM fridge_accounts WHERE floor = ? AND date < ?", "is", $floor, $date);
}

// Returns the preceeding and the following accounting date 
function surrAccountingDates() {
	$floor = $_GET['floor'] ?? $_SESSION['room']['floor'];
	$date = $_GET['date'] ?? currAccountingDate();
	return array(
		'prev' => qp_firstField("SELECT MAX(date) FROM fridge_accounts WHERE floor = ? AND date < ?", "is", $floor, $date),
		'sel' => $date,
		'next' => qp_firstField("SELECT MIN(date) FROM fridge_accounts WHERE floor = ? AND date > ?", "is", $floor, $date)
	);
}

// Returns the existing drink categories with their prices to a specific time
function categories() {
	$floor = $_GET['floor'] ?? $_SESSION['room']['floor'];
	$date = $_GET['date'] ?? date('Y-m-d');
	return qp_fetch(
		"SELECT id, name, value
		FROM fridge_categories c
		WHERE floor = ? AND c.date = (SELECT MAX(date) FROM fridge_categories WHERE id = c.id AND date <= ?)",
		"is", $floor, $date);
}

// returns a accounting table to a specific date
function accounts() {
	$floor = $_GET['floor'] ?? $_SESSION['room']['floor'];
	if (!authorize(1100 + $floor)) { http_error(401, "You need to be the fridge administrator for this action"); }

	$date = $_GET['date'] ?? currAccountingDate();
	$categories = categories();
	$categories_count = count($categories);

	$sql = "SELECT u.id, u.name, r.house, r.floor, r.room";
	for ($i = 0; $i < $categories_count; $i++) { $sql .= ", (CASE WHEN t$i.amount IS NOT NULL THEN t$i.amount ELSE 0 END) AS a$i"; }
	$sql .= " FROM users u LEFT JOIN rooms r ON (r.user = u.id AND '$date' BETWEEN r.date AND (CASE WHEN r.end IS NULL THEN '" . date('Y-m-d') . "' ELSE r.end END))";
	for ($i = 0; $i < $categories_count; $i++) { $sql .= " LEFT JOIN fridge_accounts t$i ON (t$i.user = u.id AND t$i.floor = $floor AND t$i.category = $i AND t$i.date = '$date')"; }
	$sql .= " WHERE NOT (";
	for ($i = 0; $i < $categories_count; $i++) { $sql .= "t$i.amount IS NULL AND "; }
	$sql = substr($sql, 0, -5); // letztes AND entfernen
	$sql .= ") ORDER BY (CASE WHEN r.house IS NULL THEN u.name ELSE r.house END), r.floor, r.room";

	$accounts = array();
	$qu = q_fetch($sql);
	foreach ($qu as $q) {
		$a = array();
		for ($i = 0; $i < $categories_count; $i++) { $a[$i] = 0+$q["a$i"]; }
		$o = array(
			'user' => array(
				'id' => 0+ $q['id'],
				'name' => $q['name']
			),
			'room' => array(
				'house' => 0+$q['house'],
				'floor' => 0+$q['floor'],
				'room' => 0+$q['room']
			),
			'account' => $a
		);
		array_push($accounts, $o);
	}

	return array(
		'dates' => surrAccountingDates(),
		'categories' => $categories,
		'accounts' => $accounts
	);
}

function invoice() {
	$date = date('Y-m-d');
	$sql = "SELECT u.id, u.name, r.house, r.floor, r.room, (account.amount - IFNULL(payments.amount, 0) - IFNULL(donated.amount, 0) + IFNULL(recieved.amount, 0)) AS invoice
	FROM 
		users u
		JOIN (
			SELECT a.user, SUM(a.amount * c.value) AS amount
			FROM fridge_accounts a, fridge_categories c
			WHERE a.category = c.id AND c.date =
				(SELECT MAX(fridge_categories.date) FROM fridge_categories WHERE fridge_categories.date < a.date AND a.category = fridge_categories.id)
			GROUP BY a.user
			) AS account ON (u.id = user)
		LEFT JOIN (SELECT user,      SUM(value) AS amount FROM fridge_payments  GROUP BY user     ) AS payments ON (u.id = payments.user)
		LEFT JOIN (SELECT donor,     SUM(value) AS amount FROM fridge_transfers GROUP BY donor    ) AS donated  ON (u.id = donor)
		LEFT JOIN (SELECT recipient, SUM(value) AS amount FROM fridge_transfers GROUP BY recipient) AS recieved ON (u.id = recipient)
		LEFT JOIN rooms r ON (r.user = u.id AND '$date' BETWEEN r.date AND (CASE WHEN r.end IS NULL THEN '$date' ELSE r.end END))
	WHERE (account.amount - IFNULL(payments.amount, 0) - IFNULL(donated.amount, 0) + IFNULL(recieved.amount, 0)) <> 0
	ORDER BY (CASE WHEN r.house IS NULL THEN 20 ELSE r.house END), r.floor, r.room, u.name";

	return qp_fetch($sql);
}

?>