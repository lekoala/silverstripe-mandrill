<?php

namespace LeKoala\Mandrill\Test;

use LeKoala\Mandrill\MandrillApiTransport;
use SilverStripe\Core\Environment;
use SilverStripe\Dev\SapphireTest;
use LeKoala\Mandrill\MandrillHelper;
use Mandrill;
use SilverStripe\Control\Email\Email;
use SilverStripe\Core\Injector\Injector;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;

/**
 * Test for Mandrill
 *
 * @group Mandrill
 */
class MandrillTest extends SapphireTest
{
    protected $testMailer;
    protected $isDummy = false;

    protected function setUp(): void
    {
        parent::setUp();

        // add dummy api key
        if (!MandrillHelper::getAPIKey()) {
            $this->isDummy = true;
            Environment::setEnv('MANDRILL_API_KEY', 'dummy');
        }

        $this->testMailer = Injector::inst()->get(MailerInterface::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        Injector::inst()->registerService($this->testMailer, MailerInterface::class);
    }

    public function testSetup()
    {
        $inst = MandrillHelper::registerTransport();
        $mailer = MandrillHelper::getMailer();
        $instClass = get_class($inst);
        $instMailer = get_class($mailer);
        $this->assertEquals($instClass, $instMailer);
    }

    public function testSendAllTo(): void
    {
        $sendAllTo = Environment::getEnv('SS_SEND_ALL_EMAILS_TO');

        $mailer = MandrillHelper::registerTransport();

        $email = new Email();
        $email->setSubject('Test email');
        $email->setBody("Body of my email");
        $email->getHeaders()->addTextHeader('X-SendingDisabled', "true");
        $email->setTo("sendfrom@test.local");

        // This is async, therefore it does not return anything anymore
        $email->send();

        $transport = MandrillHelper::getTransportFromMailer($mailer);
        $result = $transport->getApiResult()[0];

        // if we have a send all to, it should match
        $realRecipient = $sendAllTo ? $sendAllTo : "sendfrom@test.local";
        $this->assertEquals($realRecipient, $result["email"]);

        Environment::setEnv("SS_SEND_ALL_EMAILS_TO", "sendall@test.local");

        $email->send();
        $result = $transport->getApiResult()[0];

        $this->assertEquals("sendall@test.local", $result["email"]);

        // reset env
        Environment::setEnv("SS_SEND_ALL_EMAILS_TO", $sendAllTo);
    }

    public function testSending()
    {
        $test_to = Environment::getEnv('MANDRILL_TEST_TO');
        $test_from = Environment::getEnv('MANDRILL_TEST_FROM');

        $mailer = MandrillHelper::registerTransport();

        $email = new Email();
        $email->setSubject('Test email');
        $email->setBody("Body of my email");

        if (!$test_from || !$test_to || $this->isDummy) {
            $test_to = "example@localhost";
            $test_from =  "sender@localhost";
            // don't try to send it for real
            $email->getHeaders()->addTextHeader('X-SendingDisabled', "true");
        }
        $email->setTo($test_to);
        $email->setFrom($test_from);

        // This is async, therefore it does not return anything anymore
        $email->send();

        $transport = MandrillHelper::getTransportFromMailer($mailer);
        $result = $transport->getApiResult();

        $firstMail = $result[0] ?? [];

        $this->assertEquals($test_to, $firstMail['email']);
        $this->assertEquals("sent", $firstMail['status']);
    }
}
