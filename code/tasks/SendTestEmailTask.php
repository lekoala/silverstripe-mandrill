<?php

/**
 * A simple task to test if your emails are sending properly
 * @author lekoala
 */
class SendTestEmailTask extends BuildTask
{
    protected $description = 'Send a sample email to admin or to ?email=';

    function run($request)
    {
        $config = SiteConfig::current_site_config();
        $email  = new MandrillEmail();

        $member = Member::currentUser();
        $to     = $request->getVar('email');

        $email->setSampleContent();

        if (!$to) {
            $email->setToMember($member);
        } else {
            $email->setTo($to);
        }

        $email->setSubject('Sample email from '.$config->Title);

        echo 'Sending to '.htmlentities($email->To()).'<br/>';
        echo 'Using theme : '.$email->getTheme().'<br/>';

        $res = $email->send();

        foreach ($res as $v) {
            if (!is_string($v)) {
                echo "<pre>";
                var_dump($v);
                echo "</pre>";
            } else {
                echo $v.'<br/>';
            }
        }
    }
}