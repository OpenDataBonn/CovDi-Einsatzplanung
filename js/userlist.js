  $( function() {
    $(".user_info" ).dialog({
        autoOpen: false,
        height: 'auto',
        width: 500,
        position: { my: 'center', at: 'center+350' },
    });
      
    $(".popupInfo").mouseover(function(){
        var lid = $(this).data("id");
        $(".user_info."+lid).dialog("open");
    })
      
    $(".popupInfo").mouseout(function(){
        var lid = $(this).data("id");
        $(".user_info."+lid).dialog("close");
    })
  } );

function delLastDialog(nid){
    $("#nm_delete_confirm" ).dialog({
        resizable: false,
        height: "auto",
        width: 400,
        buttons: [
            {
                text: "Löschen",
                "class": "btn btn-danger",
                click: function(){                    
                    $(this).dialog( "close" );
                    delLast(this, nid);
                    $(document).ajaxStop(function() { location.reload(true); });
                }
            },
            {
                text: "Abbrechen",
                "class": "btn btn-secondary",
                click: function(){
                    $( this ).dialog( "close" );
                }
            }
        ]                      
    });
}

function delLast(elem, nid){
    var url = "src/data/deleteNM.php";
    var data = "nmId="+nid;
    $.ajax({
        type: "POST",
        url: url,
        data: data, 
        success: function(deleted)
        {
           if (deleted){

           } else {
               var modal = $('#modalError');
               modal.find('.modal-body').text('Fehler beim löschen der Meldung! Wurden bereits Einträge verplant?');
               modal.modal();
           }
        }
    });    
}