<?php 

require('../api.php');

// Verwertung der Eingabe
switch ($_GET['q']) {
	case 'currAccountingDate': output(currAccountingDate()); break;
	case 'surrAccountingDates': output(surrAccountingDates()); break;
	case 'categories': output(categories()); break;
	case 'accounts': output(accounts()); break;
	case 'invoices': output(invoices()); break;
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

function accounts() {
	if ($_SERVER['REQUEST_METHOD'] == 'POST') { return accounts_post(); }
	else { return accounts_get(); }
}

function accounts_post() {
	$sql = ""; $insertSql = ""; $deleteSql = "";
	$post = param_post();
	$floor = $post['floor'] ?? $_SESSION['room']['floor'];
	$date = $post['date'] ?? currAccountingDate();
	
	foreach ($post['accounts'] as $account) {
		$user = $account['user'];
		$category = $account['category'];
		$amount = $account['amount'];

		// when the amount is set to 0, delete the row instead of flooding the database with 0-entrys
		if ($amount == 0) { $deleteSql .= " OR (date = '$date' AND user = $user AND category = $category)"; }
		else { $insertSql .= "($floor, '$date', $user, $category, $amount), "; }
	}

	$insertSql = substr($insertSql, 0, -2); // remove last comma

	transaction_start();
	if ($insertSql != "") {
		query("REPLACE INTO fridge_accounts (floor, date, user, category, amount) VALUES $insertSql");
	}
	if ($deleteSql != "") {
		query("DELETE FROM fridge_accounts WHERE FALSE $deleteSql");
	}
	transaction_commit();

	return true;
}

// returns a accounting table to a specific date
function accounts_get() {
	authenticate();
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
				'house' => $q['house'],
				'floor' => $q['floor'],
				'room' => $q['room']
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

function invoices() {
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

	$invoices = array();
	$qu = q_fetch($sql);
	foreach ($qu as $q) {
		$o = array(
			'user' => array(
				'id' => 0+ $q['id'],
				'name' => $q['name']
			),
			'room' => array(
				'house' => $q['house'],
				'floor' => $q['floor'],
				'room' => $q['room']
			),
			'invoice' => $q['invoice']
		);
		array_push($invoices, $o);
	}

	return $invoices;
}

?>