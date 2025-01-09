<?php namespace ProcessWire;

/**
 * Adds footnotes using Markdown Extra’s syntax, minus Markdown
 * 
 * Copyright (c) 2025 EPRC
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
		$formatted = $this->addFootnotes($str);
		$str = is_array($formatted) ? $formatted["str"] : $formatted;
	}
	
	public function formatValue(Page $page, Field $field, &$value) {
		$formatted = $this->addFootnotes($value, [], $field);
		$value = is_array($formatted) ? $formatted["str"] : $formatted;
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
	 * - `continuous` (bool): Use continuous sequencing throughout page render?
	 * - `outputAsArray` (bool): Have the function return an array with the
	 * formatted string and the footnotes separated?
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
		if(!$str || !is_string($str)) return "";

		if(!is_array($options)) $options = [];
		$options = $this->mergeDefaultOptions($options);

		$footnoteIndex = $options["continuous"] ? (setting("tf_footnoteIndex") ?: 1) : 1;
		$footnotesId = setting("tf_footnotesId") ?: 1;
		
		// Get references
		if(!preg_match_all("/\[\^(\d+)\](?!:)/", $str, $matches)) return $str;
		$references = [];
		foreach($matches[0] as $key => $match) {
			$identifier = $matches[1][$key];
			// Have we already processed this identifier?
			if(array_key_exists($identifier, $references)) continue;
			// Are there any matching footnote?
			if(!preg_match("/\[\^$identifier\]:/", $str)) continue;
			$id = (!$options["continuous"] ? "$footnotesId:" : "") . $footnoteIndex;
			$references[$identifier] = [
				"id" => $id,
				"identifier" => $identifier,
				"index" => $footnoteIndex,
				"str" => $match
			];
			// Convert references into anchor links
			$ref =
				"<sup id='fnref$id' class='$options[referenceClass]'>" .
				"<a href='#fn$id' role='doc-noteref'>$footnoteIndex</a>" . 
				"</sup>";
			$str = preg_replace("/\[\^$identifier\](?!:)/", $ref, $str);
			$footnoteIndex++;
		}

		if(empty($references)) return $str;

		// Get footnotes
		if(!preg_match_all("/\[\^(\d+)\]:.+?(?=\[\^\d+\]:|$)/m", $str, $matches)) return $str;
		$footnotes = [];
		foreach($matches[0] as $key => $match) {
			$identifier = $matches[1][$key];
			if(!array_key_exists($identifier, $references)) continue;
			$ref = $references[$identifier];
			$key = array_search($identifier, array_keys($references));
			// We can’t have two footnotes with the same identifier
			if(array_key_exists($key, $footnotes)) continue;
			// Remove HTML markup
			$footnote = strip_tags($match, explode("|", $this->allowedTags));
			$footnote = preg_replace("/\[\^(\d+)\]\: */", "", $footnote);
			$footnotes[$key] = [
				"footnote" => $footnote,
				"id" => $ref["id"],
				"identifier" => $identifier,
				"index" => $ref["index"],
			];
			// Remove footnote
			$str = str_replace($match, "", $str);
		}

		if(empty($footnotes)) return $str;
		
		ksort($footnotes);

		// Append footnotes
		if(!$options["outputAsArray"]) {
			$str .= "\n" . $this->generateFootnotesMarkup($footnotes, $options);
		}

		// Set runtime variable for the next textformatter call
		setting("tf_footnoteIndex", $footnoteIndex);
		setting("tf_footnotesId", $footnotesId + 1);
		
		return $options["outputAsArray"] ? [
			"str" => $str,
			"footnotes" => $footnotes
		] : $str;
	}

	public function generateFootnotesMarkup($footnotes, $options = []) {
		if(!$footnotes || !is_array($footnotes) || empty($footnotes)) return "";

		if(!is_array($options)) $options = [];
		$options = $this->mergeDefaultOptions($options);

		$markup = "<$options[tag] class='$options[wrapperClass]' role='doc-endnotes'>";
		if($options["pretty"]) $markup .= "\n\t";
		$markup .= "<ol";
		if($options["continuous"]) {
			// we want the different ordered lists to start at the right number
			$markup .= " start='" . $footnotes[0]["index"] . "'";
		}
		$markup .= ">";
		foreach($footnotes as $footnote) {
			$id = $footnote["id"];
			if(!$options["continuous"]) {
				$_group = explode(":", $id)[0];
				// when the group id is different, create a new ordered list
				if(isset($group) && $group !== $_group) {
					if($options["pretty"]) $markup .= "\n\t";
					$markup .= "</ol>";
					if($options["pretty"]) $markup .= "\n\t";
					$markup .= "<ol>";
				}
				$group = $_group;
			}
			if($options["pretty"]) $markup .= "\n\t\t";
			$markup .= "<li id='fn$id' role='doc-endnote'>";
			if($options["pretty"]) $markup .= "\n\t\t\t";
			$markup .= 
				"$footnote[footnote] " .
				"<a href='#fnref$id' class='$options[backrefClass]' role='doc-backlink'>$options[icon]</a>";
			if($options["pretty"]) $markup .= "\n\t\t";
			$markup .= "</li>";
		}
		if($options["pretty"]) $markup .= "\n\t";
		$markup .= "</ol>";
		if($options["pretty"]) $markup .= "\n";
		$markup .= "</$options[tag]>";

		return $markup;
	}

	public function mergeDefaultOptions($options = []) {
		return array_merge([
			"tag" => "div",
			"icon" => $this->icon,
			"wrapperClass" => $this->wrapperClass,
			"referenceClass" => $this->referenceClass,
			"backrefClass" => $this->backrefClass,
			"continuous" => (bool) $this->continuous,
			"outputAsArray" => false,
			"pretty" => false,
		], $options);
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
			"<div><p class='description'>Input</p><pre>$exampleInput</pre></div>"
			. "<div><p class='description'>Output</p><pre style='margin-bottom:0;'>$exampleOutput</pre></div>";
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