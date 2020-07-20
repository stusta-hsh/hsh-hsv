# API-Endpoint /user

This endpoint contains functionality to manage users, their rooms, and their roles
in the self administration.

## Login

*   URL: `https://hsh.stusta.de/api/user/?q=login`
*   Method: `POST`
*   Authentication: None
*   Parameters:
	*   `user`: the ID of the user to log in
	*   `password`: the user's password (in plaintext)
*   Returns:
	*   `200`: if the login was successfull
	*   `401`: if the login was not successfull

## Create
Creates an entry in the users table to work with. The user can't log in yet.

*	URL: `https://hsh.stusta.de/api/user/?q=create`
*	Method: `POST`
*	Authentication: None
*	Parameters:
	*	`name`: A name for the user
	*	`firstName`: The users first name (optional)
	*	`lastName`: The users last name (optional)
	*	`email`: Email address of the user (optional)
*	Returns:
	*	`200`: the created user entry