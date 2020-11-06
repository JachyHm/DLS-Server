/**
* @author Resalat Haque
* @link http://www.w3bees.com
*/


$(document).ready(function() {
	/* variables */
	var status = $('.status');
	var percent = $('.percent');
	var bar = $('.bar');

	/* submit form with ajax request */
	$('form').ajaxForm({

		/* set data type json */
		dataType: 'json',

		/* reset before submitting */
        beforeSubmit : function(arr, $form, options){
            if(arr[0].value != "")
            {
                status.fadeOut();
                bar.width('0%');
                percent.html('0%');
				return true;
            }
            else
            {
                status.html("Nebyl vybrán žádný soubor!").fadeIn();
                setTimeout(function(){status.fadeOut();}, 5000);
                return false;
            }
         },

		/* progress bar call back*/
		uploadProgress: function(event, position, total, percentComplete) {
			var pVel = percentComplete + '%';
			bar.width(pVel);
			percent.html(pVel);
		},

		/* complete call back */
		complete: function(data) {
			//preview.fadeOut(800);
            status.html(data.responseJSON.status).fadeIn();
            setTimeout(function(){status.fadeOut();}, 5000);
		}

	});
});