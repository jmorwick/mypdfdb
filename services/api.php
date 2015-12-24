<?php

header("Access-Control-Allow-Orgin: *");
header("Access-Control-Allow-Methods: *");

$data_dir = getenv('DATA_DIR');
// TODO: connect to DB

// extract arguments from request
$args = explode('/', rtrim($_GET['request'], '/'));
$endpoint = array_shift($args);
unset($_GET['request']);
$extra_args = $_GET;
$method = $_SERVER['REQUEST_METHOD'];

// TODO: clean input arguments

switch($method) {
	case 'GET':
		switch($endpoint) {
			case 'search':
				search_db($args, $_GET);
				die();
			case 'pdf':
				retreive_pdf($args);
			default:
				http_response_code(404);
				die();
		}
	case 'POST':
		switch($endpoint) {
			default:
				http_response_code(404);
				die();
		}
	case 'PUT':
		switch($endpoint) {
			default:
				http_response_code(404);
				die();
		}
	case 'DELETE':
		switch($endpoint) {
			default:
				http_response_code(404);
				die();
		}
	default:
		http_response_code(404);
		die();
}

function is_tag($str) {
	return false;  // TODO: check for existence of tag in DB
}

function is_pdf_id($str) {
	return is_numeric($str); // TODO: check db for existence of pdf w/ id
}

function retrieve_pdf() {
	global $data_dir, $args;
	
	if(count($args) != 1 || !is_pdf_id($args[0])) {
		http_response_code(400);
		die();
	}
	
	$filename = get_pdf_details($args[0])['filename'];
	if(!$filename) {
		http_response_code(404);
		die();
	}
	
	header('Content-Type: application/pdf');
	readfile($data_dir, $filename);
}

function search_db() {
	global $args;
	
	if(count($args) > 1) {
		http_response_code(400);
		die();
	}
	
	// validate tag argument
	if(count($args) == 1 || isset($extra_args['tag'])) { // validate tag
		$tag = isset($args['tag']) ? $extra_args['tag'] : $args[0];
		if(!is_tag($tag)) {
			http_response_code(403);
			die();
		}
	}
	
	
	header("Content-Type: application/json");
	// TODO: query DB
}

?>
