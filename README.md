Silverstripe Mandrill module
==================
Use Mandrill in Silverstripe

Define in your \_ss\_environment.php file the following constant

	define('MANDRILL\_API\_KEY','YOUR_API_KEY_HERE');

You can also manually initialize the module by calling the following method in \_config.php

	MandrillMailer::setAsMailer(MANDRILL_API_KEY);

This module uses the official php sdk version 1.0.54 with a few tweaks.

You can also autoconfigure the module with the following constants in your _ss_environment.php

	define('MANDRILL_ENABLE_LOGGING',true); // Will log emails in the temp folders
	define('MANDRILL_SENDING_DISABLED',true); // Will disable sending (useful in development)

Mandrillapp integration
==================

This module create a new admin section that allows you to see results from
your api calls right from the Silverstripe CMS without having to log into
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

Site Config extensions
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

Although Mandrill provides an API for email templates, it is mainly useful to save
bandwith for your main email template (in our case, our BasicEmail.ss file).

But this doesn't help you to create User editable emails, like Confirmation emails, etc.
And then, there is also the problem of the variables that should be merged on the email (like $CurrentMember.FirstName).

A basic solution is to simply create the HTML on your page and create your email from that content, but it doesn't
provide you with one central place to manage all your email content.

This is why this module comes with one easy to use EmailTemplateAdmin based on ModelAdmin.

To help you migrate from existing setups, you have an ImportEmailTask thats imports all *.ss templates in the /email folder that 
end with Email in the name, like /email/myTestEmail.ss. 
The content is imported in the "Content" area, except if you specify ids for specific zones, like <div id="SideBar">My side bar content</div>

NOTE: email templates could be split in a separate module in the near future once I've
determined if it's possible to make it standalone.

Basic how-to guide
==================

After installing through your method of choice and setting up the API keys you have access to the MandrillEmail class. 

Lets say we want to send an email on form submission, the Silverstripe guide on forms is [here](https://docs.silverstripe.org/en/3.1/developer_guides/forms/introduction/) if you are unsure about forms.

We want a user to input some data and then send an email notifying us that a form was submitted. After handeling our other form requirements like saving to the DB
etc we would then want to send the email. 

```php
// Send an email using mandrill
// The recipient, cc and bcc emails can be arrays of email addresses to include.
// The 'Bounce' is the Silverstripe URL for handeling bounced emails
$email = new MandrillEmail('from@outwebsite.com', 'recipient@email.com', 'Our Subject', 'The body of the email', 'BounceURL', 'AnyCCEmails@email.com', 'AnyBCCEmails@email.com');
// Here we can set a template to use. This could be a custom email template you design or one of the included templates. 
$email->setTemplate('BoilerplateEmail');
$email->send();
```

The other option for setting a template for your email is to use the built in template builder. First you define the email template through the 'Emails' tab in the CMS. We can select a base template to use and then define the layout of the email body. We should make a note of the 'code' for the email template once we have created it. 

Within the content area we have access to the currently logged in user, the site config options and the basic information passed through such as to, from, subject etc. This is really handy when your client might need to make small changes to the emails sent out.  

To create an email using this process within out form we could use the following code. 

```php
// Send an email using the templating engine
$email = EmailTemplate::getEmailByCode('template-code');
$email->setToMember('to@email.com');
$email->send();
```

Compatibility
==================
Tested with 3.1

Maintainer
==================
LeKoala - thomas@lekoala.be