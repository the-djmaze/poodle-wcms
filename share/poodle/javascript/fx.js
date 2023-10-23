/*
	@import "poodle"
*/

function Poodle_FX(element)
{
	var timer, delay, calls, events=[];

	function run()
	{
		for (var i=0, o=events, css={}, c=false; i<o.length; ++i)
		{
			if (0 == (delay*calls) % o[i].delay)
			{
				css[o[i].property] = o[i].value+o[i].unit;
				c |= o[i].to > o[i].value;
				o[i].value = Math.min(o[i].to, o[i].value+o[i].step);
			} else c = true;
		}
		element.setCSS(css);
		++calls;
		if (!c) { clearInterval(timer); timer=null; }
	}

	this.add = function(property, from, to, step, delay, unit)
	{
		events[events.length] = {
			property:property,
			from:from,
			to:to,
			step:step,
			delay:delay,
			unit:unit||""
		};
		return this;
	};

	this.start = function()
	{
		var r, a, o=events, i=o.length;
		while (i--) o[i].value = o[i].from;
		// Greatest Common Divisor
		for (i=o.length-1, delay=o[i].delay; i;) for (a = o[--i].delay; r = a % delay; a = delay, delay = r);
		calls = 0;
		timer = setInterval(run, delay);
	};

}
