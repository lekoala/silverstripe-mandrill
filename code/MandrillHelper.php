<?php
namespace LeKoala\Mandrill;

use Exception;
use Mandrill;
use SilverStripe\Control\Director;
use SilverStripe\Control\Email\Email;
use SilverStripe\Control\Email\Mailer;
use SilverStripe\Control\Email\SwiftMailer;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\SiteConfig\SiteConfig;

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
     * Get the mailer instance
     *
     * @return SwiftMailer
     */
    public static function getMailer()
    {
        return Injector::inst()->get(Mailer::class);
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
            $key = self::config()->api_key;
            if (empty($key)) {
                throw new Exception("api_key is not configured for " . __class__);
            }
            self::$client = new Mandrill($key);
        }
        return self::$client;
    }

    /**
     * Get the log folder and create it if necessary
     *
     * @return string
     */
    public static function getLogFolder()
    {
        $logFolder = BASE_PATH . '/' . self::config()->log_folder;
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
        // Regular api key used for sending emails (including subaccount support)
        $api_key = Environment::getEnv('MANDRILL_API_KEY');
        if ($api_key) {
            self::config()->api_key = $api_key;
        }

        $sending_disabled = Environment::getEnv('MANDRILL_SENDING_DISABLED');
        if ($sending_disabled) {
            self::config()->disable_sending = $sending_disabled;
        }
        $enable_logging = Environment::getEnv('MANDRILL_ENABLE_LOGGING');
        if ($enable_logging) {
            self::config()->enable_logging = $enable_logging;
        }

        // We have a key, we can register the transport
        if (self::config()->api_key) {
            self::registerTransport();
        }
    }

    /**
     * Register the transport with the client
     *
     * @return SwiftMailer The updated swift mailer
     * @throws Exception
     */
    public static function registerTransport()
    {
        $client = self::getClient();
        $mailer = self::getMailer();
        if (!$mailer instanceof SwiftMailer) {
            throw new Exception("Mailer must be an instance of " . SwiftMailer::class . " instead of " . get_class($mailer));
        }
        $transport = new MandrillSwiftTransport($client);
        $newSwiftMailer = $mailer->getSwiftMailer()->newInstance($transport);
        $mailer->setSwiftMailer($newSwiftMailer);
        return $mailer;
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
}
