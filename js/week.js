$(document).ready(function(){
    $(".week-daycheck").each(function() { 
        checkCounting($(this));
    });
    
    $(".week-day-card").click(function(){
        location.href = '?type=dayroster&ts='+$(this).data('timestamp'); 
    });
    
    /*$(".elgroup").sortable({
        connectWith: '.elgroup',
        cancel: ".fixed",
        stop: function(e, ui){
            $(".week-daycheck").each(function() { 
                checkCounting($(this));
            });
        }
    }).disableSelection();*/
});

function prevWeek(week,year){
    location.href = '?type=weekroster&week='+(week-1)+'&month=-1&year='+year; 
}

function nextWeek(week,year){
    location.href = '?type=weekroster&week='+(week+1)+'&month=-1&year='+year; 
}