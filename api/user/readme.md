# API-Endpoint /user

This endpoint contains functionality to manage users, their rooms, and their roles
in the self administration.

## Login

*   URL: `https://hsh.stusta.de/api/user/?q=login`
*   Method: `POST`
*   Authentication: None
*   Parameters:
	*   `email`: the user's email
	*   `password`: the user's password (in plaintext)
*   Returns:
	*   `200`: if the login was successfull
	*   `401`: if the login was not successfull.
		This can happen when the provided email is unknown or the password is incorrect.
		For data protection reasons these situations are handled in the same way.

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


## Register

*	URL: `https://hsh.stusta.de/api/user/?q=register`
*	Method: `POST`
*	Authentication: None
*	Parameters:
	*	`user`: The user ID
	*	`password`: The password to be set
*	Returns:
	*	`200`: the created user entry
	*	`401`: the user is already registered

## Reset Password

*	URL: `https://hsh.stusta.de/api/user/?q=reset_password`
*	Method: `POST`
*	Authentication: The user can only reset it's own password
*	Parameters:
	*	`password`: The password to be set
*	Returns:
	*	`200`: the created user entry
	*	`401`: no user is logged in