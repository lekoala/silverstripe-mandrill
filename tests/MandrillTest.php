<?php
namespace LeKoala\Mandrill\Test;

use LeKoala\Mandrill\MandrillHelper;
use SilverStripe\Control\Email\Email;
use SilverStripe\Control\Email\Mailer;
use SilverStripe\Control\Email\SwiftMailer;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;

/**
 * Test for Mandrill
 *
 * @group Mandrill
 */
class MandrillTest extends SapphireTest
{
    protected $testMailer;

    protected function setUp()
    {
        parent::setUp();

        $this->testMailer = Injector::inst()->get(Mailer::class);

        // Ensure we have the right mailer
        $mailer = new SwiftMailer();
        $swiftMailer = new \Swift_Mailer(new \Swift_MailTransport());
        $mailer->setSwiftMailer($swiftMailer);
        Injector::inst()->registerService($mailer, Mailer::class);
    }
    protected function tearDown()
    {
        parent::tearDown();

        Injector::inst()->registerService($this->testMailer, Mailer::class);
    }

    public function testSetup()
    {
        $inst = MandrillHelper::registerTransport();
        $mailer = MandrillHelper::getMailer();
        $this->assertTrue($inst === $mailer);
    }

    public function testSending()
    {
        $test_to = Environment::getEnv('MANDRILL_TEST_TO');
        $test_from = Environment::getEnv('MANDRILL_TEST_FROM');
        if (!$test_from || !$test_to) {
            $this->markTestIncomplete("You must define tests environement variable: MANDRILL_TEST_TO, MANDRILL_TEST_FROM");
        }

        MandrillHelper::registerTransport();

        $email = new Email();
        $email->setTo($test_to);
        $email->setSubject('Test email');
        $email->setBody("Body of my email");
        $email->setFrom($test_from);
        $sent = $email->send();

        $this->assertTrue(!!$sent);
    }
}
