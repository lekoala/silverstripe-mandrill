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

class MandrillMailer extends Mailer {

	/**
	 * @var Mandrill
	 */
	protected $mandrill;
	protected static $instance;

	function __construct($apiKey) {
		$this->mandrill = new Mandrill($apiKey);
		//fix ca cert permissions
		if (strlen(ini_get('curl.cainfo')) === 0) {
			curl_setopt($this->mandrill->ch, CURLOPT_CAINFO, __DIR__ . "/cacert.pem");
		}
		self::$instance = $this;
	}

	/**
	 * A workaround for cURL: follow locations with safe_mode enabled or open_basedir set
	 * 
	 * This method is used in the call method in Mandrill.php instead of the original curl_exec
	 * 
	 * @link http://slopjong.de/2012/03/31/curl-follow-locations-with-safe_mode-enabled-or-open_basedir-set/
	 * @param resource $ch
	 * @param int $maxredirect
	 * @return boolean
	 */
	static function curl_exec_follow($ch, &$maxredirect = null) {
		$mr = $maxredirect === null ? 5 : intval($maxredirect);

		if (ini_get('open_basedir') == '' && ini_get('safe_mode') == 'Off') {
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $mr > 0);
			curl_setopt($ch, CURLOPT_MAXREDIRS, $mr);
//			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		} else {

			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

			if ($mr > 0) {
				$original_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
				$newurl = $original_url;

				$rch = curl_copy_handle($ch);

				curl_setopt($rch, CURLOPT_HEADER, true);
				curl_setopt($rch, CURLOPT_NOBODY, true);
				curl_setopt($rch, CURLOPT_FORBID_REUSE, false);
				do {
					curl_setopt($rch, CURLOPT_URL, $newurl);
					$header = curl_exec($rch);
					if (curl_errno($rch)) {
						$code = 0;
					} else {
						$code = curl_getinfo($rch, CURLINFO_HTTP_CODE);
						if ($code == 301 || $code == 302) {
							preg_match('/Location:(.*?)\n/', $header, $matches);
							$newurl = trim(array_pop($matches));

							// if no scheme is present then the new url is a
							// relative path and thus needs some extra care
							if (!preg_match("/^https?:/i", $newurl)) {
								$newurl = $original_url . $newurl;
							}
						} else {
							$code = 0;
						}
					}
				} while ($code && --$mr);

				curl_close($rch);

				if (!$mr) {
					if ($maxredirect === null)
						trigger_error('Too many redirects.', E_USER_WARNING);
					else
						$maxredirect = 0;

					return false;
				}
				curl_setopt($ch, CURLOPT_URL, $newurl);
			}
		}
		return curl_exec($ch);
	}

	/**
	 * Get mandrill api
	 * @return \Mandrill
	 */
	public function getMandrill() {
		return $this->mandrill;
	}

	/**
	 * Set mandrill api
	 * @param Mandrill $mandrill
	 */
	public function setMandrill(Mandrill $mandrill) {
		$this->mandrill = $mandrill;
	}

	/**
	 * @return \MandrillMailer
	 */
	public static function getInstance() {
		return self::$instance;
	}

	/**
	 * Get default params used by all outgoing emails
	 * @return string
	 */
	public static function getDefaultParams() {
		return Config::inst()->get(__CLASS__, 'default_params');
	}

	/**
	 * Set default params
	 * @param string $v
	 * @return \MandrillMailer
	 */
	public static function setDefaultParams(array $v) {
		return Config::inst()->update(__CLASS__, 'default_params', $v);
	}

	/**
	 * Get subaccount used by all outgoing emails
	 * @return string
	 */
	public static function getSubaccount() {
		return Config::inst()->get(__CLASS__, 'subaccount');
	}

	/**
	 * Set subaccount
	 * @param string $v
	 * @return \MandrillMailer
	 */
	public static function setSubaccount($v) {
		return Config::inst()->update(__CLASS__, 'subaccount', $v);
	}

	/**
	 * Get global tags applied to all outgoing emails
	 * @return array
	 */
	public static function getGlobalTags() {
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
	public static function setGlobalTags($arr) {
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
	function encodeFileForEmail($file, $destFileName = false, $disposition = NULL, $extraHeaders = "") {
		if (!$file) {
			user_error("encodeFileForEmail: not passed a filename and/or data", E_USER_WARNING);
			return;
		}

		if (is_string($file)) {
			$file = array('filename' => $file);
			$fh = fopen($file['filename'], "rb");
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
	function sendPlain($to, $from, $subject, $plainContent, $attachedFiles = false, $customheaders = false) {
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
	function sendHTML($to, $from, $subject, $htmlContent, $attachedFiles = false, $customheaders = false, $plainContent = false, $inlineImages = false) {
		return $this->send($to, $from, $subject, $htmlContent, $attachedFiles, $customheaders, $plainContent, $inlineImages);
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
	 * @return array|bool
	 */
	protected function send($to, $from, $subject, $htmlContent, $attachedFiles = false, $customheaders = false, $plainContent = false, $inlineImages = false) {
		$orginal_to = $to;
		$tos = explode(',', $to);

		$to = array();
		foreach ($tos as $t) {
			if (strpos($t, '<') !== false) {
				$to[] = array(
					'name' => self::get_displayname_from_rfc_email($t),
					'email' => self::get_email_from_rfc_email($t)
				);
			} else {
				$to[] = array('email' => $t);
			}
		}
		
		$default_params = array();
		if (self::getDefaultParams()) {
			$default_params = self::getDefaultParams();
		}
		$params = array_merge($default_params, array(
			"subject" => $subject,
			"from_email" => $from,
			"to" => $to
		));

		if (is_array($from)) {
			$params['from_email'] = $from['email'];
			$params['from_name'] = $from['name'];
		}

		if ($plainContent) {
			$params['text'] = $plainContent;
		}
		if ($htmlContent) {
			$params['html'] = $htmlContent;
		}

		if (self::getGlobalTags()) {
			if (!isset($params['tags'])) {
				$params['tags'] = array();
			}
			$params['tags'] = array_merge($params['tags'], self::getGlobalTags());
		}

		if (self::getSubaccount()) {
			$params['subaccount'] = self::getSubaccount();
		}

		$bcc_email = Config::inst()->get('Email', 'bcc_all_emails_to');
		if ($bcc_email) {
			if (is_string($bcc_email)) {
				$params['bcc_address'] = $bcc_email;
			}
		}

		if ($attachedFiles) {
			$attachments = array();

			// Include any specified attachments as additional parts
			foreach ($attachedFiles as $file) {
				if (isset($file['tmp_name']) && isset($file['name'])) {
					$messageParts[] = $this->encodeFileForEmail($file['tmp_name'], $file['name']);
				} else {
					$messageParts[] = $this->encodeFileForEmail($file);
				}
			}

			$params['attachments'] = $messageParts;
		}

		if ($customheaders) {
			$params['headers'] = $customheaders;
		}

		$ret = $this->getMandrill()->messages->send($params);

		if ($ret) {
			return array($orginal_to, $subject, $htmlContent, $customheaders);
		} else {
			return false;
		}
	}

	public static function get_displayname_from_rfc_email($rfc_email_string) {
		// match all words and whitespace, will be terminated by '<'
		$name = preg_match('/[\w\s]+/', $rfc_email_string, $matches);
		$matches[0] = trim($matches[0]);
		return $matches[0];
	}

	public static function get_email_from_rfc_email($rfc_email_string) {
		// extract parts between the two parentheses
		$mailAddress = preg_match('/(?:<)(.+)(?:>)$/', $rfc_email_string, $matches);
		return $matches[1];
	}

}
