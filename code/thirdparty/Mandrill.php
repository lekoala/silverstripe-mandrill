<?php

/**
 * This is a customised version of the mandrill api sdk available at
 * @https://bitbucket.org/mailchimp/mandrill-api-php/
 *
 * - Fixes follow location (disable follow location in curl and replace curl_exec)
 * - Add IDE helpers
 * - Fix ca_cert.pem if not available
 */

require_once 'Mandrill/Templates.php';
require_once 'Mandrill/Exports.php';
require_once 'Mandrill/Users.php';
require_once 'Mandrill/Rejects.php';
require_once 'Mandrill/Inbound.php';
require_once 'Mandrill/Tags.php';
require_once 'Mandrill/Messages.php';
require_once 'Mandrill/Whitelists.php';
require_once 'Mandrill/Ips.php';
require_once 'Mandrill/Internal.php';
require_once 'Mandrill/Subaccounts.php';
require_once 'Mandrill/Urls.php';
require_once 'Mandrill/Webhooks.php';
require_once 'Mandrill/Senders.php';
require_once 'Mandrill/Metadata.php';
require_once 'Mandrill/Exceptions.php';

/**
 * @property Mandrill_Templates $templates
 * @property Mandrill_Exports $exports
 * @property Mandrill_Users $users
 * @property Mandrill_Rejects $rejects
 * @property Mandrill_Inbound $inbound
 * @property Mandrill_Tags $tags
 * @property Mandrill_Messages $messages
 * @property Mandrill_Whitelists $whitelists
 * @property Mandrill_Ips $ips
 * @property Mandrill_Internal $internal
 * @property Mandrill_Subaccounts $subaccounts
 * @property Mandrill_Urls $urls
 * @property Mandrill_Webhooks $webhooks
 * @property Mandrill_Senders $senders
 * @property Mandrill_Metadata $metadata
 */
class Mandrill
{

    public $apikey;
    public $ch;
    public $root = 'https://mandrillapp.com/api/1.0';
    public $debug = false;

    public static $error_map = array(
        "ValidationError" => "Mandrill_ValidationError",
        "Invalid_Key" => "Mandrill_Invalid_Key",
        "PaymentRequired" => "Mandrill_PaymentRequired",
        "Unknown_Subaccount" => "Mandrill_Unknown_Subaccount",
        "Unknown_Template" => "Mandrill_Unknown_Template",
        "ServiceUnavailable" => "Mandrill_ServiceUnavailable",
        "Unknown_Message" => "Mandrill_Unknown_Message",
        "Invalid_Tag_Name" => "Mandrill_Invalid_Tag_Name",
        "Invalid_Reject" => "Mandrill_Invalid_Reject",
        "Unknown_Sender" => "Mandrill_Unknown_Sender",
        "Unknown_Url" => "Mandrill_Unknown_Url",
        "Unknown_TrackingDomain" => "Mandrill_Unknown_TrackingDomain",
        "Invalid_Template" => "Mandrill_Invalid_Template",
        "Unknown_Webhook" => "Mandrill_Unknown_Webhook",
        "Unknown_InboundDomain" => "Mandrill_Unknown_InboundDomain",
        "Unknown_InboundRoute" => "Mandrill_Unknown_InboundRoute",
        "Unknown_Export" => "Mandrill_Unknown_Export",
        "IP_ProvisionLimit" => "Mandrill_IP_ProvisionLimit",
        "Unknown_Pool" => "Mandrill_Unknown_Pool",
        "NoSendingHistory" => "Mandrill_NoSendingHistory",
        "PoorReputation" => "Mandrill_PoorReputation",
        "Unknown_IP" => "Mandrill_Unknown_IP",
        "Invalid_EmptyDefaultPool" => "Mandrill_Invalid_EmptyDefaultPool",
        "Invalid_DeleteDefaultPool" => "Mandrill_Invalid_DeleteDefaultPool",
        "Invalid_DeleteNonEmptyPool" => "Mandrill_Invalid_DeleteNonEmptyPool",
        "Invalid_CustomDNS" => "Mandrill_Invalid_CustomDNS",
        "Invalid_CustomDNSPending" => "Mandrill_Invalid_CustomDNSPending",
        "Metadata_FieldLimit" => "Mandrill_Metadata_FieldLimit",
        "Unknown_MetadataField" => "Mandrill_Unknown_MetadataField"
    );

    public function __construct($apikey=null)
    {
        if (!$apikey) {
            $apikey = getenv('MANDRILL_APIKEY');
        }
        if (!$apikey) {
            $apikey = $this->readConfigs();
        }
        if (!$apikey) {
            throw new Mandrill_Error('You must provide a Mandrill API key');
        }
        $this->apikey = $apikey;

        $this->ch = curl_init();
        curl_setopt($this->ch, CURLOPT_USERAGENT, 'Mandrill-PHP/1.0.54');
        curl_setopt($this->ch, CURLOPT_POST, true);
//        curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->ch, CURLOPT_HEADER, false);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($this->ch, CURLOPT_TIMEOUT, 600);

        //fix ca cert permissions
        if (strlen(ini_get('curl.cainfo')) === 0) {
            curl_setopt($this->ch, CURLOPT_CAINFO,
                __DIR__."/cacert.pem");
        }

        $this->root = rtrim($this->root, '/') . '/';

        $this->templates = new Mandrill_Templates($this);
        $this->exports = new Mandrill_Exports($this);
        $this->users = new Mandrill_Users($this);
        $this->rejects = new Mandrill_Rejects($this);
        $this->inbound = new Mandrill_Inbound($this);
        $this->tags = new Mandrill_Tags($this);
        $this->messages = new Mandrill_Messages($this);
        $this->whitelists = new Mandrill_Whitelists($this);
        $this->ips = new Mandrill_Ips($this);
        $this->internal = new Mandrill_Internal($this);
        $this->subaccounts = new Mandrill_Subaccounts($this);
        $this->urls = new Mandrill_Urls($this);
        $this->webhooks = new Mandrill_Webhooks($this);
        $this->senders = new Mandrill_Senders($this);
        $this->metadata = new Mandrill_Metadata($this);
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
    public static function curl_exec_follow($ch, &$maxredirect = null)
    {
        $mr = $maxredirect === null ? 5 : intval($maxredirect);

        if (ini_get('open_basedir') == '' && ini_get('safe_mode') == 'Off') {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $mr > 0);
            curl_setopt($ch, CURLOPT_MAXREDIRS, $mr);
//			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        } else {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

            if ($mr > 0) {
                $original_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
                $newurl       = $original_url;

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
                                $newurl = $original_url.$newurl;
                            }
                        } else {
                            $code = 0;
                        }
                    }
                } while ($code && --$mr);

                curl_close($rch);

                if (!$mr) {
                    if ($maxredirect === null) {
                        trigger_error('Too many redirects.', E_USER_WARNING);
                    } else {
                        $maxredirect = 0;
                    }

                    return false;
                }
                curl_setopt($ch, CURLOPT_URL, $newurl);
            }
        }
        return curl_exec($ch);
    }

    public function __destruct()
    {
        curl_close($this->ch);
    }

    public function call($url, $params)
    {
        $params['key'] = $this->apikey;
        $params = json_encode($params);
        $ch = $this->ch;

        curl_setopt($ch, CURLOPT_URL, $this->root . $url . '.json');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_VERBOSE, $this->debug);

        $start = microtime(true);
        $this->log('Call to ' . $this->root . $url . '.json: ' . $params);
        if ($this->debug) {
            $curl_buffer = fopen('php://memory', 'w+');
            curl_setopt($ch, CURLOPT_STDERR, $curl_buffer);
        }

//        $response_body = curl_exec($ch);
        $response_body = self::curl_exec_follow($ch);
        $info = curl_getinfo($ch);
        $time = microtime(true) - $start;
        if ($this->debug) {
            rewind($curl_buffer);
            $this->log(stream_get_contents($curl_buffer));
            fclose($curl_buffer);
        }
        $this->log('Completed in ' . number_format($time * 1000, 2) . 'ms');
        $this->log('Got response: ' . $response_body);

        if (curl_error($ch)) {
            throw new Mandrill_HttpError("API call to $url failed: " . curl_error($ch));
        }
        $result = json_decode($response_body, true);
        if ($result === null) {
            throw new Mandrill_Error('We were unable to decode the JSON response from the Mandrill API: ' . $response_body);
        }

        if (floor($info['http_code'] / 100) >= 4) {
            throw $this->castError($result);
        }

        return $result;
    }

    public function readConfigs()
    {
        $paths = array('~/.mandrill.key', '/etc/mandrill.key');
        foreach ($paths as $path) {
            if (file_exists($path)) {
                $apikey = trim(file_get_contents($path));
                if ($apikey) {
                    return $apikey;
                }
            }
        }
        return false;
    }

    public function castError($result)
    {
        if ($result['status'] !== 'error' || !$result['name']) {
            throw new Mandrill_Error('We received an unexpected error: ' . json_encode($result));
        }

        $class = (isset(self::$error_map[$result['name']])) ? self::$error_map[$result['name']] : 'Mandrill_Error';
        return new $class($result['message'], $result['code']);
    }

    public function log($msg)
    {
        if ($this->debug) {
            error_log($msg);
        }
    }
}
