<?php

namespace LeKoala\Mandrill\Test;

use LeKoala\Mandrill\MandrillApiTransport;
use SilverStripe\Core\Environment;
use SilverStripe\Dev\SapphireTest;
use LeKoala\Mandrill\MandrillHelper;
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->testMailer = Injector::inst()->get(MailerInterface::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        Injector::inst()->registerService($this->testMailer, MailerInterface::class);
    }

    public function testSetup()
    {
        if (!MandrillHelper::getApiKey()) {
            return $this->markTestIncomplete("No api key set for test");
        }

        $inst = MandrillHelper::registerTransport();
        $mailer = MandrillHelper::getMailer();
        $instClass = get_class($inst);
        $instMailer = get_class($mailer);
        $this->assertEquals($instClass, $instMailer);
    }

    public function testSending()
    {
        $test_to = Environment::getEnv('MANDRILL_TEST_TO');
        $test_from = Environment::getEnv('MANDRILL_TEST_FROM');

        $mailer = MandrillHelper::registerTransport();

        $email = new Email();
        $email->setSubject('Test email');
        $email->setBody("Body of my email");

        if (!$test_from || !$test_to) {
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
