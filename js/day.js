$(document).ready(function(){
    $(".day-daycheck").each(function() { 
        checkCounting($(this));
    });
    
    $(".elgroup").sortable({
        connectWith: '.elgroup',
        cancel: ".fixed",
        stop: function(e, ui){
            $(".day-daycheck").each(function() { 
                checkCounting($(this));
            });
            //Haken automatisch setzen, wenn einer Schicht zugeteilt
            //alert($(ui.item).parents('.col').data('schicht'));
            if ($(ui.item).parents('.col').data('schicht')){
                $(ui.item).find(".addToPlan").prop('checked', true);    
            } else {
                $(ui.item).find(".addToPlan").prop('checked', false);    
            }            
        }
    }).disableSelection();
});

function prevDay(tstamp){
    location.href = '?type=dayroster&ts='+(tstamp-(24*60*60)); 
}

function nextDay(tstamp){
    location.href = '?type=dayroster&ts='+(tstamp+(24*60*60)); 
}

function checkAlltoPlan(){
    $('.addToPlan:checkbox').each(function(){
        if ($(this).parents('.col').data('schicht')){
            $(this).prop('checked', true); 
        }        
    });
}

function deletePlan(elem, plan_id){
    deletePlanDialog(plan_id);
}

function deletePlanDialog(plan_id){
    $("#plan_delete_confirm" ).dialog({
        resizable: false,
        height: "auto",
        width: 400,
        buttons: [
            {
                text: "Löschen",
                "class": "btn btn-danger",
                click: function(){                    
                    $(this).dialog( "close" );
                    deletePlans(this, plan_id);
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

function deletePlans(elem, plan_id){
    var url = "src/data/deleteP.php";
    var data = "planId="+plan_id;
    $.ajax({
        type: "POST",
        url: url,
        data: data, 
        success: function(deleted)
        {
           if (deleted){

           } else {
               var modal = $('#modalError');
               modal.find('.modal-body').text('Fehler beim speichern!');
               modal.modal();
           }
        }
    });    
}

function createPlan(elem, wunsch_id, nutzer_id){
    if ($(elem).parents('.springer').length) {   
        var modal = $('#modalError');
        //modal.find('.modal-title').text('Speichern');
        modal.find('.modal-body').text('Springer können nicht als Springer gebucht werden!'+"\n"+'Bitte teilen Sie den Springer erst einer Schicht zu.');
        modal.modal();
        return false;
    } else {
        $(elem).parent().parent().find('.addToPlan').prop('checked', true);
        createPlanDialog();
    }
}

function createPlanDialog(){
    $("#plan_create_confirm" ).dialog({
        resizable: false,
        height: "auto",
        width: 400,
        buttons: [
            {
                text: "Speichern",
                "class": "btn btn-success",
                click: function(){                    
                    $(this).dialog( "close" );
                    createPlans(this);
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

function createPlans(elem){
    $('.addToPlan:checkbox:checked').each(function(){
        var taetigkeit = $(this).parents('.day-daycheck').data('taetigkeit');
        var schicht = $(this).parents('.col').data('schicht');
        var timestamp = $(this).parents('.dayrow').data('timestamp');
        if ($(this).parents('.list-group-item').data('wunschid')) var wunsch_id = $(this).parents('.list-group-item').data('wunschid');
        else var wunsch_id = -1;
        if ($(this).parents('.list-group-item').data('nutzerid')) var nutzer_id = $(this).parents('.list-group-item').data('nutzerid');
        if ($(this).parents('.list-group-item').data('unknownid')) var nutzer_id = $(this).parents('.list-group-item').data('unknownid');
        
        if (taetigkeit && schicht) {
            var url = "src/data/addNewP.php";
            var data = "wunschId="+wunsch_id+"&taetigkeit="+taetigkeit+"&schicht="+schicht+"&ts="+timestamp+"&nutzer="+nutzer_id;
            //alert(data);
            $.ajax({
                type: "POST",
                url: url,
                data: data, 
                success: function(plan_id)
                {
                   if (plan_id){

                   } else {
                       var modal = $('#modalError');
                       modal.find('.modal-body').text('Fehler beim speichern!');
                       modal.modal();
                   }
                }
            });
        }
    })
}

function removeWuenscheForNutzer(nutzer_id){
    $(".nutzer_"+nutzer_id).remove();
}

function removeWunsch(wunsch_id){
    $(".wunsch_"+wunsch_id).remove();
}
