## Expressions

Let's start by setting up a basic model for use in the following examples:

```
<?php
$data = array(
	'title' => 'The Title',
	'url' => 'http://blog.com',
	'author' => array(
		'name' => 'Jane',
		'email' => 'jane@gmail.com'
	),
	'tags' => array('tech','design','email'),
	'related' => array(
		array(
			'title' => 'Post One',
			'url' => 'http://blog.com/one'
		),
		array(
			'title' => 'Post Two',
			'url' => 'http://blog.com/two'
		)
	)
);
```

Handlebars expressions are the basic unit of a Handlebars template. You can use them alone in a {{mustache}}, pass them to a Handlebars helper, or use them as values in hash arguments.


The simplest Handlebars expression is a simple identifier:

```
{{title}}
<!-- The Title -->
```

Handlebars nested expressions which are dot-separated paths.

```
{{author.name}}
<!-- Jane -->
```

Handlebars nested expressions in an array.

```
{{related.0.title}}
<!-- Post One -->
```

Handlebars also allows for name conflict resolution between helpers and data fields via a this reference:

```
{{./name}} or {{this/name}} or {{this.name}}
```

Handlebars expressions with a helper. In this case we're using the upper helper

```
{{#upper title}}
<!-- THE TITLE -->
```

Handlebars HTML-escapes values returned by a {{expression}}. If you don't want Handlebars to escape a value, use the "triple-stash", {{{ }}}

```
{{{foo}}}
```


---


## Control Structures

`if/else`, `ifEqual/else`, `ifGreater/else`, `ifLesser/else`, and `unless` control structures are implemented as regular Handlebars helpers.


### If/Else

You can use the `if` helper to conditionally render a block. If its argument returns `FALSE`, `NULL`, '', 0, or [], Handlebars will not render the block.

```
{{#if isActive}}
	This part will be shown if isActive is TRUE(ish)
{{else}}
	Otherwise you get this
{{/if}}
```


### IfEqual/Else

`ifEqual` works the same way as `if`, except the comparison involes a specific value. If the first value equals the second, Yay!, else, Nay!

```
{{#ifEqual mySubject "Linux"}}
	This part will be shown if mySubject == "Linux"
{{else}}
	Otherwise you get this
{{/ifEqual}}
```


### IfGreater/Else

`ifGreater` returns true if your variable is greater than the passed value.

```
{{#ifGreater myAmount 0}}
	This part will be shown if myAmount > 0
{{else}}
	Otherwise you get this
{{/ifGreater}}
```


### IfLesser/Else

`ifGreater` returns true if your variable is less than the passed value.

```
{{#ifLesser myAmount 0}}
	This part will be shown if myAmount < 0
{{else}}
	Otherwise you get this
{{/ifLesser}}
```


### Unless

The `unless` helper is the opposite of the `if` helper.

```
{{#unless isActive}}
	This part will not show if isActive is TRUE
{{/unless}}
```


---


##Iterators: Each

You can iterate over a list using the built-in `each` helper. Inside the block, you can use {{this}} or {{.}} to reference the element being iterated over.

```
<h2>Article Tags:</h2>
{{#each tags}}
	{{.}}
{{/each}}

{{#each related}}
	<a href="{{this.url}}">{{this.title}}</a>
{{/each}}

<!--this also works-->
{{#related}}
	<a href="{{this.url}}">{{this.title}}</a>
{{/related}}
```


### Each/Else

You can optionally provide an {{else}} section which will display only when the list is empty.

```
<h2>Article Tags:</h2>
{{#each tags}}
	{{.}}
{{else}}
	This article is not about anything in particular.
{{/each}}
```


---

## Change Context: With

You can shift the context for a section of a template by using the built-in with block helper.

```
<?php
$data =	array(
	'genres' => array('Hip-Hop', 'Rap', 'Techno', 'Country'),
	'other' => array(
		'genres' => array('Rock', 'Classical', 'Opera', 'Showtunes')
	)
);
```

---

```
<h2>All genres:</h2>
{{#with other}}
	{{#each genres}}
		{{.}}
	{{/each}}
{{/with}}
```


---


## Handlebars Helpers

### If

```
{{#if isActive}}
	This part will show if isActive is TRUE(ish)
{{else}}
	Otherwise you get this
{{/if}}
```

### IfEqual

```
{{#ifEqual name "Jane"}}
	This part will show if name == "Jane"
{{else}}
	Otherwise you get this
{{/ifEqual}}
```

### IfGreater

```
{{#ifGreater amount 0}}
	This part will show if amount > 0
{{else}}
	Otherwise you get this
{{/ifGreater}}
```

### IfLesser

```
{{#ifLesser amount 0}}
	This part will show if amount < 0
{{else}}
	Otherwise you get this
{{/ifLesser}}
```

### Unless

```
{{#unless isActive}}
	This part will show if isActive is FALSE(ish)
{{else}}
	Otherwise you get this
{{/unless}}
```

### Each

```
{{#each genres[0:10]}}
	{{.}}
{{else}}
	No genres found!
{{/each}}
```

### With

```
{{#with other_genres}}
	{{#each genres}}
		{{.}}
	{{/each}}
{{/with}}
```

### Avg

Return the average value from an array of numbers.

```
{{#avg myarray}}
```

### Capitalize

To capitalize the first letter. Alias: `#ucfirst`.

```
{{#capitalize title}}
```

### Capitalize_Words

To capitalize the first letter of each word in a string. Alias: `#ucwords`

```
{{#capitalize_words title}}
```

### Count

Count the number of items in an array.

```
{{#count myarray}}
```

### Currency

Turn a number into USD currecy format, e.g. 2.4 -> $2.40.

```
{{#currency number}}
```

### Date

Format a date using PHP's [$format](http://php.net/manual/en/function.date.php) guidelines. Alias: `#format_date`.

```
{{#date date 'Y-m-d H:i:s'}}
```

### Default

To use a default value if the string is empty: `{{#default title $defaultValue}}`.

```
{{#default title 'No title'}}
```

### Inflect

To singularize or plurialize words based on count `{{#inflect count $singular $plurial}}`. The `count` value may be an integer or an array; if the latter the array's size will be used as the value.

```
{{#inflect count '%d book' '%d books'}}
```

### Join

Join the values of an array with a defined delimiter.

```
{{#join myarray ', '}}
```

### Lower

To format string to lowercase. Alias: `#strtolower`.

```
{{#lower title}}
```

### Max

Return the maximum value from an array.

```
{{#max myarray}}
```

### Min

Return the minimum value from an array.

```
{{#min myarray}}
```

### Nl2Br

Converts \n \r newlines into HTML linebreaks.

```
{{#nl2br myparagraph}}
```

### Now

Return the current blog time, optionally formatted using PHP's [$format](http://php.net/manual/en/function.date.php) guidelines.

```
{{#now 'Y-m-d'}}
```

### Raw

This helper return handlebars expression as is. The expression will not be parsed:

```
{{#raw}}
	{{Curly brackets will print!}}
{{/raw}}
```

### Repeat

To repeat a string: `{{#repeat $count}}{{/repeat}}`

```
{{#repeat 5}}
	Hello World!
{{/repeat}}
```

Variable and blocks can still be used

```
{{#repeat 5}}
	Hello {{name}}!
{{/repeat}}
```

### Reverse

To reverse the order of string.

```
{{#reverse title}}
```

### Sum

Return the sum of numbers from an array.

```
{{#sum myarray}}
```

### Truncate

To truncate a string: `{{#truncate title $length $ellipsis}}`

```
{{#truncate title 21 '...'}}
```

### Upper

To format string to uppercase.

```
{{#upper title}}
```

### WP_BlogInfo

Return any WordPress [`bloginfo()`](https://developer.wordpress.org/reference/functions/get_bloginfo/) value by key.

```
{{#wp_bloginfo 'admin_email'}}
```

### WP_Site_URL

Works just like WordPress' [`site_url()`](https://developer.wordpress.org/reference/functions/site_url/) function. `$path` and `$scheme` parameters are optional.

```
{{#wp_site_url '/some/path' 'https'}}
```

---


### Template Comments

You can use comments in your handlebars code just as you would in your code. Since there is generally some level of logic, this is a good practice.

```
{{!-- only output this author names if an author exists --}}
```
