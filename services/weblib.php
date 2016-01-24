<?php

////////////////////////////////////////////////////////////////////////////////
// define error message functions
////////////////////////////////////////////////////////////////////////////////

function fatal_error($msg, $code) {
	error_log($msg);
	http_response_code($code);
	echo $msg;
	die();
}

function err_no_such_service($service) {
	fatal_error("404: no such service: $service", 404);	
}

function err_bad_input_format($msg) {
	fatal_error("403: malformed input to service: $msg", 403);
}

function err_bad_input_data($field, $value, $msg) {
	fatal_error("403: bad input value for service: '$field' can't have value '$value': $msg", 403);
}

function err_internal($msg) {
	fatal_error("500: internal error: $msg", 500);
}

// bring in library functions (error functions must be defined first)
require_once("lib.php");


////////////////////////////////////////////////////////////////////////////////
// helper functions
////////////////////////////////////////////////////////////////////////////////

function prepare_pdf_record($id) {
	global $db;
	$record = get_pdf_info($id);
	$record['tags'] = find_tags_for_pdf($id);
	$record['DT_RowId'] = "row_$id"; // needed for datatable plugin
	return $record;
}

////////////////////////////////////////////////////////////////////////////////
// servicing functions
////////////////////////////////////////////////////////////////////////////////

function service_search_db($args) {	
	$pdf_ids = array();
	
	if($args) foreach($args as $tag) {
		if(!get_tag_info($tag)) 
			err_bad_input_data('tag', $tag, "doesn't exist");
		$pdf_ids = array_unique(array_merge($pdf_ids, find_pdfs_with_tag($tag)));
	} else {
		$pdf_ids = find_pdfs_with_no_tag();
	}
	
	$pdfs = array();
	if($pdf_ids) foreach($pdf_ids as $pdf_id)
		$pdfs[] = prepare_pdf_record($pdf_id);
	echo json_encode(array( 'data' => $pdfs));
}

function service_retrieve_pdf($args) {	
	global $data_dir;

	if(count($args) < 1 || count($args) > 2)
		err_bad_input_format("expected one or two arguments in URL");
	
	$details = get_pdf_info($args[0]);
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

function service_update_pdf($args, $fields) {	
	if(count($args) == 0)
		err_bad_input_format("expected at least one argument in URL");
	
	if(!get_pdf_info($args[0]))
		err_bad_input_data('file_id', $args[0], 'not a valid pdf id');
	
	foreach($args as $pdf_id) {
		update_pdf_info($pdf_id, $fields);
	}
}

function service_retrieve_tags() {
	global $db;
	header("Content-Type: application/json");
	$tags = array();
	$res = $db->query("SELECT * FROM tag_info");
	while($row = $res->fetchArray(SQLITE3_ASSOC))
		$tags[] = $row;
	echo json_encode($tags);
}

function service_associate_tags($args) {	
	if(count($args) != 2)
		err_bad_input_format("expected two argument in URL (dash-separated list of tag ids followed by dash-separated list of pdf ids)");
	$tags = explode('-',$args[0]);
	$pdfs = explode('-',$args[1]);
	foreach($tags as $tag) 
	  if(!get_tag_info($tag)) 
	    err_bad_input_data('tagid', $tag, 'not a valid tag id');
	foreach($pdfs as $pdf) 
	  if(!get_pdf_info($pdf)) 
	    err_bad_input_data('pdfid', $pdf, 'not a valid pdf id');
    
    	foreach($pdfs as $pdf_id) {
    	  foreach($tags as $tag) {
    	    tag_pdf($pdf_id, $tag);
    	  }
    	}
}

function service_disassociate_tags($args) {	
	if(count($args) != 2)
		err_bad_input_format("expected two argument in URL (dash-separated list of tag ids followed by dash-separated list of pdf ids)");
	$tags = explode('-',$args[0]);
	$pdf_ids = explode('-',$args[1]);
	foreach($tags as $tag) 
	  if(!get_tag_info($tag)) 
	    err_bad_input_data('tagid', $tag, 'not a valid tag id');
	foreach($pdf_ids as $pdf_id) 
	  if(!get_pdf_info($pdf_id)) 
	    err_bad_input_data('pdfid', $pdf_id, 'not a valid pdf id');
    
    	foreach($pdf_ids as $pdf_id) {
    	  foreach($tags as $tag) {
    	    untag_pdf($pdf_id, $tag);
    	  }
    	}
}

function service_create_tag($args) {
	if(count($args) == 0 || count($args) > 2)
		err_bad_input_format("expected 1-2 arguments in URL (tag, parent tag)");
	
	if(count($args) == 1) {
		create_tag($args[0]);
	} else {
		create_tag($args[0], $args[1]);
	}
}


function service_delete_tags($args) {	
	if(count($args) == 0)
		err_bad_input_format("expected at least 1 argument in URL (one or more tags)");
	
	// validate tag arguments (don't start deleting just to stop half-way)
	foreach($args as $tag) {
		if(!get_tag_info($tag)) 
			err_bad_input_data('tag', $tag, "not a valid tag");		
	}
	
	// delete tags
	foreach($args as $tag) {
		delete_tag($tag);
	}
}


function service_delete_pdfs($args) {	
	if(count($args) == 0)
		err_bad_input_format("expected at least 1 argument in URL (one or more pdf ids)");
	
	// validate pdf arguments
	foreach($args as $pdf_id) 
	  if(!get_pdf_info($pdf_id)) 
	    err_bad_input_data('pdfid', $pdf_id, 'not a valid pdf id');		
	
	// delete pdfs
	foreach($args as $pdf_id) {
		delete_pdf($pdf_id);
	}	

}


function service_merge_pdfs($args) { // TODO: this entire function should be gaurded by some sort of mutex
	global $db, $data_dir;
	
	if(count($args) == 0)
		err_bad_input_format("expected at least 1 argument in URL (one or more pdf ids)");
	
	// validate pdf arguments
	$paths = array();
	$attributes = array();
	$tags = array();
	$total_pages = 0;
	foreach($args as $pdf_id) {
	  $pdf = get_pdf_info($pdf_id);
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
    	if(file_exists("/usr/bin/pdftk")) $merge_cmd = "/usr/bin/pdftk";
    	else $merge_cmd = "/usr/local/bin/pdftk";
    	foreach($paths as $path) {
    	  $merge_cmd .= " ".escapeshellarg($data_dir."/".$path)." ";
    	}
    	$merge_cmd .= " cat output ".escapeshellarg($data_dir."/".$new_path);
    	error_log($merge_cmd);
    	$res = shell_exec($merge_cmd);
    	error_log("merging files: $merge_cmd  --> $res");
    	foreach($attributes as $k => $v) error_log(" $k => $v");
    	foreach($tags as $v) error_log(" tag: $v");
    	$md5 = md5_file($data_dir."/".$new_path);
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
        service_delete_pdfs($args);
}

