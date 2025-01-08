<?php namespace ProcessWire;

/**
 * Adds footnotes using Markdown Extra’s syntax, minus Markdown
 * 
 * Copyright (c) 2023 EPRC
 * Licensed under MIT License, see LICENSE
 *
 * https://eprc.studio
 *
 * For ProcessWire 3.x
 * Copyright (c) 2021 by Ryan Cramer
 * Licensed under GNU/GPL v2
 *
 * https://www.processwire.com
 *
 */

class TextformatterFootnotes extends Textformatter implements ConfigurableModule {

	/**
	 * List of the default whitelisted inline tags
	 * 
	 * @var string
	 * 
	 */
	protected $defaultInlineTags = "abbr|a|bdi|bdo|br|b|cite|code|data|del|dfn|em|ins|i|kbd|mark|q|small|span|strong|sub|sup|s|time|var";

	public function __construct() {
		parent::__construct();
		$this->set("icon", "&#8617;");
		$this->set("wrapperClass", "footnotes");
		$this->set("referenceClass", "footnote-ref");
		$this->set("backrefClass", "footnote-backref");
		$this->set("continuous", 0);
		$this->set("allowedTags", $this->defaultInlineTags);
		$this->set("reset", 0);
	}

	public function format(&$str) {
		$str = $this->addFootnotes($str);
	}
	
	public function formatValue(Page $page, Field $field, &$value) {
		$value = $this->addFootnotes($value, [], $field);
	}

	/**
	 * Extracts references and footnotes from a string, converts them into links
	 * and put footnotes at the end of the string
	 * 
	 * You can specify options in an associative array:
	 * - `tag` (string): Tag used for the footnotes’ wrapper
	 * - `icon` (string): String (could be a `<img>` or `<svg>`) used in the
	 * backreference link
	 * - `wrapperClass` (string): Class used for the footnotes’ wrapper
	 * - `referenceClass` (string): Class used for the reference link
	 * - `backrefClass` (string): Class used for the backreference link
	 * - `continuous` (bool): Use continuous sequencing throughout the page render?
	 * - `pretty` (bool): Add tabs/carriage returns to the footnotes’ output?
	 * 
	 * @see https://michelf.ca/projects/php-markdown/extra/#footnotes
	 * @param string $str
	 * @param array $options
	 * @param Field|string $field
	 * @return string
	 * 
	 */
	public function ___addFootnotes($str, $options = [], $field = "") {
		if(!$str) return "";

		if(!is_array($options)) $options = [];
		$defaultOptions = [
			"tag" => "div",
			"icon" => $this->icon,
			"wrapperClass" => $this->wrapperClass,
			"referenceClass" => $this->referenceClass,
			"backrefClass" => $this->backrefClass,
			"continuous" => (bool) $this->continuous,
			"pretty" => false
		];
		$options = array_merge($defaultOptions, $options);

		$temp = $str;

		// Clean line returns
		$temp = str_replace(array("\r\n", "\r"), "\n", $temp);
		$lines = explode("\n", $temp);

		// Get references
		$footnoteIndex = $options["continuous"] ? setting("footnoteIndex") : 1;
		if(!$footnoteIndex) {
			$footnoteIndex = 1;
			setting("footnoteIndex", $footnoteIndex);
		}
		$references = [];
		foreach($lines as $lineIndex => $line) {
			if(!preg_match_all("/\[\^(\d+)\](?!:)/", $line, $matches)) continue;
			foreach($matches[0] as $key => $match) {
				$references[$matches[1][$key]] = [
					"index" => $footnoteIndex++,
					"str" => $match,
					"source" => $lineIndex
				];
			}
		}
		if(empty($references)) return $str;

		// Get footnotes
		$footnotes = [];
		foreach($lines as $lineIndex => $line) {
			if(!preg_match_all("/\[\^(\d+)\]\:(?:.(?!\[\^))*/", $line, $matches)) continue;
			$unset = true;
			foreach($matches[0] as $key => $match) {
				if(!key_exists($matches[1][$key], $references)) {
					$unset = false;
					continue;
				}
				$index = $references[$matches[1][$key]]["index"];
				// Remove HTML markup
				$footnote = strip_tags($match, explode("|", $this->allowedTags));
				$footnote = preg_replace("/\[\^(\d+)\]\: */", "", $footnote);
				$footnotes[$index] = $footnote;
			}
			if($unset) unset($lines[$lineIndex]);
		}
		ksort($footnotes);
		if(empty($footnotes)) return $str;

		// Get footnotes’ current id (not used with `continuous` option)
		$footnotesId = setting("footnotesId") ?: 1;

		// Add references
		foreach($references as $key => $reference) {
			if(!key_exists($reference["index"], $footnotes)) continue;
			$id = (!$options["continuous"] ? "$footnotesId:" : "") . $reference["index"];
			$ref =
				"<sup id=\"fnref$id\" class=\"$options[referenceClass]\">" .
				"<a href=\"#fn$id\" role=\"doc-noteref\">$reference[index]</a>" . 
				"</sup>";
			// Replace reference with anchor link
			$lines[$reference["source"]] = preg_replace("/\[\^$key\](?!:)/", $ref, $lines[$reference["source"]]);
		}

		// Put lines back together
		$str = implode("\n", $lines) . "\n";

		// Add footnotes
		$str .= "<$options[tag] class=\"$options[wrapperClass]\" role=\"doc-endnotes\">";
		if($options["pretty"]) $str .= "\n\t";
		$str .= "<ol";
		if($options["continuous"]) {
			$str .= " start='" . setting("footnoteIndex") . "'";
		}
		$str .= ">";
		foreach($footnotes as $key => $footnote) {
			$id = (!$options["continuous"] ? "$footnotesId:" : "") . $key;
			if($options["pretty"]) $str .= "\n\t\t";
			$str .= "<li id=\"fn$id\" role=\"doc-endnote\">";
			if($options["pretty"]) $str .= "\n\t\t\t";
			$str .= "$footnote <a href=\"#fnref$id\" class=\"$options[backrefClass]\" role=\"doc-backlink\">$options[icon]</a>";
			if($options["pretty"]) $str .= "\n\t\t";
			$str .= "</li>";
		}
		if($options["pretty"]) $str .= "\n\t";
		$str .= "</ol>";
		if($options["pretty"]) $str .= "\n";
		$str .= "</$options[tag]>";
		
		// Set current footnote’s index (used with `continuous` option)
		setting("footnoteIndex", $footnoteIndex);
		
		// Increment footnotes’ id (not used with `continuous` option)
		setting("footnotesId", $footnotesId + 1);
		
		return $str;
	}

	public function getModuleConfigInputfields(InputfieldWrapper $inputfields) {
		/** @var Modules $modules */
		$modules = $this->wire()->modules;

		if($this->reset) {
			$this->allowedTags = $this->defaultInlineTags;
		}

		/** @var InputfieldText $f */
		$f = $modules->get("InputfieldText");
		$f->name = "icon";
		$f->label = "Backreference icon";
		$f->description = "Default: \"&amp;#8617;\" &#8617;";
		$f->columnWidth = 25;
		$f->value = $this->icon;
		$inputfields->append($f);

		/** @var InputfieldText $f */
		$f = $modules->get("InputfieldText");
		$f->name = "wrapperClass";
		$f->label = "Wrapper class";
		$f->description = "Default: \"footnotes\"";
		$f->columnWidth = 25;
		$f->value = $this->wrapperClass;
		$inputfields->append($f);

		/** @var InputfieldText $f */
		$f = $modules->get("InputfieldText");
		$f->name = "referenceClass";
		$f->label = "Reference class";
		$f->description = "Default: \"footnote-ref\"";
		$f->columnWidth = 25;
		$f->value = $this->referenceClass;
		$inputfields->append($f);

		/** @var InputfieldText $f */
		$f = $modules->get("InputfieldText");
		$f->name = "backrefClass";
		$f->label = "Backreference class";
		$f->description = "Default: \"footnote-backref\"";
		$f->columnWidth = 25;
		$f->value = $this->backrefClass;
		$inputfields->append($f);

		/** @var InputfieldMarkup $f */
		$f = $modules->get("InputfieldMarkup");
		$f->label = "Usage";
		$exampleInput = "This is a reference[^1] within a text\n[^1]: And this is a footnote";
		$exampleOutput = htmlentities($this->___addFootnotes($exampleInput, ["pretty" => true]));
		$f->markupText = 
			"<div><p class=\"description\">Input</p><pre>$exampleInput</pre></div>"
			. "<div><p class=\"description\">Output</p><pre style=\"margin-bottom:0;\">$exampleOutput</pre></div>";
		$f->collapsed = 1;
		$inputfields->append($f);

		$f = $inputfields->InputfieldToggle;
		$f->set('themeOffset', 1);
		$f->label = $this->_("Use continuous sequencing?");
		$f->description = $this->_("When enabled references/footnotes will be continuously sequenced throughout the page render instead of individually per field");
		$f->name = "continuous";
		$f->useReverse = 1;
		$f->value = (bool) $this->continuous;
		$inputfields->add($f);
	
		/** @var InputfieldTextTags $f */
		$f = $modules->get("InputfieldTextTags");
		$f->name = "allowedTags";
		$f->label = "Allowed Inline Tags";
		$f->description = "When parsing the footnotes, any inline tags other than these will be removed";
		$f->allowUserTags = 1;
		$f->delimiter = "p";
		$f->value = $this->allowedTags;
		$inputfields->append($f);

		/** @var InputfieldCheckbox $f */
		$f = $modules->get("InputfieldCheckbox");
		$f->name = "reset";
		$f->label = "Reset Allowed Tags";
		$f->description = "Do you want to put back the initial allowed inline tags?";
		$f->collapsed = 1;
		$f->label2 = "Yes";
		$f->value = 0;
		$inputfields->append($f);

		return $inputfields;
	}

}