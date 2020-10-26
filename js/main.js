$(document).ready(function(){
    
});

$('#setPin').keydown( function(e)
{
    if(e.keyCode == 13) setPin();
});

function setPin(){
    var pin = $("#setPin").val();
    $.ajax({
        type: 'POST',
        url: 'src/functions/login.php',
        data: {pin: pin}
    }).done(function(data) {
        if (data != false){
            var obj = jQuery.parseJSON(data);
            if (obj.uid != null){
                location.reload();
            } else {
                alert("Falsche PIN, bitte erneut versuchen");    
            }
        } else {
            alert("Falsche PIN, bitte erneut versuchen");
        }
    }); 
}

function unsetPin(){
    $.ajax({
        type: 'POST',
        url: 'src/functions/logout.php'
    }).done(function(){
        location.href = '?type=main';
    });
}

function isValidDate(dateString) {
  var regEx = /^\d{2}.\d{2}.\d{4}$/;
  if(!dateString.match(regEx)) return false;  // Invalid format
  /*var d = new Date(dateString);
  var dNum = d.getTime();
  if(!dNum && dNum !== 0) return false; // NaN value, Invalid date
  return d.toISOString().slice(0,10) === dateString;*/
  return true;
}


function checkCounting($element){
    $count = $element.find('ul.list-group li.counting').length;
    $element.data('given',$count);
    $element.find('.acount').text($count);
    //$(this).data('given',$counter);
    if ($element.data('needed') > $element.data('given')){
        $element.find('.info').css('color', 'red');
    } else if ($element.data('needed') == $element.data('given')) {
        $element.find('.info').css('color', 'green');
    } else {
        $element.find('.info').css('color', 'orange');
    }
}