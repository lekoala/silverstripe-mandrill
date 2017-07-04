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
    protected static $disable_sending = false;
    protected static $enable_logging = false;
    protected static $log_folder = 'silverstripe-cache/emails';
    private static $mandrill_api_key;

    /**
     * Get the API key to use
     *
     * @return string
     */
    public static function get_mandrill_api_key()
    {
        if (defined('MANDRILL_API_KEY')) {
            return MANDRILL_API_KEY;
        } elseif ($apikey = Config::inst()->get(__CLASS__, 'mandrill_api_key')) {
            return $apikey;
        } else {
            throw new Exception('Api key is empty');
        }
    }

    public function __construct()
    {
        $this->mandrill = new Mandrill(self::get_mandrill_api_key());
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
    public static function setAsMailer()
    {
        $mandrillMailer = new MandrillMailer();
        Email::set_mailer($mandrillMailer);
        if (defined('MANDRILL_SENDING_DISABLED') && MANDRILL_SENDING_DISABLED) {
            self::setSendingDisabled();
        }
        if (defined('MANDRILL_ENABLE_LOGGING') && MANDRILL_ENABLE_LOGGING) {
            self::setEnableLogging();
        }

        // Use custom classes
        Object::useCustomClass('Member_ChangePasswordEmail', 'Mandrill_ChangePasswordEmail');
        Object::useCustomClass('Member_ForgotPasswordEmail', 'Mandrill_ForgotPasswordEmail');
    }

    /**
     * @return \MandrillMailer
     */
    public static function getInstance()
    {
        return self::$instance;
    }

    /**
     * @return bool
     */
    public static function getSendingDisabled()
    {
        return self::$disable_sending;
    }

    /**
     * @param bool $v
     */
    public static function setSendingDisabled($v = true)
    {
        self::$disable_sending = $v;
    }

    /**
     * @return bool
     */
    public static function getEnableLogging()
    {
        return self::$enable_logging;
    }

    /**
     * @param bool $v
     */
    public static function setEnableLogging($v = true)
    {
        self::$enable_logging = $v;
    }

    /**
     * @return string
     */
    public static function getLogFolder()
    {
        return self::$log_folder;
    }

    /**
     * @param bool $v
     */
    public static function setLogFolder($v)
    {
        self::$log_folder = $v;
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
    public function encodeFileForEmail($file, $destFileName = false, $disposition = null, $extraHeaders = "")
    {
        if (!$file) {
            user_error("encodeFileForEmail: not passed a filename and/or data", E_USER_WARNING);
            return;
        }

        if (is_string($file)) {
            $file = array('filename' => $file);
            $fh = fopen($file['filename'], "rb");
            if ($fh) {
                $file['contents'] = "";
                while (!feof($fh)) {
                    $file['contents'] .= fread($fh, 10000);
                }
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
    public function sendPlain($to, $from, $subject, $plainContent, $attachedFiles = false, $customheaders = false)
    {
        return $this->send($to, $from, $subject, false, $attachedFiles, $customheaders, $plainContent, false);
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
    public function sendHTML($to, $from, $subject, $htmlContent, $attachedFiles = false, $customheaders = false, $plainContent = false, $inlineImages = false)
    {
        return $this->send($to, $from, $subject, $htmlContent, $attachedFiles, $customheaders, $plainContent, $inlineImages);
    }

    /**
     * Normalize a recipient to an array of email and name
     *
     * @param string|array $recipient
     * @return array
     */
    protected function processRecipient($recipient)
    {
        if (is_array($recipient)) {
            $email = $recipient['email'];
            $name = $recipient['name'];
        } elseif (strpos($recipient, '<') !== false) {
            $email = self::get_email_from_rfc_email($recipient);
            $name = self::get_displayname_from_rfc_email($recipient);
        } else {
            $email = $recipient;
            // As a fallback, extract the first part of the email as the name
            if (self::config()->name_fallback) {
                $name = trim(ucwords(str_replace(array('.', '-', '_'), ' ', substr($email, 0, strpos($email, '@')))));
            } else {
                $name = null;
            }
        }
        return array(
            'email' => $email,
            'name' => $name
        );
    }

    /**
     * A helper method to process a list of recipients
     *
     * @param array $arr
     * @param string|array $recipients
     * @param string $type to - cc - bcc
     * @return array
     */
    protected function appendTo($arr, $recipients, $type = 'to')
    {
        if (!is_array($recipients)) {
            $recipients = explode(',', $recipients);
        }
        foreach ($recipients as $recipient) {
            $r = $this->processRecipient($recipient);
            $arr[] = array(
                'email' => $r['email'],
                'name' => $r['name'],
                'type' => $type
            );
        }
        return $arr;
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
    protected function send($to, $from, $subject, $htmlContent, $attachedFiles = false, $customheaders = false, $plainContent = false, $inlineImages = false)
    {
        $original_to = $to;

        // Process recipients
        $to_array = array();
        $to_array = $this->appendTo($to_array, $to, 'to');
        if (isset($customheaders['Cc'])) {
            $to_array = $this->appendTo($to_array, $customheaders['Cc'], 'cc');
            unset($customheaders['Cc']);
        }
        if (isset($customheaders['Bcc'])) {
            $to_array = $this->appendTo($to_array, $customheaders['Bcc'], 'bcc');
            unset($customheaders['Bcc']);
        }

        // Process sender
        $fromArray = $this->processRecipient($from);
        $fromEmail = $fromArray['email'];
        $fromName = $fromArray['name'];

        // Create params to send to mandrill message api
        $default_params = array();
        if (self::getDefaultParams()) {
            $default_params = self::getDefaultParams();
        }
        $params = array_merge($default_params, array(
            "subject" => $subject,
            "from_email" => $fromEmail,
            "to" => $to_array
        ));
        if ($fromName) {
            $params['from_name'] = $fromName;
        }

        // Inject additional params into message
        if (isset($customheaders['X-MandrillMailer'])) {
            $params = array_merge($params, $customheaders['X-MandrillMailer']);
            unset($customheaders['X-MandrillMailer']);
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
        if (self::getUseGoogleAnalytics() && !Director::isDev()) {
            if (!isset($params['google_analytics_domains'])) {
                // Compute host
                $host = str_replace(Director::protocol(), '', Director::protocolAndHost());

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
                    $attachments[] = $this->encodeFileForEmail($file['tmp_name'], $file['name']);
                } else {
                    $attachments[] = $this->encodeFileForEmail($file);
                }
            }

            $params['attachments'] = $attachments;
        }

        $sendingDisabled = false;
        if (isset($customheaders['X-SendingDisabled']) && $customheaders['X-SendingDisabled']) {
            $sendingDisabled = $sendingDisabled;
            unset($customheaders['X-SendingDisabled']);
        }

        if ($customheaders) {
            $params['headers'] = $customheaders;
        }

        if (self::getEnableLogging()) {
            // Append some extra information at the end
            $logContent = $htmlContent;
            $logContent .= '<pre>';
            $logContent .= 'To : ' . print_r($original_to, true) . "\n";
            $logContent .= 'Subject : ' . $subject . "\n";
            $logContent .= 'Headers : ' . print_r($customheaders, true) . "\n";
            if (!empty($params['from_email'])) {
                $logContent .= 'From email : ' . $params['from_email'] . "\n";
            }
            if (!empty($params['from_name'])) {
                $logContent .= 'From name : ' . $params['from_name'] . "\n";
            }
            if (!empty($params['to'])) {
                $logContent .= 'Recipients : ' . print_r($params['to'], true) . "\n";
            }
            $logContent .= '</pre>';

            // Store it
            $logFolder = BASE_PATH . '/' . self::getLogFolder();
            if (!is_dir($logFolder)) {
                mkdir($logFolder, 0777, true);
            }
            $filter = new FileNameFilter();
            $title = substr($filter->filter($subject), 0, 20);
            $r = file_put_contents($logFolder . '/' . time() . '-' . $title . '.html', $logContent);
            if (!$r) {
                throw new Exception('Failed to store email in ' . $logFolder);
            }
        }

        if (self::getSendingDisabled() || $sendingDisabled) {
            $customheaders['X-SendingDisabled'] = true;
            return array($original_to, $subject, $htmlContent, $customheaders);
        }

        try {
            $ret = $this->getMandrill()->messages->send($params);
        } catch (Exception $ex) {
            $ret = array(array('status' => 'rejected', 'reject_reason' => $ex->getMessage()));
        }

        $this->last_result = $ret;

        $sent = 0;
        $failed = 0;
        $reasons = array();
        if ($ret) {
            foreach ($ret as $result) {
                if (in_array($result['status'], array('rejected', 'invalid'))) {
                    $failed++;
                    if (!empty($result['reject_reason'])) {
                        $reasons[] = $result['reject_reason'];
                    } elseif ($result['status'] == 'invalid') {
                        $reasons[] = 'Email "' . $result['email'] . '" is invalid';
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
            $this->last_error = $ret;
            SS_Log::log("Failed to send $failed emails", SS_Log::DEBUG);
            foreach ($reasons as $reason) {
                SS_Log::log("Failed to send because: $reason", SS_Log::DEBUG);
            }
            return false;
        }
    }

    /**
     * Sends emails using specified template in mandrill.
     * Note: not all parameters and features of mandrill->messages->sendTemplate are implimented.
     * @param  string $templateName The name of the template in mandrill.
     * @param  array $globalMergeVars associative array of merge vars.
     * @param  string $to email address to send the message.
     * @param  string $from the email address the message is from.
     * @param  string $subject subject of the email.
     * @param  array $customheaders custom headers to add to the request.
     * @param  array $attachFiles Single dimension array of absolute paths to files
     * @return bool success indicates result to sending the message to mantrill.
     */
    public function sendTemplate($templateName, $globalMergeVars, $to, $from, $subject, $customheaders, $attachFiles = array())
    {
        // Process recipients
        $to_array = array();
        $to_array = $this->appendTo($to_array, $to, 'to');
        if (isset($customheaders['Cc'])) {
            $to_array = $this->appendTo($to_array, $customheaders['Cc'], 'cc');
            unset($customheaders['Cc']);
        }
        if (isset($customheaders['Bcc'])) {
            $to_array = $this->appendTo($to_array, $customheaders['Bcc'], 'bcc');
            unset($customheaders['Bcc']);
        }

        // Process sender
        $fromArray = $this->processRecipient($from);
        $fromEmail = $fromArray['email'];
        $fromName = $fromArray['name'];

        // Create params to send to mandrill message api
        $default_params = array();

        if (self::getDefaultParams()) {
            $default_params = self::getDefaultParams();
        }

        // Put together the parameters.
        $params = array_merge(
            $default_params, array(
            "subject" => $subject,
            "from_email" => $fromEmail,
            "to" => $to_array
            )
        );

        if ($fromName) {
            $params['from_name'] = $fromName;
        }

        // If merge vars specified then include them.
        if ($globalMergeVars) {

            // Need to convert to the correct format for sending via the api.
            $mergeVars = array();

            foreach ($globalMergeVars as $key => $val) {
                $mergeVars[] = array(
                    'name' => $key,
                    'content' => $val
                );
            }

            $params['global_merge_vars'] = $mergeVars;
        }

        if ($attachFiles) {
            $attachments = array();

            foreach($attachFiles as $file) {
                $attachments[] = $this->encodeFileForEmail($file);
            }

            $params['attachments'] = $attachments;
        }

        // Inject additional params into message
        if (isset($customheaders['X-MandrillMailer'])) {
            $params = array_merge($params, $customheaders['X-MandrillMailer']);
            unset($customheaders['X-MandrillMailer']);
        }

        if ($customheaders) {
            $params['headers'] = $customheaders;
        }

        // @TODO probably/possibly need to also do the following things like in function above.
        // BCC, Analytics, Attachments, Global Tags, Logging.
        // -------------------
        // Finally try sending the message with the sendTemplate() function in the Messages class.
        try {
            $ret = $this->getMandrill()->messages->sendTemplate($templateName, null, $params);
        } catch (Exception $ex) {
            $ret = array(array('status' => 'rejected', 'reject_reason' => $ex->getMessage()));
        }

        $this->last_result = $ret;

        // Process the results, extracting the reasons for failure.
        $sent = 0;
        $failed = 0;
        $reasons = array();
        if ($ret) {
            foreach ($ret as $result) {
                if (in_array($result['status'], array('rejected', 'invalid'))) {
                    $failed++;
                    if (!empty($result['reject_reason'])) {
                        $reasons[] = $result['reject_reason'];
                    } elseif ($result['status'] == 'invalid') {
                        $reasons[] = 'Email "' . $result['email'] . '" is invalid';
                    }
                    continue;
                }
                $sent++;
            }
        }

        if ($sent) {
            $this->last_is_error = false;
            // @TODO work out if anything more needs to be returned for this.
            return true;
        } else {
            $this->last_is_error = true;
            $this->last_error = $ret;
            SS_Log::log("Failed to send $failed emails", SS_Log::DEBUG);
            foreach ($reasons as $reason) {
                if ($reason == 'unsigned') {
                    $reason .= ' - senders domain ' . $fromEmail . ' is not properly configured';
                }
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
     * @param string|array $from
     * @return string
     */
    public static function resolveDefaultFromEmail($from = null)
    {
        // If we pass an array, normalize it to a rfc string
        if (is_array($from) && isset($from['email'])) {
            if (isset($from['name'])) {
                $from = $from['name'] . ' <' . $from['email'] . '>';
            } else {
                $from = $from['email'];
            }
        }
        $original_from = $from;
        if (!empty($from)) {
            $from = MandrillMailer::get_email_from_rfc_email($from);
            if (filter_var($from, FILTER_VALIDATE_EMAIL)) {
                return $original_from;
            }
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
        // In case of multiple recipients, do not validate anything
        if (is_array($to) || strpos($to, ',') !== false) {
            return $to;
        }
        $original_to = $to;
        if (!empty($to)) {
            $to = MandrillMailer::get_email_from_rfc_email($to);
            if (filter_var($to, FILTER_VALIDATE_EMAIL)) {
                return $original_to;
            }
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
        $parse = parse_url($fulldom);
        $dom = str_replace('www.', '', $parse['host']);

        return 'postmaster@' . $dom;
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
        $name = preg_match('/[\w\s]+/u', $rfc_email_string, $matches);
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
        $mailAddress = preg_match('/(?:<)(.+)(?:>)$/', $rfc_email_string, $matches);
        if (empty($matches)) {
            return $rfc_email_string;
        }
        return $matches[1];
    }
}
