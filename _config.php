<?php
if (defined('MANDRILL_API_KEY') && MANDRILL_API_KEY !== '') {
	$mandrillMailer = new MandrillMailer(MANDRILL_API_KEY);
	Email::set_mailer($mandrillMailer);
}