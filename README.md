Silverstripe Mandrill module
==================
Use Mandrill in Silverstripe

Define in your \_ss\_environment.php file the following constant

define('MANDRILL\_API\_KEY','YOUR_API_KEY_HERE');

This module uses the official php sdk version 1.0.54 with a few tweaks

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

Site Config extensions
==================

Most of the time, it is very useful to let the user configure himself the default
from and to address for their website.

This is why this module comes with a SiteConfig extension that you can
apply on your SiteConfig with the following yml config:

	SiteConfig:
	  extensions: 
		- MandrillSiteConfig

Compatibility
==================
Tested with 3.1

Maintainer
==================
LeKoala - thomas@lekoala.be