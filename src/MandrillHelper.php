<?php

namespace LeKoala\Mandrill;

use Mandrill;
use Exception;
use ReflectionObject;
use SilverStripe\Control\Director;
use SilverStripe\Core\Environment;
use Symfony\Component\Mailer\Mailer;
use SilverStripe\Control\Email\Email;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Config\Configurable;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport\AbstractTransport;

/**
 * This configurable class helps decoupling the api client from SilverStripe
 */
class MandrillHelper
{
    use Configurable;

    /**
     * Client instance
     *
     * @var Mandrill
     */
    protected static $client;

    /**
     * Use this to set the app domain registered in mandrill.
     * Ignored if MANDRILL_DOMAIN is set.
     *
     * @config
     * @var string
     */
    private static $domain = null;


    /**
     * Use this to set the app domain via a siteconfig field. Ignored if $domain is set.
     *
     * @config
     * @var string
     */
    private static $siteconfig_domain = null;

    /**
     * Use this to set the logging folder. E.g. _logs/emails. Will be appended to BASE_PATH
     * so must be relative to this.
     *
     * @config
     * @var string
     */
    private static $log_folder = null;

    /**
     * Set to true to enable logging. Set to true if MANDRILL_ENABLE_LOGGING env is set.
     *
     * @config
     * @var bool
     */
    private static $enable_logging = false;

    /**
     * Used to set the mandrill API key if MANDRILL_API_KEY isn't set
     *
     * @config
     * @var string
     */
    private static $api_key = null;

    /**
     * Set to true if sending should be disabled. E.g. for testing.
     * MANDRILL_SENDING_DISABLED env will overwrite this if set.
     *
     * @config
     * @var bool
     */
    private static $disable_sending = false;

    /**
     * Get the log folder and create it if necessary
     *
     * @return string
     */
    public static function getLogFolder()
    {
        $folder = self::config()->log_folder;
        if (empty($folder)) {
            return null;
        }

        $logFolder = BASE_PATH . '/' . $folder;
        if (!is_dir($logFolder)) {
            mkdir($logFolder, 0755, true);
        }
        return $logFolder;
    }

    /**
     * Process environment variable to configure this module
     *
     * @return void
     * @throws Exception
     */
    public static function init()
    {
        // We have a key, we can register the transport
        $apiKey = static::getAPIKey();
        if ($apiKey) {
            self::registerTransport();
        }
    }

    /**
     * Get api key if enabled
     *
     * @return string|null
     */
    public static function getAPIKey()
    {
        // Regular api key used for sending emails (including subaccount support)
        $apiKey = Environment::getEnv('MANDRILL_API_KEY');
        if ($apiKey) {
            return $apiKey;
        }

        $apiKey = self::config()->get('api_key');
        if ($apiKey) {
            return $apiKey;
        }

        return null;
    }

    /**
     * Register the transport with the client
     *
     * @return MandrillApiTransport The updated mailer
     * @throws Exception
     */
    public static function registerTransport()
    {
        $client = self::getClient();
        $mailer = self::getMailer();
        $transport = new MandrillApiTransport($client);
        $mailer = new Mailer($transport);
        Injector::inst()->registerService($mailer, MailerInterface::class);
        return $mailer;
    }

    /**
     * Get the api client instance
     * @return Mandrill
     *
     * @throws Exception
     */
    public static function getClient()
    {
        if (!self::$client) {
            $key = static::getAPIKey();
            if (empty($key)) {
                throw new Exception("api_key is not configured for " . __class__);
            }
            self::$client = new Mandrill($key);
        }
        return self::$client;
    }

    /**
     * @param MailerInterface $mailer
     * @return AbstractTransport|MandrillApiTransport
     */
    public static function getTransportFromMailer($mailer)
    {
        $r = new ReflectionObject($mailer);
        $p = $r->getProperty('transport');
        $p->setAccessible(true);
        return $p->getValue($mailer);
    }

    /**
     * Get the mailer instance
     *
     * @return MailerInterface
     */
    public static function getMailer()
    {
        return Injector::inst()->get(MailerInterface::class);
    }

    /**
     * @return array
     */
    public static function listValidDomains()
    {
        $list = self::config()->valid_domains;
        if (!$list) {
            $list = [];
        }
        return $list;
    }

    /**
     * Get domain configured for this application
     *
     * @return bool|string
     */
    public static function getDomain()
    {
        // Use env var
        $domain = Environment::getEnv('MANDRILL_DOMAIN');
        if ($domain) {
            return $domain;
        }

        // Use configured domain
        $domain = self::config()->domain;
        if ($domain) {
            return $domain;
        }

        // Look in siteconfig for default sender
        $config = SiteConfig::current_site_config();
        $config_field = self::config()->siteconfig_domain;
        if ($config_field && !empty($config->$config_field)) {
            return $config->$config_field;
        }

        // Guess from email
        $domain = static::getDomainFromEmail();
        if ($domain) {
            return $domain;
        }

        // Guess from host
        return static::getDomainFromHost();
    }

    /**
     * Get domain from admin email
     *
     * @return bool|string
     */
    public static function getDomainFromEmail()
    {
        $email = static::resolveDefaultFromEmail(null, false);
        if ($email) {
            $domain = substr(strrchr($email, "@"), 1);
            return $domain;
        }
        return false;
    }

    /**
     * Resolve default send from address
     *
     * Keep in mind that an email using send() without a from
     * will inject the admin_email. Therefore, SiteConfig
     * will not be used
     *
     * @param string $from
     * @param bool $createDefault
     * @return string
     */
    public static function resolveDefaultFromEmail($from = null, $createDefault = true)
    {
        $original_from = $from;
        if (!empty($from)) {
            // If we have a sender, validate its email
            $from = EmailUtils::get_email_from_rfc_email($from);
            if (filter_var($from, FILTER_VALIDATE_EMAIL)) {
                return $original_from;
            }
        }
        // Look in siteconfig for default sender
        $config = SiteConfig::current_site_config();
        $config_field = self::config()->siteconfig_from;
        if ($config_field && !empty($config->$config_field)) {
            return $config->$config_field;
        }
        // Use admin email
        if ($admin = Email::config()->admin_email) {
            return $admin;
        }
        // If we still don't have anything, create something based on the domain
        if ($createDefault) {
            return self::createDefaultEmail();
        }
        return false;
    }

    /**
     * Create a sensible default address based on domain name
     *
     * @return string
     */
    public static function createDefaultEmail()
    {
        $fulldom = Director::absoluteBaseURL();
        $host = parse_url($fulldom, PHP_URL_HOST);
        if (!$host) {
            $host = 'localhost';
        }
        $dom = str_replace('www.', '', $host);

        return 'postmaster@' . $dom;
    }

    /**
     * Get domain name from current host
     *
     * @return bool|string
     */
    public static function getDomainFromHost()
    {
        $base = Environment::getEnv('SS_BASE_URL');
        if (!$base) {
            $base = Director::protocolAndHost();
        }
        $host = parse_url($base, PHP_URL_HOST);
        $hostParts = explode('.', $host);
        $parts = count($hostParts);
        if ($parts < 2) {
            return false;
        }
        $domain = $hostParts[$parts - 2] . "." . $hostParts[$parts - 1];
        return $domain;
    }

    /**
     * Resolve default send to address
     *
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
            $to = EmailUtils::get_email_from_rfc_email($to);
            if (filter_var($to, FILTER_VALIDATE_EMAIL)) {
                return $original_to;
            }
        }
        $config = SiteConfig::current_site_config();
        $config_field = self::config()->siteconfig_to;
        if ($config_field && !empty($config->$config_field)) {
            return $config->$config_field;
        }
        if ($admin = Email::config()->admin_email) {
            return $admin;
        }
        return false;
    }

    /**
     * Is logging enabled?
     *
     * @return bool
     */
    public static function getLoggingEnabled()
    {
        if (Environment::getEnv('MANDRILL_ENABLE_LOGGING')) {
            return true;
        }

        if (self::config()->get('enable_logging')) {
            return true;
        }

        return false;
    }

    /**
     * Is sending enabled?
     *
     * @return bool
     */
    public static function getSendingEnabled()
    {
        $disabled = Environment::getEnv('MANDRILL_SENDING_DISABLED');
        if ($disabled) {
            return false;
        }
        if (self::config()->get('disable_sending')) {
            return false;
        }
        return true;
    }
}
