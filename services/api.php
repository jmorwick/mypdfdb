<?php
require_once("weblib.php");

header("Access-Control-Allow-Orgin: *");
header("Access-Control-Allow-Methods: *");

// extract arguments from request
$args = explode('/', rtrim($_GET['request'], '/'));
$endpoint = array_shift($args);
$method = $_SERVER['REQUEST_METHOD'];

switch($method) {
	case 'GET':
		switch($endpoint) {
			case 'search':
				service_search_db($args);
				exit();
			case 'pdf':
				service_retrieve_pdf($args);
				exit();
			case 'tags':
				service_retrieve_tags($args);
				exit();
			default: err_no_such_service($endpoint);
		}
	case 'POST':
		switch($endpoint) {
			case 'updatepdf':
				service_update_pdf($args, $_POST);
				exit();
			case 'mergepdfs':
				service_merge_pdfs($args);
				exit();
			default: err_no_such_service($endpoint);
		}
	case 'PUT':
		switch($endpoint) {
			case 'tag':
				service_associate_tags($args);
				exit();
			case 'createtag':
				service_create_tag($args);
				exit();
			case 'untag':
				service_disassociate_tags($args);
				exit();
			default: err_no_such_service($endpoint);
		}
	case 'DELETE':
		switch($endpoint) {
			case 'deletetags':
				service_delete_tags($args);
				exit();
			case 'deletepdfs':
				service_delete_pdfs($args);
				exit();
			default: err_no_such_service($endpoint);
		}
	default: err_no_such_service($endpoint);
}

