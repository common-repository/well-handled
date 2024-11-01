## Actions ##

The following action hooks are available:

### wh_mail_error

This action is triggered when `wh_mail()` or `wh_mail_template()` fails to send a message. Common reasons for failure include invalid/missing recipient information, authentication issues with the sending server, or template parsing errors.

```
<?php
add_action('wh_mail_error', 'my_callback_function', 10, 3);
```

#### Parameters
 * (array) **$mail**: an array containing the following mail-related fields:
  * (string|array) **'to'**: the e-mail recipient(s)
  * (string) **'subject'**: the e-mail subject
  * (string|array) **'headers'**: additional headers
  * (bool) **'testmode'**: whether or not this message would have been logged
 * (array) **$template**:
  * (float) **'compilation_time'**: the time spent compiling the template
  * (string) **'template_slug'**: the template used
  * (array) **'template_data'**: the data to be processed by the template
  * (array) **'template_options'**: additional options passed to `wh_mail()` or `wh_mail_template()`
 * (WP_Error) **$error**: a [WP_Error](https://codex.wordpress.org/Class_Reference/WP_Error) object containing details about the failure, if any


## Filters ##

The following filter hooks are available:

### wh_preprocess_template

This filter hook modifies the HTML content returned by `wh_get_template()`.  It is applied before any {{handlebar}} handling.

```
<?php
add_filter('wh_preprocess_template', 'my_early_callback_function', 10, 2);
```

#### Parameters
 * (string) **$content**: the raw template content
 * (string) **$template_slug**: the unique slug used by the template


### wh_postprocess_template

This filter hook modifies the HTML content returned by `wh_get_template()`.  It is applied after all function operations have completed.

```
<?php
add_filter('wh_postprocess_template', 'my_late_callback_function', 10, 2);
```

#### Parameters
 * (string) **$content**: the (less) raw template content
 * (string) **$template_slug**: the unique slug used by the template


### wh_mail_to

This filter hook is applied to the `$to` variable passed to `wh_mail()` or `wh_mail_template()`.  Refer to the documentation for [`wp_mail()`](https://developer.wordpress.org/reference/functions/wp_mail/) for more information about the formatting of the various `wh_mail_*` filters.

```
<?php
add_filter('wh_mail_to', 'my_filter_callback_function', 10, 1);
```

#### Parameters
 * (string|array) **'to'**: the e-mail recipient(s).


### wh_mail_subject

This filter hook is applied to the `$subject` variable passed to `wh_mail()` or `wh_mail_template()`.

```
<?php
add_filter('wh_mail_subject', 'my_filter_callback_function', 10, 1);
```

#### Parameters
 * (string) **'subject'**: the e-mail subject.


### wh_mail_message

This filter hook is applied to the `$message` variable passed to `wh_mail()` or the processed message created by `wh_mail_template()`.

```
<?php
add_filter('wh_mail_message', 'my_filter_callback_function', 10, 1);
```

#### Parameters
 * (string) **'message'**: the e-mail message.


### wh_mail_headers

This filter hook is applied to the `$headers` variable passed to `wh_mail()` or `wh_mail_template()`.

```
<?php
add_filter('wh_mail_headers', 'my_filter_callback_function', 10, 1);
```

#### Parameters
 * (string|array) **'headers'**: the e-mail headers.


### wh_mail_attachments

This filter hook is applied to the `$attachments` variable passed to `wh_mail()` or `wh_mail_template()`.

```
<?php
add_filter('wh_mail_attachments', 'my_filter_callback_function', 10, 1);
```

#### Parameters
 * (string|array) **'attachments'**: the e-mail attachments.

### wh_mail_from_name

This filter can be used to dynamically set the "from" name when sending messages through SMTP, Amazon SES, or Mandrill.

```
<?php
add_filter('wh_mail_from_name', 'my_filter_callback_function', 10, 2);
```

#### Parameters
* (string) **'from_name'**: the from name.
* (string) **'method'**: the send method, one of `"mandrill"`, `"ses"`, or `"smtp"`.

### wh_mail_recipient_name

Due to the transactional nature of Well-Handled's emails, the recipients in the `$to` variable passed to `wh_mail()` or `wh_mail_template()` are teased apart.  The function further separates a recipient's name from his/her email address.  This filter is applied to a single recipient name.

```
<?php
add_filter('wh_mail_recipient_name', 'my_filter_callback_function', 10, 1);
```

#### Parameters
 * (string) **'recipient_name'**: the recipient name.


### wh_mail_recipient_email

And this filter is applied to a single recipient's email address, derived from the `$to` variable passed to `wh_mail()` or `wh_mail_template()`.  You can return `FALSE` to prevent a message from being sent to this person.

```
<?php
add_filter('wh_mail_recipient_email', 'my_filter_callback_function', 10, 1);
```

#### Parameters
 * (string) **'recipient_email'**: the recipient email.


## Shortcodes ##

Well-Handled templates include full support for WordPress shortcodes. Shortcode processing takes place before anything else, so any mail-specific filters and operations being applied to the HTML will be applied to the whole HTML.

The plugin also comes with its own handy shortcodes:

### wh-fragment

This powerful shortcode allows you to embed the HTML from other WH templates. This allows you to, for example, share a common header and footer or other elements without having to duplicate a ton of code.

```
<html>
[wh-fragment template="your-template-slug" /]
```

#### Parameters
 * (string) **'template'**: the fragment's template slug. To A/B test, separate slugs with a comma, e.g. `template="template-one, template-two"`.
