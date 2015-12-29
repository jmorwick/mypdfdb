<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8"/>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.4/jquery.min.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.4/jquery-ui.min.js"></script>
<script src="https://cdn.datatables.net/1.10.10/js/jquery.dataTables.min.js"></script>
<script src="jquery-bonsai/jquery.bonsai.js"></script>
<script src="https://cdn.datatables.net/select/1.1.0/js/dataTables.select.min.js"></script>
<script src="mypdfdb.js"></script>
<link rel="stylesheet" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.4/themes/smoothness/jquery-ui.css"></link>
<link rel="stylesheet" href="mypdfdb.css"></link
<link rel="stylesheet" href="https://cdn.datatables.net/1.10.10/css/jquery.dataTables.min.css"></link>
<link rel="stylesheet" href="jquery-bonsai/jquery.bonsai.css"></link>
<link rel="stylesheet" href="https://cdn.datatables.net/select/1.1.0/css/select.dataTables.min.css"></link>
<title>MyPDF DB</title>
</head>

<body>

<header>
	<h1>myPDF DB</h1>
</header>

<div class="searchbar">
</div>

<div class="optionsbar">
	<ul class="tagTree">
	</ul>
	<a class="addTag button">add</a>
	<a class="editTag button">edit</a>
</div>

<table id="pdfInfoTableContainer" class="display" cellspacing="0" width="100%">
	<thead>
		<th>id</th>
		<th>tags</th>
		<th>title</th>
		<th>date</th>
		<th>pages</th>
		<th>origin</th>
		<th>recipient</th>
		<th>view</th>
	</thead>
</table>
<a class="searchWithTags button">show records with selected tags</a>
<a class="associateTags button">tag selected records</a>
<a class="disassociateTags button">untag selected records</a>
<a class="unselectAll button">unselect all records</a>
</body>

</html>
