<?php

////////////////////////////////////////////////////////////////////////////////
// error messages
////////////////////////////////////////////////////////////////////////////////

function err_no_such_service($service) {
	echo "404: no such service: $service";	
	http_response_code(404);
	die();
}

function err_bad_input_format($msg) {
	echo "404: malformed input to service: $msg";	
	http_response_code(404);
	die();
}

function err_bad_input_data($field, $value, $msg) {
	echo "404: bad input value for service: '$field' can't have value '$value': $msg";	
	http_response_code(403);
	die();
}

function err_internal($msg) {
	echo "500: internal error: $msg";	
	http_response_code(500);
	die();
}

////////////////////////////////////////////////////////////////////////////////
// begin service handling script
////////////////////////////////////////////////////////////////////////////////

header("Access-Control-Allow-Orgin: *");
header("Access-Control-Allow-Methods: *");

$data_dir = getenv('DATA_DIR');
$db = new SQLite3($data_dir.'/.mypdfdb');

// extract arguments from request
$args = explode('/', rtrim($_GET['request'], '/'));
$endpoint = array_shift($args);
unset($_GET['request']);
$method = $_SERVER['REQUEST_METHOD'];

// TODO: clean input arguments
switch($method) {
	case 'GET':
		switch($endpoint) {
			case 'search':
				search_db($args);
				exit();
			case 'pdf':
				retrieve_pdf($args);
				exit();
			case 'tags':
				retrieve_tags();
				exit();
			default: err_no_such_service($endpoint);
		}
	case 'POST':
		switch($endpoint) {
			case 'updatepdf':
				update_pdf($args, $_POST);
				exit();
			case 'mergepdfs':
				merge_pdfs($args);
				exit();
			default: err_no_such_service($endpoint);
		}
	case 'PUT':
		switch($endpoint) {
			case 'tag':
				associate_tags($args);
				exit();
			case 'createtag':
				create_tag($args);
				exit();
			case 'untag':
				disassociate_tags($args);
				exit();
			default: err_no_such_service($endpoint);
		}
	case 'DELETE':
		switch($endpoint) {
			case 'deletetags':
				delete_tags($args);
				exit();
			case 'deletepdfs':
				delete_pdfs($args);
				exit();
			default: err_no_such_service($endpoint);
		}
	default: err_no_such_service($endpoint);
}


////////////////////////////////////////////////////////////////////////////////
// helper functions
////////////////////////////////////////////////////////////////////////////////

function prepare_pdf_record($record) {
	global $db;
	$record['tags'] = array();
	$res = $db->query("SELECT tag FROM tags WHERE file_id = '".$record['id']."'");
	while($row = $res->fetchArray(SQLITE3_ASSOC)) {
		$record['tags'][] = $row['tag'];
	}
	$record['DT_RowId'] = 'row_'.$record['id'];
	return $record;
}

function prepare_tag_record($record) {
	return $record;
}

function is_tag($str) {
	global $db;
	return $db->querySingle("SELECT tag FROM tag_info WHERE tag='$str'");
}

function get_pdf_details($id) {
	global $db;
	if(is_numeric($id)) {
		$res = $db->query("SELECT * FROM files WHERE id = '$id'");
		return $res->fetchArray(SQLITE3_ASSOC);
	}
	return false;
}

////////////////////////////////////////////////////////////////////////////////
// servicing functions
////////////////////////////////////////////////////////////////////////////////

function search_db($args) {
	global $db;
	
	$tag_queue = array();
	$tags = array();
	
	// validate tag arguments
	foreach($args as $tag) {
		if(!is_tag($tag)) 
			err_bad_input_data('tag', $tag, "not a valid tag");
		$tag_queue[] = $tag;
		
	}
	
	// find all child tags
	while(count($tag_queue)) {
		$tag = array_shift($tag_queue);
		$tags[] = $tag;
		$res = $db->query("SELECT tag FROM tag_info WHERE parent = '$tag'");
		while($row = $res->fetchArray(SQLITE3_ASSOC)) {
			$tag_queue[] = $row['tag'];
		}
	}
	
	header("Content-Type: application/json");
	if($tags) {
		$queries = array();
		foreach($tags as $tag) {
			$queries[] = "SELECT files.* FROM files, tags WHERE tags.file_id = files.id AND tags.tag = '$tag'";
		}
		$res = $db->query(implode(" UNION " , $queries));
	} else {
		$res = $db->query("SELECT files.* FROM files");
	}
	$pdfs = array();
	while($row = $res->fetchArray(SQLITE3_ASSOC))
		$pdfs[] = prepare_pdf_record($row);
	echo json_encode(array( 'data' => $pdfs));
}


function retrieve_pdf() {
	global $data_dir, $args;
	
	if(count($args) < 1 || count($args) > 2)
		err_bad_input_format("expected one or two arguments in URL");
	
	$details = get_pdf_details($args[0]);
	if(!$details)
		err_bad_input_data('file_id', $args[0], 'not a valid pdf id');
	
	$filename = $details['path'];
	if(!$filename) err_bad_input_data('file_id', $args[0], "pdf file missing from database");
	$downloadfilename = ($details['title'] != null && strlen($details['title']) > 0) ? 
		$details['title'] . ".pdf" : $filename;
	header('Content-Type: application/pdf');
	header("Content-Disposition:".
		(count($args) == 2 ? 'inline' : 'attachment').
		";filename='$downloadfilename'");
	readfile($data_dir.'/'.$filename);
}

function update_pdf($args, $fields) {
	global $db, $data_dir;
	
	if(count($args) == 0)
		err_bad_input_format("expected at least one argument in URL");
	
	$details = get_pdf_details($args[0]);
	if(!$details)
		err_bad_input_data('file_id', $args[0], 'not a valid pdf id');
	foreach($args as $id) {
	$fields_sql = array();	
		foreach($fields as $field => $value) {
			if(!array_key_exists($field, $details) || in_array($field , array('path', 'md5', 'pages', 'id')))
				err_bad_input_data($field, '', 'not a valid field');
			$fields_sql[] = "`".addslashes($field)."` = ".($value ? "'".addslashes($value)."'" : "NULL");
		} 
		$db->exec("UPDATE files SET " . implode($fields_sql, ',') . " WHERE id = $id");
	}
	
}

function retrieve_tags() {
	global $db;
	header("Content-Type: application/json");
	$tags = array();
	$res = $db->query("SELECT * FROM tag_info");
	while($row = $res->fetchArray(SQLITE3_ASSOC))
		$tags[] = prepare_pdf_record($row);
	echo json_encode($tags);
}

function associate_tags($args) {
	global $db;
	
	if(count($args) != 2)
		err_bad_input_format("expected two argument in URL (dash-separated list of tag ids followed by dash-separated list of pdf ids)");
	$tags = explode('-',$args[0]);
	$pdfs = explode('-',$args[1]);
	foreach($tags as $tag) 
	  if(!is_tag($tag)) 
	    err_bad_input_data('tagid', $tag, 'not a valid tag id');
	foreach($pdfs as $pdf) 
	  if(!get_pdf_details($pdf)) 
	    err_bad_input_data('pdfid', $pdf, 'not a valid pdf id');
    
    	foreach($pdfs as $pdf) {
    	  foreach($tags as $tag) {
    	    if(!$db->querySingle("SELECT * FROM tags WHERE file_id = '$pdf' AND tag = '$tag'")) {
    	      $db->exec("INSERT INTO tags VALUES ('$pdf', '$tag')");
    	    }
    	  }
    	}
}

function disassociate_tags($args) {
	global $db;
	
	if(count($args) != 2)
		err_bad_input_format("expected two argument in URL (dash-separated list of tag ids followed by dash-separated list of pdf ids)");
	$tags = explode('-',$args[0]);
	$pdfs = explode('-',$args[1]);
	foreach($tags as $tag) 
	  if(!is_tag($tag)) 
	    err_bad_input_data('tagid', $tag, 'not a valid tag id');
	foreach($pdfs as $pdf) 
	  if(!get_pdf_details($pdf)) 
	    err_bad_input_data('pdfid', $pdf, 'not a valid pdf id');
    
    	foreach($pdfs as $pdf) {
    	  foreach($tags as $tag) {
    	    $db->exec("DELETE FROM tags WHERE file_id = '$pdf' AND tag = '$tag'");
    	  }
    	}
}

function create_tag($args) {
	global $db;
	if(count($args) == 0 || count($args) > 2)
		err_bad_input_format("expected 1-2 arguments in URL (tag, parent tag)");
	
	$tag = $args[0];
	$parent = count($args) == 2 ? $args[1] : null;
	
	if(is_tag($tag))
		err_bad_input_data('tag', $tag, 'already exists');
	
	if($parent != null && !is_tag($parent))
		err_bad_input_data('parent', $parent, "doesn't exist");
	
	// TODO: validate tag format (a-z or _ only)
	
	$db->exec("INSERT INTO tag_info VALUES ('".$tag."',".
		(count($args) > 1 ? "'".$parent."'" : "NULL").",NULL)");
}


function delete_tags($args) {
	global $db;
	
	if(count($args) == 0)
		err_bad_input_format("expected at least 1 argument in URL (one or more tags)");
	
	// validate tag arguments
	foreach($args as $tag) {
		if(!is_tag($tag)) 
			err_bad_input_data('tag', $tag, "not a valid tag");		
	}
	
	// delete tags
	foreach($args as $tag) {
		$parent = $db->querySingle("SELECT parent FROM tag_info WHERE tag='$tag'");
		$db->exec("BEGIN TRANSACTION");	
		if($parent) {
		  $db->exec("UPDATE tags SET tag = '$parent' WHERE tag = '$tag'");
		  $db->exec("UPDATE tag_info SET parent = '$parent' WHERE parent = '$tag'");
		} else {
		  $db->exec("DELETE FROM tags WHERE tag = '$tag'");
		  $db->exec("UPDATE tag_info SET parent = NULL WHERE parent = '$tag'");
		}
		
		$db->exec("DELETE FROM tag_info WHERE tag='$tag'");
		$db->exec("COMMIT TRANSACTION");	
	}
	
}


function delete_pdfs($args) {
	global $db, $data_dir;
	
	if(count($args) == 0)
		err_bad_input_format("expected at least 1 argument in URL (one or more pdf ids)");
	
	// validate pdf arguments
	foreach($args as $pdf_id) 
	  if(!get_pdf_details($pdf_id)) 
	    err_bad_input_data('pdfid', $pdf_id, 'not a valid pdf id');		
	
	// delete pdfs
	$db->exec("BEGIN TRANSACTION");	
	foreach($args as $pdf_id) {
		$pdf = get_pdf_details($pdf_id);
		unlink($data_dir.'/'.$pdf['path']);
		$db->exec("DELETE FROM tags WHERE file_id='$pdf_id'");
		$db->exec("DELETE FROM files WHERE id='$pdf_id'");
	}
	$db->exec("COMMIT TRANSACTION");	

}


function merge_pdfs($args) { // TODO: this entire function should be gaurded by some sort of mutex
	global $db, $data_dir;
	
	if(count($args) == 0)
		err_bad_input_format("expected at least 1 argument in URL (one or more pdf ids)");
	
	// validate pdf arguments
	$paths = array();
	$attributes = array();
	$tags = array();
	$total_pages = 0;
	foreach($args as $pdf_id) {
	  $pdf = get_pdf_details($pdf_id);
	  if(!$pdf) 
	    err_bad_input_data('pdfid', $pdf_id, 'not a valid pdf id');	
          $paths[] = $pdf['path'];
          if(!isset($attributes['path']) && $pdf['path']) 
            $attributes['path'] = $pdf['path'];
          if(!isset($attributes['title']) && $pdf['title']) 
            $attributes['title'] = $pdf['title'];
          if(!isset($attributes['pages']) && $pdf['pages']) 
            $pages += $pdf['pages'];
          if(!isset($attributes['date']) && $pdf['date']) 
            $attributes['date'] = $pdf['date'];
          if(!isset($attributes['origin']) && $pdf['origin']) 
            $attributes['origin'] = $pdf['origin'];
          if(!isset($attributes['recipient']) && $pdf['recipient']) 
            $attributes['recipient'] = $pdf['recipient'];
          $pdf = prepare_pdf_record($pdf);
          $tags = array_unique(array_merge($tags, $pdf['tags']));
    	}
    	$i=1;
    	$new_path = substr($attributes['path'], 0, -4)."$i.pdf";
    	while(file_exists($data_dir."/".$new_path)) {
    	  $i++;
    	  $new_path = substr($new_path, 0, -(4 + strlen("".($i-1))))."$i.pdf";
    	}

    	$merge_cmd = "/usr/local/bin/pdftk ";
    	foreach($paths as $path) {
    	  $merge_cmd .= escapeshellarg($data_dir."/".$path)." ";
    	}
    	$merge_cmd .= " cat output ".escapeshellarg($data_dir."/".$new_path);
    	error_log($merge_cmd);
    	$res = shell_exec($merge_cmd);
    	error_log("merging files: $merge_cmd  --> $res");
    	foreach($attributes as $k => $v) error_log(" $k => $v");
    	foreach($tags as $v) error_log(" tag: $v");
    	$md5 = trim(shell_exec("md5 < ".escapeshellarg($data_dir."/".$new_path)));
    	if(!$md5) err_internal("could not merge files");
    	$sql="INSERT INTO files VALUES (NULL, '$new_path'".
    		', '.($attributes['title'] ? "'".addslashes($attributes['title'])."'":'NULL').
    		', '.($md5 ? "'$md5'":'NULL').
    		', '.($attributes['date'] ? "'".addslashes($attributes['date'])."'":'NULL').
    		", ".$total_pages.
    		', '.($attributes['origin'] ? "'".addslashes($attributes['origin'])."'":'NULL').
    		', '.($attributes['recipient'] ? "'".addslashes($attributes['recipient'])."'":'NULL').
    		")";
    	$db->exec($sql);
        $new_id = $db->lastInsertRowID();
    	if(!$new_id) err_internal("could not insert merged file to db");
        foreach($tags as $tag) {
          $db->exec("INSERT INTO tags VALUES ($new_id, '$tag')");
        }
        delete_pdfs($args);
}

?>
