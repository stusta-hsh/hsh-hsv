<?php 

// -------------------
// Datenbankverbindung
// -------------------

include('db_config.php');
$db = mysqli_connect($sql_host, $sql_username, $sql_password, $sql_dbname);
if(!$db) exit('Database connection error: '.mysqli_connect_error());

// Abfragefunktionen
function query($sql) { global $db; return mysqli_query($db, $sql); }
function q_firstField($sql) { return mysqli_fetch_row(query($sql))[0]; }
function q_assocFirstRow($sql) { return mysqli_fetch_array(query($sql), MYSQLI_ASSOC); }
function q_assocArray($sql) { return mysqli_fetch_all(query($sql), MYSQLI_ASSOC); }
function q_firstColumn($sql) { return array_map(function ($a) { return $a[0]; }, mysqli_fetch_all(query($sql))); }

?>