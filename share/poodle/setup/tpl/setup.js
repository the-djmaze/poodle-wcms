/*
	@import "poodle"
*/

var $ = function(id){return document.getElementById(id)};
function progress(id, perc, query)
{
	perc = Math.min(100,perc);
	$("pc"+id).txt(perc+"%");
	$("pb"+id).value = perc;
	$("pbq"+id).txt(query);
}
