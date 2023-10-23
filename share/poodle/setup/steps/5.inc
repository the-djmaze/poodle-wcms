<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.
*/
if (!class_exists('Poodle',0)) { exit; }
$K = Poodle::getKernel();
if (!($K instanceof \Poodle\Kernels\Setup)) { exit; }

if (!is_file($K->cfg_file)) {
	\Poodle\HTTP\Headers::setLocation('?step='.($K->step-1), 303);
	$K->display_error('Configuration not finished');
}

$dbms = $K->_readonly_data['dbms'];
$K->SQL = new \Poodle\SQL($dbms['adapter'], $dbms['master'], $dbms['tbl_prefix']);

$errors = array();
$user = array (
	'nickname'  => '',
	'email'     => '',
	'givenname' => '',
	'surname'   => '',
	'language'  => $K->OUT->L10N->lng,
	'timezone'  => date_default_timezone_get()
);

$K->OUT->L10N->load('poodle_identity');

if (!empty($_POST['user'])) {
	$user['nickname']  = $_POST->text('user', 'nickname');
	$user['email']     = $_POST->text('user', 'email');
	$user['givenname'] = $_POST->text('user', 'givenname');
	$user['surname']   = $_POST->text('user', 'surname');
	$user['language']  = $_POST->text('user', 'language');
	$user['timezone']  = $_POST->text('user', 'timezone');
	$passphrase = $_POST->raw('user', 'passphrase');

	$K->CFG = \Poodle\Config::load();
	\Poodle\Identity\Validate::nickname($user['nickname'], $errors);
	\Poodle\Identity\Validate::passphrase($passphrase, $user['nickname'], $errors, $_POST['passphrase_confirm']);
	\Poodle\Identity\Validate::email($user['email'], $errors);
	if (!$errors) {
		$user = new \Poodle\Identity($user);
		$user->save();
		$user->updateAuth(1 /*DB*/, $user['nickname'], $passphrase);
		$user->addToGroup(3);
		$user->addToGroup(4);
?>
		<form action="<?php echo dirname($_SERVER['SCRIPT_NAME']); ?>" method="get">
		Account created! Click next to go to the admin system or <a href="?step=5">create another admin</a>.
<?php
		return;
	}
}

$K->OUT->user_lng = array();
foreach ($K->OUT->L10N->getActiveList() as $v) {
	$v['selected'] = ($v['value'] == $user['language']);
	$K->OUT->user_lng[] = $v;
}

$K->OUT->fatal_errors = $errors;
$K->OUT->account      = $user;

$K->OUT->display('poodle/setup/account');
