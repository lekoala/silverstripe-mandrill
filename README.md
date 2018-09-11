SilverStripe Mandrill module
==================
[![Build Status](https://travis-ci.org/lekoala/silverstripe-mandrill.svg?branch=master)](https://travis-ci.org/lekoala/silverstripe-mandrill)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/lekoala/silverstripe-mandrill/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/lekoala/silverstripe-mandrill/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/lekoala/silverstripe-mandrill/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/lekoala/silverstripe-mandrill/?branch=master)
[![Build Status](https://scrutinizer-ci.com/g/lekoala/silverstripe-mandrill/badges/build.png?b=master)](https://scrutinizer-ci.com/g/lekoala/silverstripe-mandrill/build-status/master)
[![codecov.io](https://codecov.io/github/lekoala/silverstripe-mandrill/coverage.svg?branch=master)](https://codecov.io/github/lekoala/silverstripe-mandrill?branch=master)

[![Latest Stable Version](https://poser.pugx.org/lekoala/silverstripe-mandrill/version)](https://packagist.org/packages/lekoala/silverstripe-mandrill)
[![Latest Unstable Version](https://poser.pugx.org/lekoala/silverstripe-mandrill/v/unstable)](//packagist.org/packages/lekoala/silverstripe-mandrill)
[![Total Downloads](https://poser.pugx.org/lekoala/silverstripe-mandrill/downloads)](https://packagist.org/packages/lekoala/silverstripe-mandrill)
[![License](https://poser.pugx.org/lekoala/silverstripe-mandrill/license)](https://packagist.org/packages/lekoala/silverstripe-mandrill)
[![Monthly Downloads](https://poser.pugx.org/lekoala/silverstripe-mandrill/d/monthly)](https://packagist.org/packages/lekoala/silverstripe-mandrill)
[![Daily Downloads](https://poser.pugx.org/lekoala/silverstripe-mandrill/d/daily)](https://packagist.org/packages/lekoala/silverstripe-mandrill)

[![Dependency Status](https://www.versioneye.com/php/lekoala:silverstripe-mandrill/badge.svg)](https://www.versioneye.com/php/lekoala:silverstripe-mandrill)
[![Reference Status](https://www.versioneye.com/php/lekoala:silverstripe-mandrill/reference_badge.svg?style=flat)](https://www.versioneye.com/php/lekoala:silverstripe-mandrill/references)

![codecov.io](https://codecov.io/github/lekoala/silverstripe-mandrill/branch.svg?branch=master)

Use Mandrill in SilverStripe

Define in your .env file the following constant

	MANDRILL_API_KEY='YOUR_API_KEY_HERE'

or by defining the api key in your config.yml

   ```yaml
   LeKoala\Mandrill\MandrillHelper:
     mandrill_api_key: 'key3goes9here'
   ```

This module uses the official php sdk version 1.0.54 with a few tweaks.

You can also autoconfigure the module with the following constants in your .env file

    # Will log emails in the temp folders
	MANDRILL_ENABLE_LOGGING=true
    # Will disable sending (useful in development)
	MANDRILL_SENDING_DISABLED=true
	# Set app domain explicitly
	MANDRILL_DOMAIN="mysite.co.nz"
	# Also recommended to specify an explicit from
	SS_SEND_ALL_EMAILS_FROM="noreply@mysite.co.nz"

By defining the Api Key, the module will register a new mailer that will be used to send all emails.

Integration
==================

This module create a new admin section that allows you to see results from
your api calls right from the SilverStripe CMS without having to log into
mandrillapp.com

Webhooks
==================

From the Mandrill Admin, you can setup a webhook for your website. This webhook
will be called and MandrillController will take care of handling all events
for you.

By default, MandrillController will do nothing. Feel free to add your own
extensions to MandrillController to define your own rules, like "Send an
email to the admin when we receive a spam complaint".

MandrillController provides 4 extensions points:
- updateHandleAnyEvent
- updateHandleSyncEvent
- updateHandleInboundEvent
- updateHandleMessageEvent

Compatibility
==================
Tested with SilverStripe 4.1+

Maintainer
==================
LeKoala - thomas@lekoala.be
