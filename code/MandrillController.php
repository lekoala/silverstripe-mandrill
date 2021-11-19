<?php

namespace LeKoala\Mandrill;

use Psr\Log\LoggerInterface;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Environment;

/**
 * Provide extensions points for handling the webhook
 *
 * @author LeKoala <thomas@lekoala.be>
 */
class MandrillController extends Controller
{
    const EVENT_SEND = 'send';
    const EVENT_HARD_BOUNCE = 'hard_bounce';
    const EVENT_SOFT_BOUNCE = 'soft_bounce';
    const EVENT_OPEN = 'open';
    const EVENT_CLICK = 'click';
    const EVENT_SPAM = 'spam';
    const EVENT_UNSUB = 'unsub';
    const EVENT_REJECT = 'reject';
    const EVENT_INBOUND = 'inbound';
    const EVENT_WHITELIST = 'whitelist';
    const EVENT_BLACKLIST = 'blacklist';

    protected $eventsCount = 0;
    protected $skipCount = 0;
    private static $allowed_actions = [
        'incoming',
    ];

    private static $webhook_auth_enabled = false;

    /**
     * Inject public dependencies into the controller
     *
     * @var array
     */
    private static $dependencies = [
        'logger' => '%$Psr\Log\LoggerInterface',
    ];

    /**
     * @var LoggerInterface
     */
    public $logger;

    /**
     * Handle incoming webhook
     *
     * @link http://help.mandrill.com/entries/21738186-introduction-to-webhooks
     * @link http://help.mandrill.com/entries/22092308-What-is-the-format-of-inbound-email-webhooks-
     * @param HTTPRequest $req
     * @return HTTPResponse
     */
    public function incoming(HTTPRequest $req)
    {
        $generatedSignature = $this->generateSignature($req->postVars());
        $mandrillSignature = $req->getHeader('X-Mandrill-Signature');
        $json = $req->postVar('mandrill_events');

        // By default, return a valid response
        $response = $this->getResponse();
        $response->setStatusCode(200);
        $response->setBody('');

        //make sure the generated signature matches the X-Mandrill-Signature header if webook auth is enabled
        if (self::config()->webhook_auth_enabled && $generatedSignature !== $mandrillSignature) {
            return $response;
        }

        if (!$json) {
            return $response;
        }

        $events = json_decode($json);

        foreach ($events as $ev) {
            $this->handleAnyEvent($ev);

            $event = $ev->event;
            switch ($event) {
                // Sync type
                case self::EVENT_BLACKLIST:
                case self::EVENT_WHITELIST:
                    $this->handleSyncEvent($ev);
                    break;
                // Inbound type
                case self::EVENT_INBOUND:
                    $this->handleInboundEvent($ev);
                    break;
                // Message type
                case self::EVENT_CLICK:
                case self::EVENT_HARD_BOUNCE:
                case self::EVENT_OPEN:
                case self::EVENT_REJECT:
                case self::EVENT_SEND:
                case self::EVENT_SOFT_BOUNCE:
                case self::EVENT_SPAM:
                case self::EVENT_UNSUB:
                    $this->handleMessageEvent($ev);
                    break;
            }
        }
        return $response;
    }

    protected function handleAnyEvent($e)
    {
        $this->extend('updateHandleAnyEvent', $e);
    }

    protected function handleSyncEvent($e)
    {
        $this->extend('updateHandleSyncEvent', $e);
    }

    protected function handleInboundEvent($e)
    {
        $this->extend('updateHandleInboundEvent', $e);
    }

    protected function handleMessageEvent($e)
    {
        $this->extend('updateHandleMessageEvent', $e);
    }

    /**
     * Get logger
     *
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * generates signature to verify request is from mailchimp.
     * see https://mailchimp.com/developer/transactional/guides/track-respond-activity-webhooks/#authenticating-webhook-requests
     *
     * @param Array @postVars
     * @return string
     */
    protected function generateSignature(array $postVars)
    {
        ksort($postVars);
        $data = MandrillAdmin::create()->singleton()->WebhookUrl();
        $key = Environment::getEnv('MANDRILL_WEBHOOK_KEY');

        foreach ($postVars as $key => $value) {
            $data .= $key;
            $data .= $value;
        }

        if (self::config()->webhook_key) {
            $key = self::config()->webhook_key;
        }

        return base64_encode(hash_hmac('sha1', $data, Environment::getEnv('MANDRILL_WEBHOOK_KEY'), true));
    }
}
