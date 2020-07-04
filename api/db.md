# Background database
This document provides information about the background database scheme.
It will not be complete nor up-to-date, but it should give an overview about the data structure.

## Users
The table `users` contains information about inhabitants or users of services provided by
this website.

| Column	| Datatype	| Attriutes | Description
| ---		| ---		| ---		| ---
| `id`		| unsigned int(11) | auto-increment, primary key | Unique identifier for each user
| `name`	| varchar(30) | | The nickname of the user
| `first_name` | varchar(30) | | The user's first name
| `last_name` | varchar(30) | | The user's last name
| `password` | varchar(255) | | The user's password, hashed with PHPs password hashing extension, which by now uses bcrypt (PHP 5.5.0).
| `email` | varchar(50) | | The user's email address.

The table `user_roles` assigns authorisation roles to the users. More information on authorisation can be found in [readme.md].

| Column	| Datatype	| Attriutes | Description
| ---		| ---		| ---		| ---
| `user`	| unsigned int(10) | primary key, foreign key to `users` | The user's ID, which has the following role
| `role`	| unsigned int(10) | primary key, foreign key to [`roles.json`][readme.md] | The role the user holds
| `start`	| date | primary key | The date, the user begins/began to hold this role
| `end`		| date | | The date, the assigned role ends for the user