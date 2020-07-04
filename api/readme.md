# General API functions

## Database Functionality
The file `db.php` contains functionality for the API endpoints to interact with the database.

The database server is the same server this webpage is running on. It is a MySQL database,
which is accessed here with the PHP MySQLi extension.
Details on the database scheme can be found in the file [db.md].

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

*   `query($sql)`: Interface to perform a simple query on the database. Returns an array of the result rows.
*   `q_firstField($sql)`: Returns only the first cell of the first row. Handy for aggregate functions.
*   `q_firstRow($sql)`: Returns only the first row as an associative array. Handy for querying a specific row of a table.
*   `q_firstColumn($sql)`: Returns an associative array of the result rows.

### Prepared query functions
This functions can process user input by themselves, using prepared MySQLi queries.

*   `query_prepared($sql, ...$params)`: Interface to perform a prepared query on the database.
*   `qp_firstField`, `qp_firstRow` and `qp_firstColumn`: the simple queries' according prepared querys