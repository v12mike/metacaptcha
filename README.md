# metacaptcha
A CAPTCHA plugin for phpBB which enables multiple other CAPTCHA plugins to be simultaneously active

When a user tries to register (or perform any CAPTCHA-protected action) he is presented with a CAPTCHA and when that has been solved he is presented with a second different CAPTCHA (and potentially more after that).

This extension does not add a new user-facing CAPTCHA, but just changes the way that existing CAPTCHAs (built-in or extensions) are used.

Meta-CAPTCHA is believed to be functionally compatible with all existing phpBB CAPTCHA plugins (built-in or extension).  

# Installation and configuration

Meta-CAPTCHA is installed in the same way as other extensions by unzipping the file tree into the directory ext/v12mike/metacaptcha/ the extension is enabled in the normal manner in the ACP CUSTOMISE tab.

Configuration is done through the ACP 'GENERAL' tab ->'Spambot Countermeasures', where it is selected and configured as if it was a CAPTCHA.

On the Meta-CAPTCHA configuration page is a list of configured CAPTCHAs and a list of available CAPTCHAs.  CAPTCHAs can be moved from the available to the configured list to make them active.

In the table of configured CAPTCHAs, each CAPTCHA has a priority, CAPTCHAs with the highest priority (lowest numeric value) are presented to users first.  If two configured CAPTCHAs have the same priority value, then the order of presentation is chosen randomly for each user.
