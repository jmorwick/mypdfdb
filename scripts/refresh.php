<?php

function fatal_error($msg) {
	echo "$msg\n";
	die();
}

function err_bad_input_format($msg) {
	fatal_error("malformed input: $msg");
}

function err_bad_input_data($field, $value, $msg) {
	fatal_error("bad input value: '$field' can't have value '$value': $msg");
}

function err_internal($msg) {
	fatal_error($msg);
}

$data_dir = $argv[1];
echo $data_dir;
require_once("../services/lib.php");

foreach (new RecursiveIteratorIterator 
	  (new RecursiveDirectoryIterator ('/var/db/mypdfdb')) as $file) {
  $path = substr($file->getPathname(), strlen($data_dir));
  if(preg_match("/\.(pdf|PDF)$/", $path)) {
    $id = get_pdf_id($path);
    $md5 = get_md5_hash($path);
    if($id) {
      $attributes = get_pdf_info($id);
      unset($attributes['id']);
      echo "updating: $id: $path \n";
      $attributes['md5'] = $md5;
      print_r($attributes);
      update_pdf_info($id, $attributes);
    } else {
      echo "inserting: $path \n";
      $attributes = array('md5' => $md5);
      print_r($attributes);
      add_pdf_to_db($path, $attributes);
    }
  }
}
