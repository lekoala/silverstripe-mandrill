<?php

require_once "thirdarty/Mandrill.php";

/*
 * MandrillMailer for Silverstripe
 * 
 * Features
 * - Global tag support
 * - Multiple recipient support
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

	public function getMandrill() {
		return $this->mandrill;
	}

	public function setMandrill(Mandrill $mandrill) {
		$this->mandrill = $mandrill;
	}

	public function getGlobalTags() {
		$tags = $this->config()->get('global_tags');
		if (!is_array($tags)) {
			$tags = array($tags);
		}
		return $tags;
	}

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

		if (!$destFileName)
			$base = basename($file['filename']);
		else
			$base = $destFileName;

		$mimeType = !empty($file['mimetype']) ? $file['mimetype'] : HTTP::get_mime_type($file['filename']);
		if (!$mimeType)
			$mimeType = "application/unknown";

		// Return completed packet
		return array(
			'type' => $mimeType,
			'name' => $destFileName,
			'content' => $file['contents']
		);
	}

	function sendPlain($to, $from, $subject, $plainContent, $attachedFiles = false, $customheaders = false) {
		return $this->send($to, $from, $subject, false, $attachedFiles, $customheaders, $plainContent, false);
	}

	function sendHTML($to, $from, $subject, $htmlContent, $attachedFiles = false, $customheaders = false, $plainContent = false, $inlineImages = false) {
		return $this->send($to, $from, $subject, $htmlContent, $attachedFiles, $customheaders, $plainContent, $inlineImages);
	}

	protected function send($to, $from, $subject, $htmlContent, $attachedFiles = false, $customheaders = false, $plainContent = false, $inlineImages = false) {
		$orginal_to = $to;
		$tos = explode(',', $to);

		if (count($tos) > 1) {
			$to = array();
			foreach ($tos as $t) {
				$to[] = array('email' => $t);
			}
		}

		if (!is_array($to)) {
			$to = array(array('email' => $to));
		}

		$params = array(
			"subject" => $subject,
			"from_email" => $from,
			"to" => $to
		);

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

		if ($this->global_tags) {
			$params['tags'] = $this->global_tags;
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

			$params['attachments'] = $attachments;
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

}
