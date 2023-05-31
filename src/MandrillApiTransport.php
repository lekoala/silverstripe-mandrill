<?php

namespace LeKoala\Mandrill;

use Mandrill;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Email;
use SilverStripe\Control\Director;
use Symfony\Component\Mailer\Envelope;
use SilverStripe\Assets\FileNameFilter;
use Symfony\Component\HttpClient\Response\CurlResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Component\Mime\Header\UnstructuredHeader;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * We create our own class
 * We cannot extend easily due to private methods
 *
 * @link https://www.Mandrill.com/api#/reference/introduction
 * @link https://github.com/symfony/symfony/blob/6.3/src/Symfony/Component/Mailer/Bridge/Mailchimp/Transport/MandrillApiTransport.php
 * @author LeKoala <thomas@lekoala.be>
 */
class MandrillApiTransport extends AbstractApiTransport
{
    private const HOST = 'mandrillapp.com';

    /**
     * @var Mandrill
     */
    private $apiClient;

    private $apiResult;

    public function __construct(Mandrill $apiClient, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null, LoggerInterface $logger = null)
    {
        $this->apiClient = $apiClient;

        parent::__construct($client, $dispatcher, $logger);
    }

    public function __toString(): string
    {
        return sprintf('mandrill+api://%s', $this->getEndpoint());
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $disableSending = $email->getHeaders()->has('X-SendingDisabled') || !MandrillHelper::getSendingEnabled();

        // We don't really care about the actual response
        $response = new MockResponse();
        if ($disableSending) {
            $result = [];
            foreach ($email->getTo() as $recipient) {
                $result[] = [
                    'email' => $recipient->toString(),
                    'status' => 'sent',
                    'reject_reason' => '',
                    '_id' => uniqid(),
                    'disabled' => true,
                ];
            }
        } else {
            $payload = $this->getPayload($email, $envelope);
            $result = $this->apiClient->messages->send($payload);

            $sendCount = 0;
            foreach ($result as $item) {
                if ($item['status'] === 'sent' || $item['status'] === 'queued') {
                    $sendCount++;
                }
            }
        }

        $this->apiResult = $result;

        $firstRecipient = reset($result);
        if ($firstRecipient) {
            $sentMessage->setMessageId($firstRecipient['_id']);
        }

        if (MandrillHelper::getLoggingEnabled()) {
            $this->logMessageContent($email, $result);
        }

        return $response;
    }

    public function getApiResult()
    {
        return $this->apiResult;
    }

    private function getEndpoint(): ?string
    {
        return ($this->host ?: self::HOST) . ($this->port ? ':' . $this->port : '');
    }

    private function getPayload(Email $email, Envelope $envelope): array
    {
        $message = array_merge(MandrillHelper::config()->default_params, [
            'html' => $email->getHtmlBody(),
            'text' => $email->getTextBody(),
            'subject' => $email->getSubject(),
            'from_email' => $envelope->getSender()->getAddress(),
            'to' => $this->getRecipients($email, $envelope),
        ]);

        if ('' !== $envelope->getSender()->getName()) {
            $message['from_name'] = $envelope->getSender()->getName();
        }

        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();
            $disposition = $headers->getHeaderBody('Content-Disposition');
            $att = [
                'content' => $attachment->bodyToString(),
                'type' => $headers->get('Content-Type')->getBody(),
            ];
            if ($name = $headers->getHeaderParameter('Content-Disposition', 'name')) {
                $att['name'] = $name;
            }
            if ('inline' === $disposition) {
                $message['images'][] = $att;
            } else {
                $message['attachments'][] = $att;
            }
        }

        $headersToBypass = ['from', 'to', 'cc', 'bcc', 'subject', 'content-type'];
        foreach ($email->getHeaders()->all() as $name => $header) {
            if (\in_array($name, $headersToBypass, true)) {
                continue;
            }
            if ($header instanceof TagHeader) {
                $message['tags'] = array_merge(
                    $message['tags'] ?? [],
                    explode(',', $header->getValue())
                );
                continue;
            }
            if ($header instanceof MetadataHeader) {
                $message['metadata'][$header->getKey()] = $header->getValue();
                continue;
            }
            $message['headers'][$header->getName()] = $header->getBodyAsString();
        }

        foreach ($email->getHeaders()->all() as $name => $header) {
            if (!($header instanceof UnstructuredHeader)) {
                continue;
            }
            $headerValue = $header->getValue();
            switch ($name) {
                case 'List-Unsubscribe':
                    $message['headers']['List-Unsubscribe'] = $headerValue;
                    break;
                case 'X-MC-InlineCSS':
                    $message['inline_css'] = $headerValue;
                    break;
                case 'X-MC-Tags':
                    $tags = $headerValue;
                    if (!is_array($tags)) {
                        $tags = explode(',', $tags);
                    }
                    $message['tags'] = $tags;
                    break;
                case 'X-MC-Autotext':
                    $autoText = $headerValue;
                    if (in_array($autoText, array('true', 'on', 'yes', 'y', true), true)) {
                        $message['auto_text'] = true;
                    }
                    if (in_array($autoText, array('false', 'off', 'no', 'n', false), true)) {
                        $message['auto_text'] = false;
                    }
                    break;
                case 'X-MC-GoogleAnalytics':
                    $analyticsDomains = explode(',', $headerValue);
                    if (is_array($analyticsDomains)) {
                        $message['google_analytics_domains'] = $analyticsDomains;
                    }
                    break;
                case 'X-MC-GoogleAnalyticsCampaign':
                    $message['google_analytics_campaign'] = $headerValue;
                    break;
                default:
                    if (strncmp($header->getName(), 'X-', 2) === 0) {
                        $message['headers'][$header->getName()] = $headerValue;
                    }
                    break;
            }
        }

        return $message;
    }

    protected function getRecipients(Email $email, Envelope $envelope): array
    {
        $recipients = [];
        foreach ($envelope->getRecipients() as $recipient) {
            $type = 'to';
            if (\in_array($recipient, $email->getBcc(), true)) {
                $type = 'bcc';
            } elseif (\in_array($recipient, $email->getCc(), true)) {
                $type = 'cc';
            }

            $recipientPayload = [
                'email' => $recipient->getAddress(),
                'type' => $type,
            ];

            if ('' !== $recipient->getName()) {
                $recipientPayload['name'] = $recipient->getName();
            }

            $recipients[] = $recipientPayload;
        }

        return $recipients;
    }

    /**
     * Log message content
     *
     * @param Email $message
     * @param array $results Results from the api
     * @throws Exception
     */
    protected function logMessageContent(Email $message, $results = [])
    {
        // Folder not set
        $logFolder = MandrillHelper::getLogFolder();
        if (!$logFolder) {
            return;
        }
        // Logging disabled
        if (!MandrillHelper::getLoggingEnabled()) {
            return;
        }

        $subject = $message->getSubject();
        $body = $message->getBody();
        $contentType = $message->getHtmlBody() !== null ? "text/html" : "text";

        $logContent = $body;

        // Append some extra information at the end
        $logContent .= '<hr><pre>Debug infos:' . "\n\n";
        $logContent .= 'To : ' . print_r($message->getTo(), true) . "\n";
        $logContent .= 'Subject : ' . $subject . "\n";
        $logContent .= 'From : ' . print_r($message->getFrom(), true) . "\n";
        $logContent .= 'Headers:' . "\n" . $message->getHeaders()->toString() . "\n";
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
        $attachments = $message->getAttachments();
        if (!empty($attachments)) {
            $logContent .= '<hr />';
            foreach ($attachments as $attachment) {
                $attachmentDestination = $logFolder . '/' . $logName . '_' . $attachment->getFilename();
                file_put_contents($attachmentDestination, $attachment->getBody());
                $logContent .= 'File : <a href="' . $attachmentDestination . '">' . $attachment->getFilename() . '</a><br/>';
            }
        }

        // Store it
        $ext = ($contentType == 'text/html') ? 'html' : 'txt';
        $r = file_put_contents($logFolder . '/' . $logName . '.' . $ext, $logContent);

        if (!$r && Director::isDev()) {
            throw new Exception('Failed to store email in ' . $logFolder);
        }
    }
}
