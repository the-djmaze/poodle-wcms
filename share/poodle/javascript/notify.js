/**
	@import "poodle"
 */

(K=>{

var container;

function show(message, type)
{
	container.$A("div",{"class":type, textContent:message});
}

K.notify = {
	message: show,
	info:    s => show(s, "info"),
	success: s => show(s, "success"),
	warning: s => show(s, "warning"),
	error:   s => show(s, "error")
};

K.onDOMReady(()=>{
	// Remove notification on click
	container = K.$("poodle_notifications");
	if (!container) {
		container = K.$B().$A("div",{id:"poodle_notifications"});
	}
	container.on("click", e => {
		if (e.target.parent() == container) { e.target.remove(); }
	});
});

})(Poodle);
