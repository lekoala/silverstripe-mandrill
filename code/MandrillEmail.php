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
    protected $ss_template = "emails/BasicEmail";
    protected $locale;
    protected $callout;
    protected $image;

    /**
     *
     * @var Member
     */
    protected $to_member;
    protected $theme = null;
    protected $header_color;
    protected $header_font_color;
    protected $footer_color;
    protected $footer_font_color;
    protected $panel_color;
    protected $panel_border_color;
    protected $panel_font_color;

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
        $config = SiteConfig::current_site_config();
        if ($config->EmailTheme) {
            $this->setTheme($config->EmailTheme);
        } else if ($theme = self::config()->default_theme) {
            $this->setTheme($theme);
        }

        // Set base data
        $this->populateTemplate(array(
            'CurrentMember' => Member::currentUser(),
            'SiteConfig' => $config,
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

    /**
     * Send an email with HTML content.
     *
     * @see sendPlain() for sending plaintext emails only.
     * @uses Mailer->sendHTML()
     *
     * @param string $messageID Optional message ID so the message can be identified in bounces etc.
     * @return bool Success of the sending operation from an MTA perspective.
     * Doesn't actually give any indication if the mail has been delivered to the recipient properly)
     */
    public function send($messageID = null)
    {
        // Check for Subject
        if (!$this->subject) {
            throw new Exception('You must set a subject');
        }

        $this->from = MandrillMailer::resolveDefaultFromEmail($this->from);
        if (!$this->from) {
            throw new Exception('You must set a sender');
        }
        $this->to = MandrillMailer::resolveDefaultToEmail($this->to);
        if (!$this->to) {
            throw new Exception('You must set a recipient');
        }

        // Set language to use for the email
        $restore_locale = null;
        if ($this->locale) {
            $restore_locale = i18n::get_locale();
            i18n::set_locale($this->locale);
        }
        if ($this->to_member) {
            // If no locale is defined, use Member locale
            if ($this->to_member->Locale && !$this->locale) {
                $restore_locale = i18n::get_locale();
                i18n::set_locale($this->to_member->Locale);
            }
            // Include name in to as standard rfc
            $this->to = $this->to_member->FirstName.' '.$this->to_member->Surname.' <'.$this->to_member->Email.'>';
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

    /**
     *  Set locale to set before email is sent
     *
     * @param string $val
     */
    public function setLocale($val)
    {
        $this->locale = $val;
    }

    /**
     * Get locale set before email is sent
     *
     * @return string
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * Set callout - displayed in a box in BasicEmail.ss
     *
     * @param string $val
     */
    public function setCallout($val)
    {
        $this->callout = $val;
    }

    /**
     * Get callout
     *
     * @return string
     */
    public function getCallout()
    {
        return $this->callout;
    }

    /**
     * Set image in the body of the message - see BasicEmail.ss
     * 
     * @param Image|int $image Image or ImageID
     * @param int $size
     */
    public function setImage($image, $size = 580)
    {
        if (is_int($image)) {
            $image = Image::get()->byID($image);
        }
        $this->image = $image->SetWidth($size)->Link();
    }

    /**
     * The url of the image
     *
     * @return string
     */
    public function getImage()
    {
        return $this->image;
    }

    /**
     * Get available themes
     *
     * @return array
     */
    public static function getAvailableThemes()
    {
        return array_keys(self::config()->get('themes'));
    }

    /**
     * 
     * @return string
     */
    public function getTheme()
    {
        return $this->theme;
    }

    /**
     *
     * @param type $val
     * @return type
     * @throws Exception
     */
    public function setTheme($val)
    {
        $availableThemes = self::getAvailableThemes();
        if (!in_array($val, $availableThemes)) {
            throw new Exception("Invalid theme, must be one of ".implode(',',
                $availableThemes));
        }
        $conf        = self::config()->themes[$val];
        $this->theme = $val;
        return $this->setThemeOptions($conf);
    }

    /**
     * Get current theme options
     *
     * @return array
     */
    public function getThemeOptions()
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

    /**
     * Set theme variables - see getTheme for available options
     * 
     * @param array $vars
     */
    public function setThemeOptions($vars)
    {
        foreach ($vars as $k => $v) {
            if ($v) {
                $this->$k = $v;
            }
        }
    }

    /**
     * Set some sample content for demo purposes
     */
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

        $image = Image::get()->first();
        if ($image->ID) {
            $this->setImage($image);
        }
    }

    /**
     * Get recipient as member
     * 
     * @return Member
     */
    public function getToMember()
    {
        if (!$this->to_member && $this->to) {
            $email           = MandrillMailer::get_email_from_rfc_email($this->to);
            $this->to_member = Member::get()->filter(array('Email' => $email))->first();
        }
        return $this->to_member;
    }

    /**
     *
     * @param string $val
     * @return Email
     */
    public function setTo($val)
    {
        if ($this->to_member && $val !== $this->to_member->Email) {
            $this->to_member = false;
        }
        return parent::setTo($val);
    }

    /**
     * Set a member as a recipient
     *
     * @param Member $member
     * @return MandrillEmail
     */
    public function setToMember(Member $member)
    {
        $this->locale    = $member->Locale;
        $this->to_member = $member;
        return $this->setTo($member->Email);
    }

    /**
     * Get custom api params for this message.
     * 
     * @return array
     */
    public function getApiParams()
    {
        if (isset($this->customHeaders['X-MandrillMailer'])) {
            return $this->customHeaders['X-MandrillMailer'];
        }
        return array();
    }

    /**
     * Set api parameters for this message.
     *
     * @param array $params
     */
    public function setApiParams(array $params)
    {
        $this->customHeaders['X-MandrillMailer'] = $params;
    }

    /**
     * Set api parameter
     *
     * @param string $key
     * @param mixed $value
     */
    public function setApiParam($key, $value)
    {
        $params       = $this->getApiParams();
        $params[$key] = $value;
        $this->setApiParams($params);
    }

    /**
     * Set google analytics campaign
     *
     * @param string $value
     */
    public function setGoogleAnalyticsCampaign($value)
    {
        $this->setApiParam('google_analytics_campaign', $value);
    }

    /**
     * Set metadata
     *
     * @param string $key
     * @param string $value
     */
    public function setMetadata($key, $value)
    {
        $params = $this->getApiParams();
        if (!isset($params['metadata'])) {
            $params['metadata'] = array();
        }
        $params['metadata'][$key] = $value;
        $this->setApiParams($params);
    }

    /**
     * Set metadatas
     *
     * @param array $values
     */
    public function setMetadatas($values)
    {
        $params             = $this->getApiParams();
        $params['metadata'] = $values;
        $this->setApiParams($params);
    }

    /**
     * Set recipient metadatas
     *
     * @param string $recipient Email of the recipient
     * @param array $values
     */
    public function setRecipientMetadatas($recipient, $values)
    {
        $params = $this->getApiParams();
        if (!isset($params['recipient_metadata'])) {
            $params['recipient_metadata'] = array();
        }
        // Look for recipient
        $found = false;
        foreach ($params['recipient_metadata'] as &$rcp) {
            if ($rcp['rcpt'] == $recipient) {
                $found         = true;
                $rcp['values'] = $values;
            }
        }
        if (!$found) {
            $params['recipient_metadata'][] = array(
                'rcpt' => $recipient,
                'values' => $values
            );
        }
        $this->setApiParams($params);
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