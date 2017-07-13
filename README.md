SilverStripe Mandrill module
==================
Use Mandrill in SilverStripe

Define in your _ss_environment.php file the following constant

    ```php
	define('MANDRILL_API_KEY','YOUR_API_KEY_HERE');
    ```

or by defining the api key in your config.yml

   ```yaml
   MandrillMailer:
     mandrill_api_key: 'key3goes9here'
   ```

This module uses the official php sdk version 1.0.54 with a few tweaks.

You can also autoconfigure the module with the following constants in your _ss_environment.php

	define('MANDRILL_ENABLE_LOGGING',true); // Will log emails in the temp folders
	define('MANDRILL_SENDING_DISABLED',true); // Will disable sending (useful in development)

By defining the Api Key, the module will register a new mailer that will be used to send all emails.

Switching to SparkPost
==================

For those who want to keep a free email solution, I recommend from now on to use
SparkPost. I created a [new module] (https://github.com/lekoala/silverstripe-sparkpost)
for this.

If needed, you can install SparkPost alongside this module.

Mandrillapp integration
==================

This module create a new admin section that allows you to see results from
your api calls right from the SilverStripe CMS without having to log into
mandrillapp.com


New extended email class
==================

This module comes with a new MandrillEmail class that extends the base Email class.

It comes with the following features:
- Possibility to define a base template that will wrap the html content
- The base template contains a few elements that are themable and/or configurable (logo, colors, sections...)
- Send email according to Member locale
- Rewrite urls in a safe fashion (no errors on empty links)

Emails templates use the Ink css framework.

SiteConfig extension
==================

Most of the time, it is very useful to let the user configure himself the default
from and to address for their website.

This is why this module comes with a SiteConfig extension that you can
apply on your SiteConfig with the following yml config:

	SiteConfig:
	  extensions:
		- MandrillSiteConfig

Also, it might be useful to set the email theme, logo and footer for your emails.
All this is setupable through the CMS in the Settings section.

Webhooks
==================

From the Mandrill Admin, you can setup a webhook for your website. This webhook
will be called and MandrillController will take care of handling all events
for you.

By default, MandrillController will do nothing. Feel free to add your own
extensions to MandrillController to define your own rules, like "Send an
email to the admin when a receive a spam complaint".

MandrillController provides 4 extensions points:
- updateHandleAnyEvent
- updateHandleSyncEvent
- updateHandleInboundEvent
- updateHandleMessageEvent

Emails templates
==================

Email templates have been split to a [standalone module](https://github.com/lekoala/silverstripe-email-templates).

Basic how-to guide
==================

After installing through your method of choice and setting up the API keys you have access to the MandrillEmail class.

Please note that even if the module provides the MandrillEmail class, you don't have to use it. The regular Email class will work
just as well because we have registered a new mailer (MandrillMailer).

Lets say we want to send an email on form submission, the Silverstripe guide on forms is [here](https://docs.silverstripe.org/en/3.1/developer_guides/forms/introduction/) if you are unsure about forms.

We want a user to input some data and then send an email notifying us that a form was submitted. After handeling our other form requirements like saving to the DB
etc we would then want to send the email.

```php
// Send an email using mandrill
// The recipient, cc and bcc emails can be arrays of email addresses to include.
// The 'Bounce' is the Silverstripe URL for handeling bounced emails
$email = new MandrillEmail('from@outwebsite.com', 'recipient@email.com', 'Our Subject', 'The body of the email', 'BounceURL', 'AnyCCEmails@email.com', 'AnyBCCEmails@email.com');
$email->send();
```

Compatibility
==================
Tested with 3.1

Maintainer
==================
LeKoala - thomas@lekoala.be