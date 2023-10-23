/*
	@import "poodle"
*/

Poodle.onDOMReady(()=>{
	var st = Poodle.$('identity_type'), iir = Poodle.$('identity_inactive_reason');
	if (st && iir) {
		st.on('change',function(){
			(1 > this.value) ? iir.show() : iir.hide();
		});
	}
});
