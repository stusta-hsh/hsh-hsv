# API-Endpoint /fridge

This endpoint contains functionality to manage the beverage fridges in the common rooms.

## Overview
*	*Current Accounting Date*: Returns the date, when the last accounting was done
*	*Surrounding Accounting Dates*: Finds to a given date the date of the last and the next accounting
*	*Categories*: Returns the beverage categories and the respective prices
*	*Accounts*: Gets or sets an accounting table
*	*Invoices*: Returns a list of all fridge users an their debts

## Functions

### Current Accounting Date
This function is used to define the default landing of the accounting page.
*	URI: `/api/fridge/currAccountingDate`
*	Method: `GET`
*	Authorisation: None
*	Parameters:
	*	`floor`: (optional, by default the floor the user lives in)
*	Returns:
	*	`200`: with the mentioned date

### Surrounding Accounting Dates

### Categories

### Accounts

### Invoices