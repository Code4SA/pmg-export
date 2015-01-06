pmg-export
==========

Export a database from Drupal/MySql to a bunch of JSON files

Designed for the [PMG website](http://pmg.org.za) but probably usable for other Drupal DBs.

This code is adapted from the original 10Layer framework used to do the original exports. 

It now no longer relies on the 10Layer API, and writes directly to JSON files, which can then be read
directly by the Flask db importer in the PMG project.

Sorry, the DB credentials won't work on the live server. 

Built on CodeIgniter (PHP). Should just run, given a working PHP installation with MySql.
