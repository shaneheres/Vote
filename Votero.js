window.onload = function() {
var Votero = function Votero() {
	// Submit a vote.
	this.vote = function(a, v) {$.post(mw.util.wikiScript(), {action:'ajax', rs:'wfVoteroClick', rsargs:[mw.config['values']['wgArticleId'], a, v]}).done(function(d) {
		console.log(d);
	});};
};

window.voteroRangeSubmit = function(id, v) {
	// Submit a vote.
	var a = id.split('_')[1];
	$.post(mw.util.wikiScript(), {action:'ajax', rs:'wfVoteroClick', rsargs:[mw.config['values']['wgArticleId'], a, v]}).done(function(d) {
		console.log(d);
	});
}

window.voteroRangeStep = function(id, value) {
	var parts = id.split('_');
	var textID = "#" + parts[0] + "_" + parts[1] + "_" + parts[2] + "_txt";
	var attribute = parts[1];
	var label = $("#" + parts[0] + "_" + parts[1] + "_" + parts[2]).data("label");
	label = label.replace('VV', number_with_commas(value)); // value with commas.
	label = label.replace('MCG', convert_microgram(value)); // value converted to micrograms.
	label = label.replace('MG', convert_miligram(value)); // value converted to miligram.
	label = label.replace('SS', (value > 1 || value <-1) ? "'s" : ''); // apostrophe s
	$(textID).text(label);
}

function number_with_commas(x) {
    return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

function convert_miligram(x) {
	if (x > 1000000) return (x / 1000000).toFixed(2) + " kg";
	if (x > 1000) return (x / 1000).toFixed(2) + " g";
	return x + " mg";
}

function convert_microgram(x) {
	if (x > 1000000000) return (x / 1000000000).toFixed(2) + " kg";
	if (x > 1000000) return (x / 1000000).toFixed(2) + " g";
	if (x > 1000) return (x / 1000).toFixed(2) + " mg";
	return x + " mcg";
}

function format_number(number) {
	if (number >= 9940) return Math.round(number / 1000) + "k";
	if (number >= 1000) return (number / 1000).toFixed(1) + "k";
	return number;
}

// Activate tooltips.
$('[data-toggle="tooltip"]').tooltip();

$(function() {
	var votero = new Votero();
	var canVote = true;
	
	$('body').on('click', '.votero', function() {
		if (canVote) {
			canVote = false;
			setTimeout(function(){ canVote = true; }, 1000);
			
			var that = $(this);
			
			var attribute = that.parent().parent().attr('id').split('_')[1];
			var style = that.parent().parent().attr('id').split('_')[2];
			var isStars = (style == 'stars'); // Star display is unique in that it highlights all previous buttons.
			var vote = that.attr('id');
			
			// Place vote.
			votero.vote(attribute, vote);
			
			var list = [];
			var lastVote = -1;
			
			$('.votero').each(function() {
				var button = $(this);
				var buttonAttribute = button.parent().parent().attr('id').split('_')[1];
				
				if (buttonAttribute == attribute)
				{
					var buttonSelected = button.hasClass('votero-selected');
					var buttonVote = button.attr('id');
					
					if (buttonSelected && buttonVote > lastVote)
						lastVote = buttonVote;
				}
			});
			
			var deselecting = (vote == lastVote);
			
			$('.votero').each(function() {
				var button = $(this);
				var buttonAttribute = button.parent().parent().attr('id').split('_')[1];
				
				if (buttonAttribute == attribute)
				{
					list.push($(this));
					var buttonSelected = button.hasClass('votero-selected');
					var buttonVote = button.attr('id');
					
					// Deselect.
					if (deselecting || (isStars && buttonVote > vote)) {
						// Swap icon.
						button.removeClass(button.data('class')).addClass(button.data('backing')).removeClass('votero-selected');
						
						// Subtract from count.
						if (!isStars) {
							var count = parseInt(button.data('count')) - 1;
							button.data('count', count).text(' ' + format_number(count));
						}
						
					}
					
					// Selecting.
					if (!deselecting && (vote == buttonVote || (isStars && vote > buttonVote))) {
						// Swap icon.
						button.removeClass(button.data('backing')).addClass(button.data('class')).addClass("votero-selected");
						
						// Add to count.
						if (!isStars) {
							var count = parseInt(button.data('count')) + 1;
							button.data('count', count).text(' ' + format_number(count));
						}
					}
				}
			});
		}
	});
} );
}