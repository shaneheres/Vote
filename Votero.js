var Votero = function Votero() {
	var ready = false;
	
	// Get initial values.
	this.init = function(a) {$.post(mw.util.wikiScript(), {action:'ajax', rs:'wfVoteroGetData', rsargs: [mw.config['values']['wgArticleId'], a.join()]}).done(function(d) {
		var d_attrib = d.split("|");
		
		for (var i = 0; i < d_attrib.length; i++) {
			var attribute = a[i];
			var split = d_attrib[i].split(".");
			var myvote = split[0];
			var counts = split[1].split("_");
			var isStars = $("#votero_" + attribute + "_0_span").hasClass("votero-stars");
			
			for(var vote = 0; vote < counts.length; vote++) {
				var count = counts[vote];
				
				// Update text.
				if (!isStars) {
					$("#votero_" + attribute + "_" + vote + "_txt")
						.data('voteCount', count)
						.text(format_number(count));
				}
				
				// Select parent if this is what user voted for.
				if (myvote == vote || (isStars && vote < myvote))
				{
					$("#votero_" + attribute + "_" + vote + "_span").addClass('votero-selected');
					
					var button = $("#votero_" + attribute + "_" + vote + "_btn");
					button.removeClass(button.data('backing')).addClass(button.data('class'));
				}
			}
		}
		Votero.ready = true;
		console.log("Ready");
	});};
	
	// Submit a vote.
	this.vote = function(a, v) {$.post(mw.util.wikiScript(), {action:'ajax', rs:'wfVoteroClick', rsargs:[mw.config['values']['wgArticleId'], a, v]}).done(function(d) {
		//console.log(d);
	});};
};

function format_number(number) {
	if (number >= 9940) return Math.round(number / 1000) + "k";
	if (number >= 1000) return (number / 1000).toFixed(1) + "k";
	return number;
}

$(function() {
	var votero = new Votero();
	var canVote = true;
	
	var list = [];
	$('.votero-button').each(function() { if ($(this).data('vote') == 0) list.push($(this).data('attribute')); });
	votero.init(list);
	
	$('body').on('click', '.votero-button', function() {
		if (canVote && Votero.ready) {
			canVote = false;
			setTimeout(function(){ canVote = true; }, 1500);
			
			var that = $(this);
			var attribute = that.data('attribute');
			var vote = that.data('vote');
			votero.vote(attribute, vote);
			
			var list = [];
			var lastVote = -1;
			
			// Get related buttons.
			$('.votero-button').each(function() {
				var button = $(this);
				if (button.data('attribute') == attribute)
				{
					list.push($(this));
					
					// Get last vote. (Whichever is selected. If it's a stars display, multiple buttons will be selected so grab the last most one.)
					if (button.parent().hasClass("votero-selected")) {
						
						button.parent().removeClass("votero-selected");
						button.removeClass(button.data('class')).addClass(button.data('backing'));
						
						if (!isStars) {
							var buttonText = $("#votero_" + button.data('attribute') + "_" + button.data('vote') + "_txt");
							var count = parseInt(buttonText.data('voteCount')) - 1;
							buttonText.data('voteCount', count).text(format_number(count));
						}
						
						if (button.data('vote') > lastVote)
							lastVote = button.data('vote');
					} 
				}
			});
			
			var deselecting = (vote == lastVote); // Removing vote.
			var isStars = that.parent().hasClass("votero-stars"); // Star display is unique in that it selects all previous buttons.
			
			for (var i = 0; i < list.length; i++) {
				var button = list[i];
				
				if (!deselecting && (vote == i || (isStars && i < vote))) {
					// Select button and animate.
					button.addClass("votero-anim-selected").removeClass(button.data('backing')).addClass(button.data('class')).parent().addClass("votero-selected");
					// Remove animation class, so it can be animated again if clicked again.
					setTimeout(function(attribute, i){ $("#votero_"+attribute+"_"+i+"_btn").removeClass("votero-anim-selected"); }, 200, attribute, i);
					
					if (!isStars) {
						var buttonText = $("#votero_" + attribute + "_" + vote + "_txt");
						var count = parseInt(buttonText.data('voteCount')) + 1;
						buttonText.data('voteCount', count).text(format_number(count));
					}
				}
			}
		}
	});
} );