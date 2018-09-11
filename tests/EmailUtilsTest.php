<?php

namespace LeKoala\Mandrill\Test;

use LeKoala\Mandrill\EmailUtils;
use SilverStripe\Dev\SapphireTest;

/**
 * Test for EmailUtils
 *
 * Copied from sparkpost
 */
class EmailUtilsTest extends SapphireTest
{

    /**
     * @dataProvider providerTestDisplayName
     * @param string $email
     * @param string $name
     */
    public function testDisplayName($email, $name)
    {
        $displayName = EmailUtils::get_displayname_from_rfc_email($email);
        $this->assertEquals($name, $displayName);
    }

    /**
     * @return array
     */
    public function providerTestDisplayName()
    {
        return [
            // Standard emails
            ["me@test.com", "me"],
            ["mobius@test.com", "mobius"],
            ["test_with-chars.in.it@test-ds.com.xyz", "test_with-chars.in.it"],
            // Rfc emails
            ["Me <me@test.com>", "Me"],
            ["Möbius <mobius@test.com>", "Möbius"],
            ["John Smith <test_with-chars.in.it@test-ds.com.xyz>", "John Smith"],

        ];
    }

    /**
     * @dataProvider providerTestGetEmail
     * @param string $input
     * @param string $innerEmail
     */
    public function testGetEmail($input, $innerEmail)
    {
        $email = EmailUtils::get_email_from_rfc_email($input);
        $this->assertEquals($innerEmail, $email);
    }

    /**
     * @return array
     */
    public function providerTestGetEmail()
    {
        return [
            // Standard emails
            ["me@test.com", "me@test.com"],
            ["mobius@test.com", "mobius@test.com"],
            ["test_with-chars.in.it@test-ds.com.xyz", "test_with-chars.in.it@test-ds.com.xyz"],
            // Rfc emails
            ["Me <me@test.com>", "me@test.com"],
            ["Möbius <mobius@test.com>", "mobius@test.com"],
            ["John Smith <test_with-chars.in.it@test-ds.com.xyz>", "test_with-chars.in.it@test-ds.com.xyz"],

        ];
    }

    public function testConvertHtmlToText()
    {
        $someHtml = '   Some<br/>Text <a href="http://test.com">Link</a> <strong>End</strong>    ';

        $textResult = "Some\nText Link (http://test.com) *End*";

        $process = EmailUtils::convert_html_to_text($someHtml);

        $this->assertEquals($textResult, $process);
    }
}
