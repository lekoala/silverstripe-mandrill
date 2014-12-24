<?php
// Autosetup if key is defined in _ss_environment
if (defined('MANDRILL_API_KEY') && MANDRILL_API_KEY !== '') {
	MandrillMailer::setAsMailer(MANDRILL_API_KEY);
}