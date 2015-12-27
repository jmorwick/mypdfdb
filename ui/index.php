<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8"/>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.4/jquery.min.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.4/jquery-ui.min.js"></script>
<script src="jquery-dynatable/jquery.dynatable.js"></script>
<script src="jquery-bonsai/jquery.bonsai.js"></script>
<script src="mypdfdb.js"></script>
<link rel="stylesheet" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.4/themes/smoothness/jquery-ui.css"></link>
<link rel="stylesheet" href="mypdfdb.css"></link
<link rel="stylesheet" href="jquery-dynatable/jquery.dynatable.css"></link>
<link rel="stylesheet" href="jquery-bonsai/jquery.bonsai.css"></link>
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

<table id="pdfInfoTableContainer">
	<thead>
		<th>tags</th>
		<th>title</th>
		<th>date</th>
		<th>pages</th>
		<th>origin</th>
		<th>recipient</th>
		<th>view</th>
	</thead>
</table>
	<a class="associateTag button">show records with selected tags</a>
	<a class="associateTag button">tag selected records</a>
	<a class="disassociateTag button">untag selected records</a>

</body>

</html>
