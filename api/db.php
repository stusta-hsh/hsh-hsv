<?php 

// -------------------
// Database connection
// -------------------

include('db_config.php');
$db = mysqli_connect($sql_host, $sql_username, $sql_password, $sql_dbname);
if(!$db) exit('Database connection error: '.mysqli_connect_error());

// simple query functions
function query($sql) { global $db; return mysqli_query($db, $sql); }
function q_firstField($sql) { return mysqli_fetch_row(query($sql))[0]; }
function q_firstRow($sql) { return mysqli_fetch_array(query($sql), MYSQLI_ASSOC); }
function q_firstColumn($sql) { return array_map(function ($a) { return $a[0]; }, mysqli_fetch_all(query($sql))); }

// prepared query functions
function query_prepared($sql, $types, ...$params) {
	global $db;
	$stmt = mysqli_prepare($db, $sql);
	if (!mysqli_stmt_bind_param($stmt, $types, ...$params)) { echo(mysqli_error($db)); }
	if (!mysqli_stmt_execute($stmt)) { echo(mysqli_error($db)); }
	return mysqli_stmt_get_result($stmt);
}
function qp_firstField($sql, $types, ...$params) { return mysqli_fetch_row(query_prepared($sql, $types, ...$params))[0]; }
function qp_firstRow($sql, $types, ...$params) { return mysqli_fetch_array(query_prepared($sql, $types, ...$params), MYSQLI_ASSOC); }
function qp_firstColumn($sql, $types, ...$params) { return array_map(function ($a) { return $a[0]; }, mysqli_fetch_all(query_prepared($sql, $types, ...$params))); }

function dm_prepared($sql, $types, ...$params) {
	global $db;
	$stmt = mysqli_prepare($db, $sql);
	if (!mysqli_stmt_bind_param($stmt, $types, ...$params)) { echo(mysqli_error($db)); }
	if (!mysqli_stmt_execute($stmt)) { echo(mysqli_error($db)); }
	return mysqli_insert_id($db);
}
?>