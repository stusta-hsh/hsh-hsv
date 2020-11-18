# API-Endpoint /user

This endpoint contains functionality to manage users, their rooms, and their roles
in the self administration.

## Overview
*	Me: Returnes information about the currently logged in user.
*	Login: Creates an authorized session for the user.
*	Create: Creates a ghost entry in the users table to work with.
*	Request: Creates a registration request, that an authorized user needs to accept.
*	Verify: Verifies the Email address of a registration request.
*	Register: Resolves a registration request by associating it with a ghost account.
*	Change Password: Changes the password of an authorized user.
*	Reset Password: Resets the password of an unauthrized user to a random string.
*	Suggest: Returns a list of users that match a query string.

## Functions

### Me
This function authenticates the user and returns all information about him stored in
the users table of the database.
*	URI: `/api/user/me`
*	Method: `GET`
*	Authentication: The user can only get information about himself.
*	Parameters: None
*	Returns:
	*	`200`: with the mentioned data
	*	`401`: if no user is logged in

### Login
This function establishes a PHP session by sending a cookie with the response. The cookie
contains a session ID, that PHP uses to associate respective data with the user in later
requests, which then stands in the global variable `$_SESSION`. This method fills the
global variable with the user ID, information about the user and the user's room.
*	URI: `/api/user/login`
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

### Create
This function is used for two cases: (1) in response to a registration request, a authorized
person creates the account with the provided or modified data and registers it afterwards.
(2) in order to put someone on a list (fridge accounts or floor resposibles), who has no
account and has not (yet) requested one.
*	URI: `/api/user/create`
*	Method: `POST`
*	Authentication: `2`, `3`, `4`, `11xx`
*	Parameters:
	*	`name`: A name for the user
	*	`firstName`: The user's first name (optional)
	*	`lastName`: The user's last name (optional)
	*	`email`: Email address of the user (optional)
	*	`room`: The user's room (optional)
	*	`moved_in`: The date, when the user moved in the mentioned room (optional) 
*	Returns:
	*	`200`: with the created user entry
	*	`401`: if no user is logged in
	*	`403`: if the logged in user is not authorized to create ghost users

### Request

### Register
*	URI: `/api/user/register`
*	Method: `POST`
*	Authentication: None
*	Parameters:
	*	`user`: The user ID
	*	`password`: The password to be set
*	Returns:
	*	`200`: the created user entry
	*	`401`: the user is already registered

### Reset Password
*	URI: `/api/user/reset_password`
*	Method: `POST`
*	Authentication: The user can only reset it's own password
*	Parameters:
	*	`password`: The password to be set
*	Returns:
	*	`200`: the created user entry
	*	`401`: no user is logged in