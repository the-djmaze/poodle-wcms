<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.
*/
if (!class_exists('Poodle',0)) { exit; }
$K = Poodle::getKernel();
if (!($K instanceof \Poodle\Kernels\Setup)) { exit; }

$this->OUT->display('poodle/setup/LICENSE');
?>
	<form action="" method="get">
		<input type="hidden" name="step" value="2">
		<div id="stepbuttons">
			<button type="submit"><?php echo $this->OUT->L10N->get('I_Agree');?></button>
		</div>
	</form>
