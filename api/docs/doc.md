# API-Endpoint /doc

This endpoint provides the information you are probably looking at right now (attention: meta!).

## Overview
*	*All*: Returns all API endpoints and a short description of them.
*	*Endpoint*: Returns all functions provided by an endpoint.
*	*Function*: Returns detailed information about a single API function.
*	*Shortcut*: Acts as a shortcut to the functions defined above.

## Functions

### All
This function lists all documented API endpoints and a short description for each one.
*	URI: `/api/doc/all`
*	Method: `GET`
*	Authorisation: None
*	Parameters: None
*	Returns:
	*	`200`: with the list of endpoints

### Endpoint
This function lists all documented functions in an API endpoint and a short description for each one.
*	URI: `/api/doc/ep`
*	Method: `GET`
*	Authorisation: None
*	Parameters:
	*	`endpoint`: the name of the endpoint
*	Returns:
	*	`200`: with the list of functions
	*	`400`: if the specified endpoint doesn't exist.

### Function
Finds the section in an endpoint documentation about a specified API function, and retuns the
information in it in a structurized way. The information includes the same properties as listed
below (URI, Method, Authentication, ...).
*	URI: `/api/doc/fun`
*	Method: `GET`
*	Authorisation: None
*	Parameters:
	*	`endpoint`: the name of the endpoint
	*	`function`: the name of the function (not the URI, but the name as listed in `Endpoint`)
*	Returns:
	*	`200`: with the properties of the API function
	*	`400`: if the specified endpoint or function doesn't exist

### Shortcut
With this function, one can simply access the documentation about a specific API function, by
adding the string `doc/d/` to the url right before the endpoint. Eg if you work with the
function `/api/user/login` and want to see the documentation, simply change the ULR to
`/api/doc/d/user/login`. If you skip the function name, this also works for the endpoint documentaion.
*	URI: `/api/doc/d`
*	Method: `GET`
*	Authorisation: None
*	Parameters: None
*	Returns:
	*	`200`: with the respective documentation
	*	`400`: if the endpoint or function could not be found
