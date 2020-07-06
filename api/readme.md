# General API functions



## Database Functionality
The file `db.php` contains functionality for the API endpoints to interact with the database.

The database server is the same server this webpage is running on. It is a MySQL database,
which is accessed here with the PHP MySQLi extension.
Details on the database scheme can be found in the file [db.md](db.md).

The functions provided here simplify the use of the database, but you can also access it via
the functions of the MySQLi extension.


### Connection to the database
By running this file, it establishes a connection to the database server.
The server credentials (host, username, password, db-name) are stored in the file `db_config.php`,
which is not included in this repository for security reasons.

The database connection is stored in the global variable `$db`.


### Simple query functions
This functions should only be used for queries, that can't harm the database integrity, as they
do **not** check for SQL injection attacks. If you want to process user input in the query, use
the prepared query functions.

*	`query($sql)`: Interface to perform a simple query on the database. Returns an array of the result rows.
*	`q_firstField($sql)`: Returns only the first cell of the first row. Handy for aggregate functions.
*	`q_firstRow($sql)`: Returns only the first row as an associative array. Handy for querying a specific row of a table.
*	`q_firstColumn($sql)`: Returns an associative array of the result rows.


### Prepared query functions
This functions can process user input by themselves, using prepared MySQLi queries.

*	`query_prepared($sql, ...$params)`: Interface to perform a prepared query on the database.
*	`qp_firstField`, `qp_firstRow` and `qp_firstColumn`: the simple queries' according prepared querys

Note that prepared querys don't improve the performance of repetitive queries in this implementation,
as for every query a new statement is created. If you want to speed these queries up, you need to implement
this functionality in place in the endpoints.



## Common API functionality
The file `api.php` contains functions used across all API endpoints. It is included in every
endpoint and itself includes the `db.php` file mentioned above.


### IO Functions
This functions simplify the handling of messages between the client and the server.

*	`output($obj)`: Encodes a given object to JSON and echoes it back to the requester.

	Instead of directly echoing JSON in the endpoints, this function should be used. This makes it
	possible for a endpoint function to quickly use results from other endpoint functions, as these
	retrurn a PHP object instead of echoing a string.

*	`require_param`: Simplifies the handling of wrong requests.

	The function checks whether the	given variable exists. If it does, the variable is returned,
	if not, the request is rejected	with the HTTP stats code 401 (Bad request) and the script is aborted.

	It is intended to be used like this:
	```
	$user = require_param($_POST['user']);
	```


### Authorization Functions
This functions provide access control features for the API endpoints.

*	`authenticate`: Before a user becomes authorized to do something, it needs to be verified,
	that the user is who he claims to be.

	Authentication is achieved here through PHP sessions. After a user [logs in](/user.md), a
	cookie with an cryptic session ID is stored on the client. Information about this session,
	like the users ID, is saved on the server, invisible for the user.

	If you want to authenticate the user, you need to check whether a session exists with the
	ID specified in the request.

	This function returns the user ID of the currently logged in user.

*	`authorize`: In order to grant access to something, you first need to authenticate the user,
	determine the roles he holds an compare them to the required role. If they match, access is
	granted.

	The roles a user holds are listed in the table [`user_roles`](db.md); the role tree is specified
	in the file `roles.json` (In a file, not the database, because the file can be statically loaded
	into the servers memory, and recursive querys in SQL are impossible or at least inefficient).

	This function returns a boolean value, whether the user is authorized or not.