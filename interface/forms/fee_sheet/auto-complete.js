var MIN_LENGTH = 3;

$( document ).ready(function() {
	$("#keyword").keyup(function() {
		var keyword = $("#keyword").val();
		if (keyword.length >= MIN_LENGTH) 
		{
			$.get( "/openemr/interface/forms/fee_sheet/auto-complete.php", { keyword: keyword } )
			.done(function( data ) 
			{
				$('#results').html('');
				var results = jQuery.parseJSON(data);
				$(results).each(
				function(key, value) 
				{
					$('#results').append('<div class="item">' + value.key + value.value + '</div>');
				})

			    $('.item').click(function() {
				var res = $(this).html().split("|");
				$('#codeValue').val("CPT4|90832|");

			    	var text = $(this).html();
			    	$('#keyword').val(text);
			    })

			});
		} else {
			$('#results').html('');
		}
	});

    $("#keyword").blur(function(){
    		$("#results").fadeOut(500);
    	})
        .focus(function() {		
    	    $("#results").show();
    	});

});
