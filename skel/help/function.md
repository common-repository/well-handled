## Sending Emails

The following functions exist to make sending Well-Handled emails a breeze.


### wh_mail_template()

Process a template and email it!

```
<?php
$response = wh_mail_template($template_slug, $data, $options);
```

#### Parameters

 * (string|array) (required) **$template_slug**: the unique identifier for your template. A/B testing is baked in; just pass an array and the template will be randomly selected.
 * (array) (optional) **$data**: the data that should be used in `key=>value` format. If omitted or invalid, handlebar processing will be skipped (leaving {{these}} in place).
 * (array) (optional) **$options**: function parameters to override.

#### Options

The following template processing options are available.

 * (bool) **'collapse_whitespace'**: contiguous whitespace shall be collapsed to a single horizontal space. Default: `FALSE`
 * (bool) **'css_inline'**: CSS found in `<style>` tags shall be copied to qualifying elements via their `style` attributes. Default: `TRUE`
 * (bool) **'debug'**: append a comment to the end of the document with details about its compilation. Default: `WP_DEBUG`
 * (string) **'link_target'**: all `<a>` elements shall use this `target`. Default: `"_blank"`
 * (bool) **'linkify'**: convert plain-text URLs, email addresses, and phone numbers to HTML links. Default: `FALSE`
 * (bool) **'snippet'**: process as an HTML snippet rather than a full document. This will prevent DOCTYPE and `<html>` tags from being added. Default: `FALSE`
 * (bool) **'strip_comments'**: Comments shall be removed. Default `TRUE`
 * (bool) **'strip_style_tags'**: All `<style>` tags shall be removed from the document. With `css_inline` enabled, `<style>` tags are mostly just bloat. Default `TRUE`
 * (bool) **'utm'**: append Google Urchin tracking tags defined in **'utm_data'** to link URLs. Default `FALSE`
 * (array) **'utm_data'**: the UTM tracking data to append if **'utm'** is `TRUE`.  `NULL` or otherwise empty values are ignored. Default:
  * 'utm_campaign'=>`$template_slug`
  * 'utm_content'=>`NULL`
  * 'utm_medium'=>`"email"`
  * 'utm_source'=>`"transactional"`
  * 'utm_term'=>`NULL`
 * (bool) **'utm_local_only'**: UTM tracking tags shall only be added to links pointing to the local site. Default: `TRUE`
 * (bool) **'validate_html'**: Attempt to fix syntax errors and strip inappropriate content from the document, such as Javascript. It is strongly suggested this always be enabled. Default `TRUE`

The following options relate to the eventual email. They all work exactly like [`wp_mail()`](https://developer.wordpress.org/reference/functions/wp_mail/). The only `wp_mail()` argument that is not present is `$message`, as that is built automatically in this case.

 * (string|array) **'to'**: the e-mail recipient(s). The argument can be a string, an array, or a pretend array (comma-separated string). Recipients should follow [RFC 2822](http://www.faqs.org/rfcs/rfc2822.html) guidelines, e.g. `john@doe.com` or `John Doe <john@doe.com>`. Default: ``
 * (string) **'subject'**: the e-mail subject. Default: `[Your Site Name] Your Template Name`
 * (string|array) **'headers'**: additional headers, if any, to include with the email.  The argument can be a CRLF-separated string or an array. Default `NULL`
 * (string|array) **'attachments'**: file(s) to attach. The argument accepts full file path(s). Default `NULL`
 * (bool) **'testmode'**: for WH Pro users, a value of `TRUE` will prevent the message from being logged. This only applies if data collection is enabled via the Settings page. Default: `FALSE`


#### Response

  * (bool) **TRUE** if at least one recipient email was successfully sent. Note: this does indicate ultimate deliverability, just that the message was accepted by the sending server.
  * (bool) **FALSE** if the template could not be parsed or no valid recipients were included.


#### Example

```
<?php
$data = array('firstname'=>'Anne');
$options = array(
    'css_inline'=>true,
    'collapse_whitespace'=>true,
    'to'=>'anne@gmail.com'
);

if(false !== wh_mail_template('welcome-letter', $data, $options)) {
	echo 'Yay!';
}
else {
	echo 'Oops. Something went wrong.';
}
```

---

### wh_mail()

You can also use Well-Handled to send miscellaneous HTML emails or even template-derived messages that were precompiled. To make things easy, this function takes exactly the same arguments as [`wp_mail()`](https://developer.wordpress.org/reference/functions/wp_mail/), with one additional variable (`$testmode`) at the end for **Pro** users.  See the documentation for `wp_mail()` or `wh_mail_template()` for more details.

```
<?php
if(false !== wh_mail($to, $subject, $message, $headers=null, $attachments=null, $testmode=false)) {
    echo 'Yay!';
}
else {
    echo 'Oops. Something went wrong.';
}
```

---

### wh_recipient()

Take and email address and/or a name and smoosh it into a single "to" string that can be used as the recipient value when sending an email.

```
<?php
$email = 'john@doe.com';
$name = 'John Doe';
$to = wh_recipient($email, $name); // "John Doe" <john@doe.com>
```

#### Parameters

 * (string) (required) **$email**: an email address.
 * (array) (optional) **$name**: a name.

---

## Template Processing

You can render a template independently of sending an email with the following function.

### wh_get_template()

Use this function to process the HTML from a Well-Handled template.  It works exactly like `wh_mail_template()`, except you do not need to pass any email-related options.

```
<?php
$html = wh_get_template($template_slug, $data, $options);
```
