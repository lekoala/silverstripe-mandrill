<?php
// Autosetup if key is defined in _ss_environment or yml
if (defined('MANDRILL_API_KEY') && MANDRILL_API_KEY !== '' || Config::inst()->get('MandrillMailer', 'mandrill_api_key')) {
	MandrillMailer::setAsMailer();
}