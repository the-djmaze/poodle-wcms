<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2008. All rights reserved.
*/

namespace Poodle\Mail;

class Admin extends \Poodle\Resource\Admin
{
	public
		$title = 'Mail',
		$allowed_methods = array('GET','HEAD','POST');

	public function POST()
	{
		$K = \Poodle::getKernel();

		$K->CFG->set('mail', 'encoding', $_POST->text('mail', 'encoding'));

		$handler = $_POST->text('mail', 'sender');
		if (isset($_POST['test'])) {
			$class = '\\Poodle\\Mail\\Send\\'.$handler;
			if (!class_exists($class)) {
				throw new \Exception('Unknown mail sender');
			}
			$sender = $_POST->email('mail', 'return_path');
			$MAIL = new $class(static::getHandlerConfig($handler));
			$MAIL->subject = 'Test mail settings';
			$MAIL->setFrom($_POST['from_address'], $_POST['from_name']);
			if ($sender) {
				$MAIL->setSender($sender, $_POST['from_name']);
			}
			$MAIL->addTo($_POST['to_address'], $_POST['to_name']);
			$MAIL->addReplyTo($_POST['from_address'], $_POST['from_name']);
			$MAIL->body = $_POST['message_body'];
			try {
				if ($MAIL->send()) {
					\Poodle\Notify::success('Test mail send');
//					\Poodle::closeRequest('Test mail send', 200, \Poodle\URI::admin());
				}
			} catch (Exception $e) {
				\Poodle\Notify::error($e->getMessage()."\n".$e->getResponse());
			} catch (\Throwable $e) {
				\Poodle\Notify::error($e->getMessage());
			}
			return $this->GET();
		} else {
			$K->CFG->set('mail', 'sender', $handler);
			$K->CFG->set('mail', 'encoding', $_POST->text('mail', 'encoding'));
			$K->CFG->set('mail', 'from', $_POST->email('mail', 'from'));
			$K->CFG->set('mail', 'return_path', $_POST->email('mail', 'return_path'));
			foreach ($_POST['sender'] as $handler => $cfg) {
				$v = static::getHandlerConfig($handler);
				if (is_string($v)) {
					$K->CFG->set('mail', $handler, $v);
				}
			}
		}

		\Poodle::closeRequest($K->L10N['Configuration saved'], 200, $_SERVER['REQUEST_URI']);
	}

	public function GET()
	{
		$K = \Poodle::getKernel();
		$OUT = $K->OUT;
		if (!isset($K->CFG->mail->sender)) {
			$K->CFG->set('mail','sender','php');
		}
		$CFG = $K->CFG->mail;

		$OUT->mail_senders = array();
		foreach (glob(__DIR__ . '/send/*.php') as $handler) {
			$handler = basename($handler,'.php');
			$class = '\\Poodle\\Mail\\Send\\'.$handler;
			if (class_exists($class)) {
				$OUT->mail_senders[] = array(
					'name' => $handler,
					'config' => $class::getConfigOptions(static::getHandlerConfig($handler)),
				);
			}
		}

		$host = $CFG->from;
		if ($p = strrpos($host, '@')) {
			$host = substr($host, $p+1);
			$OUT->spf = preg_replace('/\s+/', "\n", \Poodle\DNS::getSPF($host));
			$OUT->dkim = preg_replace('/;\s*/', ";\n", \Poodle\DNS::getDKIM($host));
			$OUT->dmarc = preg_replace('/;\s*/', ";\n", \Poodle\DNS::getDMARC($host));
		} else {
			$OUT->spf = '';
			$OUT->dkim = '';
			$OUT->dmarc = '';
		}

		$OUT->head
			->addCSS('poodle_tabs')
			->addScript('poodle_tabs');
		$OUT->display('poodle/mail/settings');
	}

	protected static function getHandlerConfig($handler)
	{
		$class = '\\Poodle\\Mail\\Send\\'.$handler;
		if (class_exists($class)) {
			if ('POST' === $_SERVER['REQUEST_METHOD']) {
				if (isset($_POST['sender'][$handler])) {
					return $class::getConfigAsString($_POST['sender'][$handler]);
				}
			} else {
				$CFG = \Poodle::getKernel()->CFG->mail;
				if (isset($CFG->$handler)) {
					return $CFG->$handler;
				}
			}
		}
		return null;
	}

}
