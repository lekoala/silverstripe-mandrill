<?php

namespace LeKoala\Mandrill;

use Exception;
use Mandrill;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionException;
use SilverStripe\Assets\FileNameFilter;
use SilverStripe\Control\Director;
use SilverStripe\Core\Injector\Injector;
use Swift_Attachment;
use Swift_Events_EventListener;
use Swift_Events_SendEvent;
use Swift_Events_SimpleEventDispatcher;
use Swift_Mime_Header;
use Swift_Mime_Headers_OpenDKIMHeader;
use Swift_Mime_Headers_UnstructuredHeader;
use Swift_Mime_Message;
use Swift_MimePart;
use Swift_Transport;
use Swift_Transport_SimpleMailInvoker;

/**
 * A Mandrill transport for Swift Mailer using our custom client
 *
 * Heavily inspired by AccordGroup/MandrillSwiftMailer
 *
 * @link https://github.com/slowprog/MandrillSwiftMailer
 * @link https://www.Mandrill.com/api#/reference/introduction
 * @link https://github.com/AccordGroup/MandrillSwiftMailer
 * @author LeKoala <thomas@lekoala.be>
 */
class MandrillSwiftTransport implements Swift_Transport
{

    /**
     * @var Swift_Transport_SimpleMailInvoker
     */
    protected $invoker;

    /**
     * @var Swift_Events_SimpleEventDispatcher
     */
    protected $eventDispatcher;

    /**
     * @var Mandrill
     */
    protected $client;

    /**
     * @var array
     */
    protected $resultApi;

    /**
     * @var string
     */
    protected $fromEmail;

    /**
     * @var boolean
     */
    protected $isStarted = false;

    public function __construct(Mandrill $client)
    {
        $this->client = $client;

        $this->invoker = new \Swift_Transport_SimpleMailInvoker();
        $this->eventDispatcher = new \Swift_Events_SimpleEventDispatcher();
    }

    /**
     * Not used
     */
    public function isStarted()
    {
        return $this->isStarted;
    }

    /**
     * Not used
     */
    public function start()
    {
        $this->isStarted = true;
    }

    /**
     * Not used
     */
    public function stop()
    {
        $this->isStarted = false;
    }

    /**
     * @param Swift_Mime_Message $message
     * @param null $failedRecipients
     * @return int Number of messages sent
     * @throws ReflectionException
     * @throws Exception
     */
    public function send(Swift_Mime_Message $message, &$failedRecipients = null)
    {
        $this->resultApi = null;
        if ($event = $this->eventDispatcher->createSendEvent($this, $message)) {
            $this->eventDispatcher->dispatchEvent($event, 'beforeSendPerformed');
            if ($event->bubbleCancelled()) {
                return 0;
            }
        }

        $sendCount = 0;
        $disableSending = $message->getHeaders()->has('X-SendingDisabled')
            || !MandrillHelper::getSendingEnabled();

        $mandrillMessage = $this->getMandrillMessage($message);
        $client = $this->client;

        if ($disableSending) {
            $result = [];
            foreach ($mandrillMessage['to'] as $recipient) {
                $result[] = [
                    'email' => $recipient['email'],
                    'status' => 'sent',
                    'reject_reason' => '',
                    '_id' => uniqid(),
                    'disabled' => true,
                ];
            }
        } else {
            $result = $client->messages->send($mandrillMessage);
        }
        $this->resultApi = $result;

        if (MandrillHelper::getLoggingEnabled()) {
            $this->logMessageContent($message, $result);
        }

        foreach ($this->resultApi as $item) {
            if ($item['status'] === 'sent' || $item['status'] === 'queued') {
                $sendCount++;
            } else {
                $failedRecipients[] = $item['email'];
            }
        }

        if ($event) {
            if ($sendCount > 0) {
                $event->setResult(Swift_Events_SendEvent::RESULT_SUCCESS);
            } else {
                $event->setResult(Swift_Events_SendEvent::RESULT_FAILED);
            }

            $this->eventDispatcher->dispatchEvent($event, 'sendPerformed');
        }

        return $sendCount;
    }

    /**
     * Log message content
     *
     * @param Swift_Mime_Message $message
     * @param array $results Results from the api
     * @throws Exception
     */
    protected function logMessageContent(Swift_Mime_Message $message, $results = [])
    {
        // Folder not set
        $logFolder = MandrillHelper::getLogFolder();
        if ($logFolder) {
            return;
        }
        // Logging disabled
        if (!MandrillHelper::getLoggingEnabled()) {
            return;
        }

        $subject = $message->getSubject();
        $body = $message->getBody();
        $contentType = $this->getMessagePrimaryContentType($message);

        $logContent = $body;

        // Append some extra information at the end
        $logContent .= '<hr><pre>Debug infos:' . "\n\n";
        $logContent .= 'To : ' . print_r($message->getTo(), true) . "\n";
        $logContent .= 'Subject : ' . $subject . "\n";
        $logContent .= 'From : ' . print_r($message->getFrom(), true) . "\n";
        $logContent .= 'Headers:' . "\n";
        /** @var Swift_Mime_Header $header */
        foreach ($message->getHeaders()->getAll() as $header) {
            $logContent .= '  ' . $header->getFieldName() . ': ' . $header->getFieldBody() . "\n";
        }
        if (!empty($params['recipients'])) {
            $logContent .= 'Recipients : ' . print_r($message->getTo(), true) . "\n";
        }
        $logContent .= 'Results:' . "\n";
        $logContent .= print_r($results, true) . "\n";
        $logContent .= '</pre>';

        // Generate filename
        $filter = new FileNameFilter();
        $title = substr($filter->filter($subject), 0, 35);
        $logName = date('Ymd_His') . '_' . $title;

        // Store attachments if any
        $attachments = $message->getChildren();
        if (!empty($attachments)) {
            $logContent .= '<hr />';
            foreach ($attachments as $attachment) {
                if ($attachment instanceof Swift_Attachment) {
                    $attachmentDestination = $logFolder . '/' . $logName . '_' . $attachment->getFilename();
                    file_put_contents($attachmentDestination, $attachment->getBody());
                    $logContent .= 'File : <a href="' . $attachmentDestination . '">' . $attachment->getFilename() . '</a><br/>';
                }
            }
        }

        // Store it
        $ext = ($contentType == 'text/html') ? 'html' : 'txt';
        $r = file_put_contents($logFolder . '/' . $logName . '.' . $ext, $logContent);

        if (!$r && Director::isDev()) {
            throw new Exception('Failed to store email in ' . $logFolder);
        }
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return Injector::inst()->get(LoggerInterface::class)->withName('Mandrill');
    }

    /**
     * @param Swift_Events_EventListener $plugin
     */
    public function registerPlugin(Swift_Events_EventListener $plugin)
    {
        $this->eventDispatcher->bindEventListener($plugin);
    }

    /**
     * @return array
     */
    protected function getSupportedContentTypes()
    {
        return array(
            'text/plain',
            'text/html'
        );
    }

    /**
     * @param string $contentType
     * @return bool
     */
    protected function supportsContentType($contentType)
    {
        return in_array($contentType, $this->getSupportedContentTypes());
    }

    /**
     * @param Swift_Mime_Message $message
     * @return string
     * @throws ReflectionException
     */
    protected function getMessagePrimaryContentType(Swift_Mime_Message $message)
    {
        $contentType = $message->getContentType();

        if ($this->supportsContentType($contentType)) {
            return $contentType;
        }

        // SwiftMailer hides the content type set in the constructor of Swift_Mime_Message as soon
        // as you add another part to the message. We need to access the protected property
        // _userContentType to get the original type.
        $messageRef = new ReflectionClass($message);
        if ($messageRef->hasProperty('_userContentType')) {
            $propRef = $messageRef->getProperty('_userContentType');
            $propRef->setAccessible(true);
            $contentType = $propRef->getValue($message);
        }

        return $contentType;
    }

    /**
     * https://mandrillapp.com/api/docs/messages.php.html#method-send
     *
     * @param Swift_Mime_Message $message
     * @return array Mandrill Send Message
     * @throws ReflectionException
     */
    public function getMandrillMessage(Swift_Mime_Message $message)
    {
        $contentType = $this->getMessagePrimaryContentType($message);
        $fromAddresses = $message->getFrom();
        $fromEmails = array_keys($fromAddresses);
        $toAddresses = $message->getTo();
        $ccAddresses = $message->getCc() ? $message->getCc() : [];
        $bccAddresses = $message->getBcc() ? $message->getBcc() : [];
        $replyToAddresses = $message->getReplyTo() ? $message->getReplyTo() : [];
        $to = array();
        $attachments = array();
        $images = array();
        $headers = array();
        $tags = array();
        foreach ($toAddresses as $toEmail => $toName) {
            $to[] = array(
                'email' => $toEmail,
                'name' => $toName,
                'type' => 'to'
            );
        }
        foreach ($replyToAddresses as $replyToEmail => $replyToName) {
            if ($replyToName) {
                $headers['Reply-To'] = sprintf('%s <%s>', $replyToEmail, $replyToName);
            } else {
                $headers['Reply-To'] = $replyToEmail;
            }
        }
        foreach ($ccAddresses as $ccEmail => $ccName) {
            $to[] = array(
                'email' => $ccEmail,
                'name' => $ccName,
                'type' => 'cc'
            );
        }
        foreach ($bccAddresses as $bccEmail => $bccName) {
            $to[] = array(
                'email' => $bccEmail,
                'name' => $bccName,
                'type' => 'bcc'
            );
        }
        $bodyHtml = $bodyText = null;
        if ($contentType === 'text/plain') {
            $bodyText = $message->getBody();
        } elseif ($contentType === 'text/html') {
            $bodyHtml = $message->getBody();
        } else {
            $bodyHtml = $message->getBody();
        }
        foreach ($message->getChildren() as $child) {
            if ($child instanceof \Swift_Image) {
                $images[] = array(
                    'type' => $child->getContentType(),
                    'name' => $child->getId(),
                    'content' => base64_encode($child->getBody()),
                );
            } elseif ($child instanceof Swift_Attachment && !($child instanceof \Swift_Image)) {
                $attachments[] = array(
                    'type' => $child->getContentType(),
                    'name' => $child->getFilename(),
                    'content' => base64_encode($child->getBody())
                );
            } elseif ($child instanceof Swift_MimePart && $this->supportsContentType($child->getContentType())) {
                if ($child->getContentType() == "text/html") {
                    $bodyHtml = $child->getBody();
                } elseif ($child->getContentType() == "text/plain") {
                    $bodyText = $child->getBody();
                }
            }
        }
        $mandrillMessage = array(
            'html' => $bodyHtml,
            'text' => $bodyText,
            'subject' => $message->getSubject(),
            'from_email' => $fromEmails[0],
            'from_name' => $fromAddresses[$fromEmails[0]],
            'to' => $to,
            'headers' => $headers,
            'tags' => $tags,
            'inline_css' => null
        );
        if (count($attachments) > 0) {
            $mandrillMessage['attachments'] = $attachments;
        }
        if (count($images) > 0) {
            $mandrillMessage['images'] = $images;
        }
        /** @var Swift_Mime_Header|Swift_Mime_Headers_OpenDKIMHeader|Swift_Mime_Headers_UnstructuredHeader $header */
        foreach ($message->getHeaders()->getAll() as $header) {
            if ($header->getFieldType() === \Swift_Mime_Header::TYPE_TEXT) {
                switch ($header->getFieldName()) {
                    case 'List-Unsubscribe':
                        $headers['List-Unsubscribe'] = $header->getValue();
                        $mandrillMessage['headers'] = $headers;
                        break;
                    case 'X-MC-InlineCSS':
                        $mandrillMessage['inline_css'] = $header->getValue();
                        break;
                    case 'X-MC-Tags':
                        $tags = $header->getValue();
                        if (!is_array($tags)) {
                            $tags = explode(',', $tags);
                        }
                        $mandrillMessage['tags'] = $tags;
                        break;
                    case 'X-MC-Autotext':
                        $autoText = $header->getValue();
                        if (in_array($autoText, array('true', 'on', 'yes', 'y', true), true)) {
                            $mandrillMessage['auto_text'] = true;
                        }
                        if (in_array($autoText, array('false', 'off', 'no', 'n', false), true)) {
                            $mandrillMessage['auto_text'] = false;
                        }
                        break;
                    case 'X-MC-GoogleAnalytics':
                        $analyticsDomains = explode(',', $header->getValue());
                        if (is_array($analyticsDomains)) {
                            $mandrillMessage['google_analytics_domains'] = $analyticsDomains;
                        }
                        break;
                    case 'X-MC-GoogleAnalyticsCampaign':
                        $mandrillMessage['google_analytics_campaign'] = $header->getValue();
                        break;
                    default:
                        if (strncmp($header->getFieldName(), 'X-', 2) === 0) {
                            $headers[$header->getFieldName()] = $header->getValue();
                            $mandrillMessage['headers'] = $headers;
                        }
                        break;
                }
            }
        }
        // if ($this->getSubaccount()) {
        //     $mandrillMessage['subaccount'] = $this->getSubaccount();
        // }
        return $mandrillMessage;
    }

    /**
     * @return null|array
     */
    public function getResultApi()
    {
        return $this->resultApi;
    }
}
