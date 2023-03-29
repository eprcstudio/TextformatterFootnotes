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
		$this->set("allowedTags", $this->defaultInlineTags);
		$this->set("reset", 0);
	}

	public function format(&$str) {
		$str = $this->addFootnotes($str);
	}
	
	public function formatValue(Page $page, Field $field, &$value) {
		$value = $this->addFootnotes($value, $field);
	}

	/**
	 * Extracts references and footnotes from a string, converts them into links
	 * and put footnotes at the end of the string
	 * 
	 * You can specify options in an associative array:
	 * 
	 * - `tag` (string): Tag used for the footnotes’ wrapper
	 * 
	 * - `icon` (string): String (could be a `<img>` or `<svg>`) used in the
	 * backreference link
	 * 
	 * - `wrapperClass` (string): Class used for the footnotes’ wrapper
	 * 
	 * - `referenceClass` (string): Class used for the reference link
	 * 
	 * - `backrefClass` (string): Class used for the backreference link
	 * 
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
		$defaultOptions = [
			"tag" => "div",
			"icon" => $this->icon,
			"wrapperClass" => $this->wrapperClass,
			"referenceClass" => $this->referenceClass,
			"backrefClass" => $this->backrefClass,
			"pretty" => false
		];
		$options = array_merge($defaultOptions, $options);
		$temp = $str;
		// Clean line returns
		$temp = str_replace(array("\r\n", "\r"), "\n", $temp);
		$temp = preg_replace("/\n{2,}/", "\n", $temp);
		$temp = trim($temp, "\n");
		$lines = explode("\n", $temp);
		// Get references
		$index = 1;
		$references = [];
		foreach($lines as $lineIndex => $line) {
			if(!preg_match_all("/\[\^(\d+)\](?!:)/", $line, $matches)) continue;
			foreach($matches[0] as $key => $match) {
				$references[$matches[1][$key]] = [
					"index" => $index++,
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
		// Get current id
		$footnotesId = setting("footnotesId");
		if(!$footnotesId) $footnotesId = 1;
		// Add references
		foreach($references as $key => $reference) {
			if(!key_exists($key, $footnotes)) continue;
			$id = "$footnotesId:$reference[index]";
			$ref =
				"<sup id=\"fnref$id\" class=\"$options[referenceClass]\">" .
				"<a href=\"#fn$id\" role=\"doc-noteref\">$reference[index]</a>" . 
				"</sup>";
			$lines[$reference["source"]] = preg_replace("/\[\^$key\](?!:)/", $ref, $lines[$reference["source"]]);
		}
		// Put lines back together
		$str = implode("\n", $lines) . "\n";
		// Add footnotes
		$str .= "<$options[tag] class=\"$options[wrapperClass]\" role=\"doc-endnotes\">";
		if($options["pretty"]) $str .= "\n\t";
		$str .= "<ol>";
		foreach($footnotes as $key => $footnote) {
			$id = "$footnotesId:$key";
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
		// Increment id
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
		$f->attr("name", "icon");
		$f->label = "Backreference icon";
		$f->description = "Default: \"&amp;#8617;\" &#8617;";
		$f->columnWidth = 25;
		$f->attr("value", $this->icon);
		$inputfields->append($f);

		/** @var InputfieldText $f */
		$f = $modules->get("InputfieldText");
		$f->attr("name", "wrapperClass");
		$f->label = "Wrapper class";
		$f->description = "Default: \"footnotes\"";
		$f->columnWidth = 25;
		$f->attr("value", $this->wrapperClass);
		$inputfields->append($f);

		/** @var InputfieldText $f */
		$f = $modules->get("InputfieldText");
		$f->attr("name", "referenceClass");
		$f->label = "Reference class";
		$f->description = "Default: \"footnote-ref\"";
		$f->columnWidth = 25;
		$f->attr("value", $this->referenceClass);
		$inputfields->append($f);

		/** @var InputfieldText $f */
		$f = $modules->get("InputfieldText");
		$f->attr("name", "backrefClass");
		$f->label = "Backreference class";
		$f->description = "Default: \"footnote-backref\"";
		$f->columnWidth = 25;
		$f->attr("value", $this->backrefClass);
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
	
		/** @var InputfieldTextTags $f */
		$f = $modules->get("InputfieldTextTags");
		$f->attr("name", "allowedTags");
		$f->label = "Allowed Inline Tags";
		$f->description = "When parsing the footnotes, any inline tags other than these will be removed";
		$f->allowUserTags = 1;
		$f->delimiter = "p";
		$f->attr("value", $this->allowedTags);
		$inputfields->append($f);

		/** @var InputfieldCheckbox $f */
		$f = $modules->get("InputfieldCheckbox");
		$f->attr("name", "reset");
		$f->label = "Reset Allowed Tags";
		$f->description = "Do you want to put back the initial allowed inline tags?";
		$f->collapsed = 1;
		$f->label2 = "Yes";
		$f->attr("value", 0);
		$inputfields->append($f);

		return $inputfields;
	}

}