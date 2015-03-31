<?php
require_once "thirdparty/Mandrill.php";

/*
 * MandrillMailer for Silverstripe
 * 
 * Features
 * - Global tag support
 * - Multiple recipient support (use comma separated list, not array)
 * - File attachment support
 * 
 * @link https://mandrillapp.com/api/docs/messages.php.html#method-send
 * @package Mandrill
 * @author LeKoala <thomas@lekoala.be>
 */

class MandrillMailer extends Mailer
{
    /**
     * @var Mandrill
     */
    protected $mandrill;
    protected $last_error;
    protected $last_result;
    protected $last_is_error = false;
    protected static $instance;

    function __construct($apiKey)
    {
        $this->mandrill = new Mandrill($apiKey);
        self::$instance = $this;
    }

    /**
     * Get mandrill api
     * @return \Mandrill
     */
    public function getMandrill()
    {
        return $this->mandrill;
    }

    /**
     * Set mandrill api
     * @param Mandrill $mandrill
     */
    public function setMandrill(Mandrill $mandrill)
    {
        $this->mandrill = $mandrill;
    }

    /**
     * Helper method to initialize the mailer
     *
     * @param string $apiKey
     * @throws Exception
     */
    public static function setAsMailer($apiKey = null)
    {
        if ($apiKey === null) {
            if (defined('MANDRILL_API_KEY')) {
                $apiKey = MANDRILL_API_KEY;
            }
        }
        if (empty($apiKey)) {
            throw new Exception('Api key is empty');
        }
        $mandrillMailer = new MandrillMailer($apiKey);
        Email::set_mailer($mandrillMailer);
    }

    /**
     * @return \MandrillMailer
     */
    public static function getInstance()
    {
        return self::$instance;
    }

    /**
     * Get default params used by all outgoing emails
     * @return string
     */
    public static function getDefaultParams()
    {
        return Config::inst()->get(__CLASS__, 'default_params');
    }

    /**
     * Set default params
     * @param string $v
     * @return \MandrillMailer
     */
    public static function setDefaultParams(array $v)
    {
        Config::inst()->update(__CLASS__, 'default_params', $v);
        return $this;
    }

    /**
     * Get subaccount used by all outgoing emails
     * @return string
     */
    public static function getSubaccount()
    {
        return Config::inst()->get(__CLASS__, 'subaccount');
    }

    /**
     * Set subaccount
     * @param string $v
     * @return \MandrillMailer
     */
    public static function setSubaccount($v)
    {
        Config::inst()->update(__CLASS__, 'subaccount', $v);
        return $this;
    }

    /**
     * Get use_google_analytics
     * @return string
     */
    public static function getUseGoogleAnalytics()
    {
        return Config::inst()->get(__CLASS__, 'use_google_analytics');
    }

    /**
     * Set use_google_analytics
     * @param string $v
     * @return \MandrillMailer
     */
    public static function setUseGoogleAnalytics($v)
    {
        Config::inst()->update(__CLASS__, 'use_google_analytics', $v);
        return $this;
    }

    /**
     * Get global tags applied to all outgoing emails
     * @return array
     */
    public static function getGlobalTags()
    {
        $tags = Config::inst()->get(__CLASS__, 'global_tags');
        if (!is_array($tags)) {
            $tags = array($tags);
        }
        return $tags;
    }

    /**
     * Set global tags applied to all outgoing emails
     * @param array $arr
     * @return \MandrillMailer
     */
    public static function setGlobalTags($arr)
    {
        if (is_string($arr)) {
            $arr = array($arr);
        }
        return Config::inst()->update(__CLASS__, 'global_tags', $arr);
    }

    /**
     * Add file upload support to mandrill
     *
     * A typical Silverstripe attachement looks like this :
     *
     * array(
     * 'contents' => $data,
     * 'filename' => $filename,
     * 'mimetype' => $mimetype,
     * );
     *
     * @param string|array $file The name of the file or a silverstripe array
     * @param string $destFileName
     * @param string $disposition
     * @param string $extraHeaders
     * @return array
     */
    function encodeFileForEmail($file, $destFileName = false,
                                $disposition = NULL, $extraHeaders = "")
    {
        if (!$file) {
            user_error("encodeFileForEmail: not passed a filename and/or data",
                E_USER_WARNING);
            return;
        }

        if (is_string($file)) {
            $file = array('filename' => $file);
            $fh   = fopen($file['filename'], "rb");
            if ($fh) {
                $file['contents'] = "";
                while (!feof($fh))
                    $file['contents'] .= fread($fh, 10000);
                fclose($fh);
            }
        }

        if (!isset($file['contents'])) {
            throw new Exception('A file should have some contents');
        }

        $name = $destFileName;
        if (!$destFileName) {
            $name = basename($file['filename']);
        }

        $mimeType = !empty($file['mimetype']) ? $file['mimetype'] : HTTP::get_mime_type($file['filename']);
        if (!$mimeType) {
            $mimeType = "application/unknown";
        }

        $content = $file['contents'];
        $content = base64_encode($content);

        // Return completed packet
        return array(
            'type' => $mimeType,
            'name' => $name,
            'content' => $content
        );
    }

    /**
     * Mandrill takes care for us to send plain and/or html emails. See send method
     *
     * @param string|array $to
     * @param string $from
     * @param string $subject
     * @param string $plainContent
     * @param array $attachedFiles
     * @param array $customheaders
     * @return array|bool
     */
    function sendPlain($to, $from, $subject, $plainContent,
                       $attachedFiles = false, $customheaders = false)
    {
        return $this->send($to, $from, $subject, false, $attachedFiles,
                $customheaders, $plainContent, false);
    }

    /**
     * Mandrill takes care for us to send plain and/or html emails. See send method
     *
     * @param string|array $to
     * @param string $from
     * @param string $subject
     * @param string $plainContent
     * @param array $attachedFiles
     * @param array $customheaders
     * @return array|bool
     */
    function sendHTML($to, $from, $subject, $htmlContent,
                      $attachedFiles = false, $customheaders = false,
                      $plainContent = false, $inlineImages = false)
    {
        return $this->send($to, $from, $subject, $htmlContent, $attachedFiles,
                $customheaders, $plainContent, $inlineImages);
    }

    /**
     * Send the email through mandrill
     * 
     * @param string|array $to
     * @param string $from
     * @param string $subject
     * @param string $plainContent
     * @param array $attachedFiles
     * @param array $customheaders
     * @param bool $inlineImages
     * @return array|bool
     */
    protected function send($to, $from, $subject, $htmlContent,
                            $attachedFiles = false, $customheaders = false,
                            $plainContent = false, $inlineImages = false)
    {
        $original_to = $to;

        $grouped_recipients['to'] = $to;
        if (isset($customheaders['Cc'])) $grouped_recipients['cc'] = $customheaders['Cc'];
        if (isset($customheaders['Bcc'])) $grouped_recipients['bcc'] = $customheaders['Bcc'];

        $prepared_recipients = [];
        foreach($grouped_recipients as $recipient_type => $recipients) {
          if(!is_array($recipients)) $recipients = explode(',', $recipients);

          foreach ($recipients as $recipient) {
            if (strpos($recipient, '<') !== false) {
              $prepared_recipients[] = array(
                  'name' => self::get_displayname_from_rfc_email($recipient),
                  'email' => self::get_email_from_rfc_email($recipient),
                  'type' => $recipient_type
              );
            } else {
              $prepared_recipients[] = array(
                'email' => $recipient,
                'type' => $recipient_type
              );
            }
          }
        }

        // Create params to send to mandrill message api
        $default_params = array();
        if (self::getDefaultParams()) {
            $default_params = self::getDefaultParams();
        }
        $params = array_merge($default_params,
            array(
            "subject" => $subject,
            "from_email" => $from,
            "to" => $prepared_recipients
        ));

        // Inject additional params into message
        if (isset($customheaders['X-MandrillMailer'])) {
            $params = array_merge($params, $customheaders['X-MandrillMailer']);
            unset($customheaders['X-MandrillMailer']);
        }

        if (is_array($from)) {
            $params['from_email'] = $from['email'];
            $params['from_name']  = $from['name'];
        }

        if ($plainContent) {
            $params['text'] = $plainContent;
        }
        if ($htmlContent) {
            $params['html'] = $htmlContent;
        }

        // Attach tags to params
        if (self::getGlobalTags()) {
            if (!isset($params['tags'])) {
                $params['tags'] = array();
            }
            $params['tags'] = array_merge($params['tags'], self::getGlobalTags());
        }

        // Attach subaccount to params
        if (self::getSubaccount()) {
            $params['subaccount'] = self::getSubaccount();
        }

        $bcc_email = Config::inst()->get('Email', 'bcc_all_emails_to');
        if ($bcc_email) {
            if (is_string($bcc_email)) {
                $params['bcc_address'] = $bcc_email;
            }
        }

        // Google analytics domains
        if (self::getUseGoogleAnalytics()) {
            if (!isset($params['google_analytics_domains'])) {
                // Compute host
                $host = str_replace(Director::protocol(), '',
                    Director::protocolAndHost());

                // Define in params
                $params['google_analytics_domains'] = array(
                    $host
                );
            }
        }

        // Handle files attachments
        if ($attachedFiles) {
            $attachments = array();

            // Include any specified attachments as additional parts
            foreach ($attachedFiles as $file) {
                if (isset($file['tmp_name']) && isset($file['name'])) {
                    $attachments[] = $this->encodeFileForEmail($file['tmp_name'],
                        $file['name']);
                } else {
                    $attachments[] = $this->encodeFileForEmail($file);
                }
            }

            $params['attachments'] = $attachments;
        }

        if ($customheaders) {
            $params['headers'] = $customheaders;
        }

        try {
            $ret = $this->getMandrill()->messages->send($params);
        } catch (Exception $ex) {
            $ret = array(array('status' => 'rejected', 'reject_reason' => $ex->getMessage()));
        }

        $this->last_result = $ret;

        $sent    = 0;
        $failed  = 0;
        $reasons = array();
        if ($ret) {
            foreach ($ret as $result) {
                if (in_array($result['status'], array('rejected', 'invalid'))) {
                    $failed++;
                    if (!empty($result['reject_reason'])) {
                        $reasons[] = $result['reject_reason'];
                    }
                    continue;
                }
                $sent++;
            }
        }

        if ($sent) {
            $this->last_is_error = false;
            return array($original_to, $subject, $htmlContent, $customheaders);
        } else {
            $this->last_is_error = true;
            $this->last_error    = $ret;
            SS_Log::log("Failed to send $failed emails", SS_Log::DEBUG);
            foreach ($reasons as $reason) {
                SS_Log::log("Failed to send because: $reason", SS_Log::DEBUG);
            }
            return false;
        }
    }

    /**
     * @return array
     */
    public function getLastError()
    {
        return $this->last_error;
    }

    /**
     * @return array
     */
    public function getLastResult()
    {
        return $this->last_result;
    }

    /**
     * @return bool
     */
    public function getLastIsError()
    {
        return $this->last_is_error;
    }

    /**
     * Resolve default send from address
     * @param string $from
     * @return string
     */
    public static function resolveDefaultFromEmail($from = null)
    {
        if (!empty($from) && filter_var($from, FILTER_VALIDATE_EMAIL)) {
            return $from;
        }
        $config = SiteConfig::current_site_config();
        if (!empty($config->DefaultFromEmail)) {
            return $config->DefaultFromEmail;
        }
        if ($from = Email::config()->admin_email) {
            return $from;
        }
        return self::createDefaultEmail();
    }

    /**
     * Resolve default send to address
     * @param string $to
     * @return string
     */
    public static function resolveDefaultToEmail($to = null)
    {
        $original_to = $to;
        $to          = MandrillMailer::get_email_from_rfc_email($to);
        if (!empty($to) && filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return $original_to;
        }
        $config = SiteConfig::current_site_config();
        if (!empty($config->DefaultToEmail)) {
            return $config->DefaultToEmail;
        }
        if ($admin = Email::config()->admin_email) {
            return $admin;
        }
        return false;
    }

    /**
     * Create a sensible default address based on domain name
     * @return string
     */
    public static function createDefaultEmail()
    {
        $fulldom = Director::absoluteBaseURL();
        $parse   = parse_url($fulldom);
        $dom     = str_replace('www.', '', $parse['host']);

        return 'postmaster@'.$dom;
    }

    /**
     * Match all words and whitespace, will be terminated by '<'
     *
     * Note: use /u to support utf8 strings
     *
     * @param string $rfc_email_string
     * @return string
     */
    public static function get_displayname_from_rfc_email($rfc_email_string)
    {
        $name       = preg_match('/[\w\s]+/u', $rfc_email_string, $matches);
        $matches[0] = trim($matches[0]);
        return $matches[0];
    }

    /**
     * Extract parts between brackets
     *
     * @param string $rfc_email_string
     * @return string
     */
    public static function get_email_from_rfc_email($rfc_email_string)
    {
        if (strpos($rfc_email_string, '<') === false) {
            return $rfc_email_string;
        }
        $mailAddress = preg_match('/(?:<)(.+)(?:>)$/', $rfc_email_string,
            $matches);
        if (empty($matches)) {
            return $rfc_email_string;
        }
        return $matches[1];
    }
}
