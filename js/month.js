$(document).ready(function(){
    $(".month-daycheck").each(function() { 
        if ($(this).data('needed') > $(this).data('given')){
            $(this).css('color', 'red');
        } else if ($(this).data('needed') == $(this).data('given')) {
            $(this).css('color', 'green');
        } else {
            $(this).css('color', 'orange');
        }
    });
    $(".month-day-card").click(function(){
        location.href = '?type=weekroster&ts='+$(this).data('timestamp'); 
    });
});

function prevMonth(month,year){
    location.href = '?type=roster&month='+(month-1)+'&year='+year; 
}

function nextMonth(month,year){
    location.href = '?type=roster&month='+(month+1)+'&year='+year; 
}

function gotToMonth(){
    location.href = '?type=roster&month='+$('#monthToShow').val()+'&year='+$('#yearToShow').val(); 
}