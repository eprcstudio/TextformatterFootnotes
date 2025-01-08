# TextformatterFootnotes

This textformatter adds footnotes using [Markdown Extra](https://michelf.ca/projects/php-markdown/extra/#footnotes)’s syntax, minus Markdown.

![screenshot](https://user-images.githubusercontent.com/6616448/228490886-37eb92d4-e41b-4a56-98ad-faf87588a74c.png)

Modules directory: https://processwire.com/modules/textformatter-footnotes/

Support forum: https://processwire.com/talk/topic/28344-textformatterfoonotes/

## About

This textformatter was primarly created to ease the addition of footnotes within HTML textareas (CKEditor or TinyMCE) using [Markdown Extra](https://michelf.ca/projects/php-markdown/extra/#footnotes)’s syntax. It will also retain any inline formatting tags that are whitelisted.

## Usage

To create a footnote reference, add a caret and an identifier inside brackets ([^1]). Then add the footnote in its own line using another caret and number inside brackets with a colon and text ([^1]: My footnote.). It will be put automatically inside the footnotes block at the end of the text.

### Notes 
- the identifier has to be a number but it is permissive in that you can put in the wrong order and it will be numbered back sequentially
- there is no support for indentation, though since `<br>` tags are allowed you should be fine
- if a footnote has no corresponding reference, it will be ignored and left as is
- by default references/footnotes are sequenced per field, meaning if you are outputting several fields with this textformatter each footnotes group will start from 1. This can changed using the "Use continuous sequencing?" option

## Options

In the module settings, you can change the icon (string) used as the backreference link but also the classes used for the wrapper, the reference and backreference links. You can also edit the list of whitelisted HTML tags that won’t be removed in the footnotes.

## Hook

If you want to have a more granular control over the footnotes (e.g. per field), you can use this hook:

```php
$wire->addHookBefore("TextformatterFootnotes::addFootnotes", function(HookEvent $event) {
	$str = $event->arguments(0);
	$options = $event->arguments(1);
	$field = $event->arguments(2);

	if($field != "your-field-name") return;

	// Say you want to change the icon for a <svg>
	$options["icon"] = file_get_contents("{$event->config->paths->templates}assets/icons/up.svg");
	// Or change the wrapper’s class
	$options["wrapperClass"] = "my-own-wrapper-class";

	// Put back the updated options array
	$event->arguments(1, $options);
});
```

Check the [source code](https://github.com/eprcstudio/TextformatterFootnotes/blob/main/TextformatterFootnotes.module.php#L73) for more options.
