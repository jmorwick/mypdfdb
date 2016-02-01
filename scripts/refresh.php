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
require_once("../services/lib.php");
echo "using datadir: $data_dir\n";
$data_dir = realpath($data_dir);
foreach (new RecursiveIteratorIterator 
	  (new RecursiveDirectoryIterator ($data_dir)) as $file) {
  $path = realpath($path);
  $path = substr($file->getPathname(), strlen($data_dir));
  
  if(preg_match("/\.(pdf|PDF)$/", $path)) {
    echo "looking up info for: $path\n";
    $id = get_pdf_id($path);
    $md5 = get_md5_hash($path);
    if($id) {
      $attributes = get_pdf_info($id);
      if($attributes['md5'] != $md5) {
        unset($attributes['id']);
        echo "fixing md5 info: $id: $path \n";
        $attributes['md5'] = $md5;
        print_r($attributes);
        update_pdf_info($id, $attributes);
      }
    } else {
      echo "inserting: $path \n";
      $attributes = array('md5' => $md5);
      print_r($attributes);
      add_pdf_to_db($path, $attributes);
    }
  }
}

$orphans = array();

foreach(find_pdfs_all() as $pdf_id) {
    echo "checking $pdf_id...";
    $pdf = get_pdf_info($pdf_id);
    if($pdf) {
      $path = $pdf['path'];
      $cpath = canonicalize_path($path);
      echo "path: $cpath\n";
      
      if(!file_exists(get_full_path($path))) {
        echo "*** file $cpath doesn't exist! adding to orphans list\n";
        $orphans[] = $pdf_id;
      } else if($path != $cpath) {
        echo "path not canoncial: $path -> $cpath\n";
        $other_pdf_id = get_pdf_id($cpath);
        if($other_pdf_id) {
          // TODO: merge metadata only -- they're using the same file
          echo "redundant path with $other_pdf_id: merge with $pdf_id \n";
          merge_pdf_metadata(array($pdf_id, $other_pdf_id), $cpath);
        } else {
          echo "update path to be canonical...\n";
          $pdf['path'] = $cpath;
          unset($pdf['id']);
          update_pdf_info($pdf_id, $pdf);
        }
      }
    }
}

echo "Total records: ".count(find_pdfs_all()). "\n";
echo "orphans: \n";
foreach($orphans as $pdf_id) {
  print_r(get_pdf_info($pdf_id));
  echo "deleting path info (making this pdf an orphan)...";
  orphan_pdf($pdf_id);
}
