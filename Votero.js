window.onload = function() {
var Votero = function Votero() {
	// Submit a vote.
	this.vote = function(p, a, v) {$.post(mw.util.wikiScript(), {action:'ajax', rs:'wfVoteroClick', rsargs:[p, a, v]}).done(function(d) {
		console.log(d);
	});};
};

window.voteroRangeSubmit = function(id, v) {
	console.log(id, v);
	// Submit a vote.
	var p = parseInt(id.split('_')[1]);
	var a = parseInt(id.split('_')[2]);
	v = parseInt(v);
	$.post(mw.util.wikiScript(), {action:'ajax', rs:'wfVoteroClick', rsargs:[p, a, v]}).done(function(d) {
		console.log(d);
	});
	$("#" + id + "_delete").show();
}

window.voteroRangeStep = function(id, value) {
	var label = $("#" + id).data("label");
	var max = $("#" + id).attr("max");
	// Update label.
	$("#" + id + "_txt").text(" " + formatSlider(label, value, max));
	// Show delete option.
	$("#" + id + "_delete").show();
	
}
/*
function number_with_commas(x) {
    return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}
*/
function formatSlider(sliderLabel, x, max) {
	var vars = get_between(sliderLabel, '[', ']').split(',');
	var original = x;
	
	if (vars.length == 1) {
		var parts = vars[0].split('=');
		var label = parts.length == 2 ? parts[1] : parts[0];
		return replace_between(sliderLabel, x + label);
	}
	
	for (var i = vars.length-1; i >= 0; i -= 1) {
		// Look ahead...
		var parts = vars[i].split('=');
		var v = parts[0];
		var label = parts[1];
		
		// If smaller than that, use current.
		if (x >= parseInt(v) || i == 0) {
			x = (parseFloat(x) / parseFloat(v));
			
			if (i != 0) {
				if (Math.round(x) == x.toFixed(1))
					x = Math.round(x);
				else
					x = x.toFixed(1);
			}
			
			if (x != 1) {
				label += "'s";
			}
			
			if (original == max) {
				x = "Over " + x;
			}
			
			return replace_between(sliderLabel, x + label);
		}
	}
}

function get_between(string, start, end) {
	return string.substring(string.lastIndexOf(start)+1,string.lastIndexOf(end));
}

function replace_between(string, newstring) {
	return string.replace(/\[(.+?)\]/g, newstring);
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
	
	$('body').on('click', '.votero-delete', function() {
		if (canVote) {
			canVote = false;
			setTimeout(function(){ canVote = true; }, 1000);
			
			var that = $(this);
			var parts = that.attr('id').split('_');
			var page = parts[1];
			var attribute = parts[2];
			
			// Place vote.
			votero.vote(page, attribute, -1);
			
			parts.pop();
			var id = parts.join('_');
			// input: range
			$('#' + id + '_txt').text('');
			// input: radio
			$("input[name='" + id + "']").prop('checked', false);
			that.hide();
		}
	});
	
	$('body').on('click', '.votero', function() {
		if (canVote) {
			canVote = false;
			setTimeout(function(){ canVote = true; }, 1000);
			
			var that = $(this);
			var parts = that.parent().parent().attr('id').split('_');
			var page = parts[1];
			var attribute = parts[2];
			var style = parts[3];
			var isStars = (style == 'stars'); // Star display is unique in that it highlights all previous buttons.
			var vote = that.attr('id');
			
			// Place vote.
			votero.vote(page, attribute, vote);
			
			var list = [];
			var lastVote = -1;
			
			$('.votero').each(function() {
				var button = $(this);
				var buttonPage = button.parent().parent().attr('id').split('_')[1];
				var buttonAttribute = button.parent().parent().attr('id').split('_')[2];
				
				if (buttonPage == page && buttonAttribute == attribute)
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
				var buttonPage = button.parent().parent().attr('id').split('_')[1];
				var buttonAttribute = button.parent().parent().attr('id').split('_')[2];
				
				if (buttonPage == page && buttonAttribute == attribute)
				{
					list.push($(this));
					var buttonSelected = button.hasClass('votero-selected');
					var buttonVote = button.attr('id');
					
					// Deselect.
					if (buttonSelected && (deselecting || (isStars && buttonVote > vote) || (!isStars && buttonVote != vote))) {
						// Swap icon.
						button.removeClass(button.data('class')).addClass(button.data('backing')).removeClass('votero-selected');
						
						// Subtract from count.
						if (!isStars) {
							var count = parseInt(button.data('count')) - 1;
							button.data('count', count).find('span').text(format_number(count));
						}
					}
					
					// Selecting.
					if (!deselecting && (vote == buttonVote || (isStars && vote > buttonVote))) {
						// Swap icon.
						button.removeClass(button.data('backing')).addClass(button.data('class')).addClass("votero-selected");
						
						// Add to count.
						if (!isStars) {
							var count = parseInt(button.data('count')) + 1;
							button.data('count', count).find('span').text(format_number(count));
						}
					}
				}
			});
		}
	});
} );
}