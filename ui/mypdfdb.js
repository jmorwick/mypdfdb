var selectedRows = [];
var mainTable = null;

// general helpers

function styleTags(tags) {
  return (''+tags).split(",").map(
    function(tag){ 
      return '<div class="tag">'+tag.split('_').join(' ')+'</div>'; 
  }).join("");
}


// bonsai tree stuff for tag tree

var treeLoaded = false;
function loadTags() {
  $.ajax({
    url: 'api/tags',
    success: function(tags) {
      var used_tags = [];
      var tagTree = $(".tagTree");
      var tagSelector = $("#addTagDialog select[name='parent']");
      
      tagTree.empty();
      tagSelector.empty();
      tagSelector.append($("<option selected></option>")
        .attr("value",'')
        .text("*none")); 
      
      while(used_tags.length < tags.length) {
        tags.forEach(function (tag_record) {
          var rawTag = ''+tag_record.tag;
          var prettyTag = rawTag.split('_').join(' ');
          
          if(used_tags.indexOf(rawTag) > -1) {
          } else if(tag_record.parent != null) {
            if(used_tags.indexOf(tag_record.parent) > -1) {
              $("[data-tag="+tag_record.parent+"]")
                .append("<li>" + prettyTag + "<ul data-tag='" + 
                  rawTag + "'></ul></li>");
              tagSelector.append($("<option></option>")
                .attr("value",rawTag)
                .text(prettyTag)); 
              
              used_tags.push(rawTag);
            } else if(tags.indexOf(tag_record.parent == -1)) {
              console.log("ERROR: no parent tag for: ");
              console.log(tag_record);
              used_tags.push(rawTag);
            }
          } else {
            tagTree.append("<li>"+prettyTag+"<ul data-tag='"+rawTag+"'></ul></li>");
            
              tagSelector.append($("<option></option>")
                .attr("value",rawTag)
                .text(prettyTag)); 
            used_tags.push(rawTag);
          }
        });
      }
      if(!treeLoaded) {
        tagTree.bonsai({
          createInputs: 'checkbox',
          addSelectAll: 'true'
        });
      } else {
        tagTree.bonsai('update');
      }
      treeLoaded = true;
    }
  });
}


// dialog manipulation functions

function openAddTagDialog() {
  dialog = $('#addTagDialog');
  dialog.find('input[name="tag"]').val('');
  dialog.find('input[name="description"]').val('TODO / unimplemented');
  dialog.css("display", "block");
}
function closeAddTagDialog() {
  dialog = $('#addTagDialog');
  dialog.hide();
}


// initialization
$(function() { 
		
    // init datatable
    
    mainTable = $('#pdfInfoTableContainer').DataTable( {
        "ajax": 'api/search/',
        "columns": [
            { "data": "id" },
            { "data": "tags" },
            { "data": "title" },
            { "data": "date" },
            { "data": "pages" },
            { "data": "origin" },
            { "data": "recipient" }
        ],
        "columnDefs": [
            { "visible": false,  "targets": [ 0 ] },
            {
                "render": function ( data, type, row ) { // tags
                    // render spaces, add spaces after each tag in display
                    if(''+row['tags'] == '') return '';
                    return styleTags(row['tags']); 
                },
                "targets": 1
            },
            {
                "render": function ( data, type, row ) {
                    return row['title'] == null ? row['path'] : row['title'];
                },
                "targets": 2
            },
            {
                "render": function ( data, type, row ) {
                    return ' ('+ row['id']+')';
                },
                "targets": 6
            }
        ],
        "select": true
    } );
    
    
    mainTable.on( 'select', function ( e, dt, type, indexes ) {
      var tags = [];
      // find tags in selected rows
      mainTable.rows( { selected: true } ).data()
        .each(function(row){ 
      	  row.tags.forEach(function(tag) {
            if(tags.indexOf(tag) == -1) tags.push(tag);
          });
        });
        
      // unselect all tags
      $("input[type=checkbox]:checked").attr('checked', false);
      
      // select only the tags in the selected rows
      tags.forEach(function(tag) {
        $("[data-tag="+tag+']').siblings('input').click();
      });
    });
    
    $('.searchWithTags').click( function () { 
      selectedTags = $("input[type=checkbox]:checked").siblings('ul')
        .map(function(){return $(this).attr("data-tag");}).get();
      mainTable.ajax.url('api/search/'+selectedTags.join('/'));
      mainTable.ajax.reload();
    });
 
    $('.associateTags').click( function () { 
      selectedIds = mainTable.rows( { selected: true } ).ids()
        .map(function(row){return row.substring(4);});
      selectedTags = $("input[type=checkbox]:checked").siblings('ul')
        .map(function(){return $(this).attr("data-tag");}).get();
      
      $.ajax({
        url: 'api/tag/'+selectedTags.join('-')+'/'+selectedIds.join('-'),
        type: 'PUT',
        success: function(data) {
          mainTable.ajax.reload();
        }
      }); 
    });
    
    
    // init tag tree

    loadTags();
    
    
    // button listeners

    $('.disassociateTags').click( function () { 
      selectedIds = mainTable.rows( { selected: true } ).ids()
        .map(function(row){return row.substring(4);});
      selectedTags = $("input[type=checkbox]:checked").siblings('ul')
        .map(function(){return $(this).attr("data-tag");}).get();
      
      $.ajax({
        url: 'api/untag/'+selectedTags.join('-')+'/'+selectedIds.join('-'),
        type: 'PUT',
        success: function(data) {
          mainTable.ajax.reload();
        }
      }); 
    });
    
    $('.deleteTags').click( function () { 
      selectedTags = $("input[type=checkbox]:checked").siblings('ul')
        .map(function(){return $(this).attr("data-tag");}).get();
      if(selectedTags.length > 0) {
          $('<div></div>').appendTo('body')
            .html('Are you sure you want to delete the tags: '+styleTags(selectedTags)+'? ' + 
              'This will also alter any records using this tag and cannot be (easily) undone.')
            .dialog({
              modal: true,
              title: 'remove tag?',
              zIndex: 1000000,
              autoOpen: true,
              width: 'auto',
              resizable: false,
              buttons: {
                Yes: function () {
                  $.ajax({
                    url: 'api/deletetags/'+selectedTags.join('/'),
                    type: 'DELETE',
                    success: function(data) {
                      loadTags();
                      mainTable.ajax.reload();
                    }
                  });
                  $(this).dialog("close");
                },
                Cancel: function () {
                  $(this).dialog("close");
                }
              },
              close: function (event, ui) {
                $(this).remove();
              }
            });
      }   
    });
    
    
    $('.addTag').click(function() {openAddTagDialog();}); // TODO: figure out why the closure is needed instead of just openAddTagDialog
    $('.addTagDialogCancel').click(function() {closeAddTagDialog();});
    $('.addTagDialogSubmit').click( function () { 
      // fetch tag name in lower case with spaces replaced by _'s
      var name = $('#addTagDialog input[name="tag"]').val().toLowerCase().split(' ').join('_');
      var description = $('#addTagDialog input[name="description"]').val();
      var parent = $('#addTagDialog select[name="parent"] option:selected').val();
      if(name.search("^[a-z0-9_]+$") == -1) {
        alert("tag names must be non-empty and consist only of numbers, letters, and spaces");
      } else {
      	var urlCommand = 'api/createtag/'+name + 
            (parent != '' ? '/'+parent : '');
        console.log(urlCommand);
        $.ajax({
          url: urlCommand,
          type: 'PUT',
          success: function(data) {
            console.log(data);
            loadTags();
          }
        });
        closeAddTagDialog();
      }
    });
    
    
    $('.viewPDF').click(function() {
      selectedIds = mainTable.rows( { selected: true } ).ids()
        .map(function(row){return row.substring(4);});
      if(selectedIds.length == 0) {
        alert("you must have a pdf in the table selected");
        return;
      } else if(selectedIds.length > 1) {
        alert("you must have exactly one pdf in the table selected");
        return;
      }
      
      alert("TODO: not implemented -- open iframe view of pdf: " + selectedIds[0]);
      
    });
    
    $('.downloadPDF').click(function() {
      selectedIds = mainTable.rows( { selected: true } ).ids()
        .map(function(row){return row.substring(4);});
      if(selectedIds.length == 0) {
        alert("you must have a pdf in the table selected");
        return;
      } else if(selectedIds.length > 1) {
        alert("you must have exactly one pdf in the table selected");
        return;
      }
      
      window.open('api/pdf/'+selectedIds[0],'_blank');
      
    });
    
});
