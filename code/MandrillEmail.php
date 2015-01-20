<?php

/**
 * An improved and more pleasant base Email class to use on your project
 *
 * - URL safe rewriting
 * - Base template
 * - Send email according to member locale
 * - Auto set template based on ClassName for subclasses
 * - Basic theme options
 * - Check for subject
 * - Send to member
 *
 * @author lekoala
 */
class MandrillEmail extends Email
{
    /**
     * @var ViewableData
     */
    protected $template_data;
    protected $ss_template        = "emails/BasicEmail";
    protected $locale;
    protected $callout;
    protected $image;
    protected $header_color       = '#333333';
    protected $header_font_color  = '#ffffff';
    protected $footer_color       = '#ebebeb';
    protected $footer_font_color  = '#333333';
    protected $panel_color        = '#ECF8FF';
    protected $panel_border_color = '#b9e5ff';
    protected $panel_font_color   = '#000000';

    public function __construct($from = null, $to = null, $subject = null,
                                $body = null, $bounceHandlerURL = null,
                                $cc = null, $bcc = null)
    {
        parent::__construct($from, $to, $subject, $body, $bounceHandlerURL, $cc,
            $bcc);

        // Allow subclass template
        $class = get_called_class();
        if ($class != 'MandrillEmail') {
            $this->ss_template = array('emails/'.$class, $this->ss_template);
        }

        // Allow theming
        if ($theme = self::config()->theme) {
            $this->setTheme($theme);
        }

        // Set base data
        $this->populateTemplate(array(
            'CurrentMember' => Member::currentUser(),
            'SiteConfig' => SiteConfig::current_site_config(),
            'Controller' => Controller::curr(),
            'Image' => $this->image,
            'Callout' => $this->callout,
            'HeaderColor' => $this->header_color,
            'HeaderFontColor' => $this->header_font_color,
            'FooterColor' => $this->footer_color,
            'FooterFontColor' => $this->footer_font_color,
            'PanelColor' => $this->panel_color,
            'PanelBorderColor' => $this->panel_border_color,
            'PanelFontColor' => $this->panel_font_color,
        ));
    }



    public function send($messageID = null)
    {
        // Check for Subject
        if (!$this->subject) {
            throw new Exception('You must set a subject');
        }

        $this->from = MandrillMailer::resolveDefaultFromEmail($this->from);
        if(!$this->from) {
            throw new Exception('You must set a sender');
        }
        $this->to = MandrillMailer::resolveDefaultToEmail($this->to);
        if(!$this->to) {
            throw new Exception('You must set a recipient');
        }

        // Set language according to member
        $restore_locale = null;
        if ($this->locale) {
            $restore_locale = i18n::get_locale();
            i18n::set_locale($this->locale);
        } else if ($this->to) {
            $email  = $this->to;
            $member = false;
            if (is_array($this->to) && count($this->to) == 1) {
                $email = $this->to[0]['email'];
            }
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $member = Member::get()->filter(array('Email' => $email))->first();
            }
            if ($member) {
                $restore_locale = i18n::get_locale();
                i18n::set_locale($member->Locale);
            }
        }

        $res = parent::send($messageID);

        if ($restore_locale) {
            i18n::set_locale($restore_locale);
        }
        return $res;
    }

    /**
     * Load all the template variables into the internal variables, including
     * the template into body.	Called before send() or debugSend()
     * $isPlain=true will cause the template to be ignored, otherwise the GenericEmail template will be used
     * and it won't be plain email :)
     *
     * This function is updated to rewrite urls in a safely manner and inline css
     */
    protected function parseVariables($isPlain = false)
    {
        $origState = Config::inst()->get('SSViewer', 'source_file_comments');
        Config::inst()->update('SSViewer', 'source_file_comments', false);

        if (!$this->parseVariables_done) {
            $this->parseVariables_done = true;

            // Parse $ variables in the base parameters
            $data = $this->templateData();

            // Process a .SS template file
            $fullBody = $this->body;
            if ($this->ss_template && !$isPlain) {
                // Requery data so that updated versions of To, From, Subject, etc are included
                $data = $this->templateData();

                $template = new SSViewer($this->ss_template);

                if ($template->exists()) {
                    $fullBody = $template->process($data);
                }
            }

            // Rewrite relative URLs
            $this->body = self::rewriteURLs($fullBody);
        }
        Config::inst()->update('SSViewer', 'source_file_comments', $origState);

        return $this;
    }

    public function setLocale($val)
    {
        $this->locale = $val;
    }

    public function getLocale()
    {
        return $this->locale;
    }

    public function setCallout($val)
    {
        $this->callout = $val;
    }

    public function getCallout()
    {
        return $this->callout;
    }

    public function setImage($image, $size = 580)
    {
        if (is_int($image)) {
            $image = Image::get()->byID($image);
        }
        $this->image = $image->SetWidth($size)->Link();
    }

    public function getImage()
    {
        return $this->image;
    }

    public function setTheme($vars)
    {
        foreach ($vars as $k => $v) {
            if ($v) {
                $this->$k = $v;
            }
        }
    }

    public function getTheme()
    {
        return array(
            'header_color' => $this->header_color,
            'header_font_color' => $this->header_font_color,
            'footer_color' => $this->footer_color,
            'footer_font_color' => $this->footer_font_color,
            'panel_color' => $this->panel_color,
            'panel_border_color' => $this->panel_border_color,
            'panel_font_color' => $this->panel_font_color
        );
    }

    public function setSampleContent()
    {
        $member = Member::currentUserID() ? Member::currentUser()->getTitle() : 'Anonymous Member';
        $val    = '<h1>Hi, '.$member.'</h1>
                            <p class="lead">Phasellus dictum sapien a neque luctus cursus. Pellentesque sem dolor, fringilla et pharetra vitae.</p>
                            <p>Phasellus dictum sapien a neque luctus cursus. Pellentesque sem dolor, fringilla et pharetra vitae. consequat vel lacus. Sed iaculis pulvinar ligula, ornare fringilla ante viverra et. In hac habitasse platea dictumst. Donec vel orci mi, eu congue justo. Integer eget odio est, eget malesuada lorem. Aenean sed tellus dui, vitae viverra risus. Nullam massa sapien, pulvinar eleifend fringilla id, convallis eget nisi. Mauris a sagittis dui. Pellentesque non lacinia mi. Fusce sit amet libero sit amet erat venenatis sollicitudin vitae vel eros. Cras nunc sapien, interdum sit amet porttitor ut, congue quis urna.</p>
                       ';
        $this->setBody($val);

        $val = 'Phasellus dictum sapien a neque luctus cursus. Pellentesque sem dolor, fringilla et pharetra vitae. <a href="#">Click it! »</a>';
        $this->setCallout($val);

        $this->setImage(Image::get()->first());
    }

    public function setToMember(Member $member)
    {
        $this->locale = $member->Locale;
        return $this->setTo($member->FirstName.' '.$member->Surname.' <'.$member->Email.'>');
    }

    /**
     * Bug safe absolute url
     *
     * @param string $url
     * @param bool $relativeToSiteBase
     * @return string
     */
    static protected function safeAbsoluteURL($url, $relativeToSiteBase = false)
    {
        if (empty($url)) {
            return Director::baseURL();
        }
        return Director::absoluteURL($url, $relativeToSiteBase);
    }

    /**
     * Turn all relative URLs in the content to absolute URLs
     */
    public static function rewriteURLs($html)
    {
        if (isset($_SERVER['REQUEST_URI'])) {
            $html = str_replace('$CurrentPageURL', $_SERVER['REQUEST_URI'],
                $html);
        }
        return HTTP::urlRewriter($html,
                function($url) {
                //no need to rewrite, if uri has a protocol (determined here by existence of reserved URI character ":")
                if (preg_match('/^\w+:/', $url)) {
                    return $url;
                }
                return self::safeAbsoluteURL($url, true);
            });
    }
}