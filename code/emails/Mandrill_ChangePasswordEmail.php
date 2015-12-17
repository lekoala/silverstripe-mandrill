<?php

/**
 * Replace the default Member_ChangePasswordEmail
 *
 * @author LeKoala <thomas@lekoala.be>
 */
class Mandrill_ChangePasswordEmail extends MandrillEmail
{
    protected $from    = '';   // setting a blank from address uses the site's default administrator email
    protected $subject = '';

    public function __construct()
    {
        parent::__construct();

        $this->subject = _t('Member.SUBJECTPASSWORDCHANGED',
            "Your password has been changed", 'Email subject');

        $template = $this->config()->change_password_template;
        if (!$template) {
            $template = 'ChangePasswordEmail';
        }
        $viewer     = new SSViewer($template);
        $this->body = $viewer;
    }
}
