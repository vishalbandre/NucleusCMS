<?php
/**
  * Nucleus: PHP/MySQL Weblog CMS (http://nucleuscms.org/) 
  * Copyright (C) 2002 The Nucleus Group
  *
  * This program is free software; you can redistribute it and/or
  * modify it under the terms of the GNU General Public License
  * as published by the Free Software Foundation; either version 2
  * of the License, or (at your option) any later version.
  * (see nucleus/documentation/index.html#license for more info)
  *
  */
 
/**
 * This is the parser class of Nucleus. It is used for various things (skin parsing,
 * form generation, ...)
 */
class PARSER {
	
	/**
	 * Creates a new parser object with the given allowed actions 
	 * and the given handler
	 *
	 * @param $allowedActions array
	 * @param $handler class object with functions for each action (reference)
	 * @param $delim optional delimiter
	 * @param $paramdelim optional parameterdelimiter	 
	 */
	function PARSER($allowedActions, &$handler, $delim = '(<%|%>)', $pdelim = ',') {
		$this->actions = $allowedActions;
		$this->handler =& $handler;
		$this->delim = $delim;
		$this->pdelim = $pdelim;
		$this->norestrictions = 0;	// set this to 1 to disable checking for allowedActions
	}
	
	/**
	 * Parses the given contents and outputs it
	 */
	function parse(&$contents) {
	
		$pieces = split($this->delim,$contents);
		
		$maxidx = sizeof($pieces);
		for ($idx = 0;$idx<$maxidx;$idx++) {
			echo $pieces[$idx];		
			$idx++;
			$this->doAction($pieces[$idx]);
		}
	}
	

	/**
	  * handle an action 
	  */
	function doAction($action) {
		global $manager;

		if (!$action) return;
		
		// split into action name + arguments
		if (strstr($action,'(')) {
			$paramStartPos = strpos($action, '(');
			$params = substr($action, $paramStartPos + 1, strlen($action) - $paramStartPos - 2);
			$action = substr($action, 0, $paramStartPos);
			$params = explode ($this->pdelim, $params);
			
			// trim parameters 
			// for PHP versions lower than 4.0.6:
			//   - add // before '$params = ...' 
			//   - remove // before 'foreach'
			$params = array_map('trim',$params);
			// foreach ($params as $key => $value) { $params[$key] = trim($value); }			
		} else {
			// no parameters
			$params = array();
		}
	
		$actionlc = strtolower($action);
	
		if (in_array($actionlc, $this->actions) || $this->norestrictions ) {
			// when using PHP versions lower than 4.0.5, uncomment the line before
			// and comment the call_user_func_array call
			//$this->call_using_array($action, $this->handler, $params);
			call_user_func_array(array(&$this->handler,'parse_' . $actionlc), $params);
		} else {
			// redirect to plugin action if possible
			if (in_array('plugin', $this->actions) && $manager->pluginInstalled('NP_'.$action))
				$this->doAction('plugin('.$action.$this->pdelim.implode($this->pdelim,$params).')');
			else
				echo '<b>DISALLOWED (' , $action , ')</b>';
		}
		
	}
	
	/**
	  * Calls a method using an array of parameters (for use with PHP versions lower than 4.0.5)
	  * ( = call_user_func_array() function )
	  */
	function call_using_array($methodname, &$handler, $paramarray) {

		$methodname = 'parse_' . $methodname;
		
		if (!method_exists($handler, $methodname)) {
			return;
		}

		$command = 'call_user_func(array(&$handler,$methodname)';
		for ($i = 0; $i<count($paramarray); $i++)
			$command .= ',$paramarray[' . $i . ']';
		$command .= ');';
		eval($command);	// execute the correct method
	}
	
	function setProperty($property, $value) {
		global $manager;
		$manager->setParserProperty($property, $value);
	}
	
	function getProperty($name) {
		global $manager;
		return $manager->getParserProperty($name);
	}
	
	
}

/**
 * This class contains parse actions that are available in all ACTION classes
 * e.g. include, phpinclude, parsedinclude, skinfile, ...
 *
 * It should never be used on it's own
 */
class BaseActions {
	function BaseActions() {
		$this->level = 0; // depth level
		
		// if nesting level
		$this->if_conditions = array(); // array on which condition values are pushed/popped

		// highlights		
		$this->strHighlight = '';			// full highlight
		$this->aHighlight = array();		// parsed highlight
		
	}

	// include file (no parsing of php)
	function parse_include($filename) {
		@readfile($this->getIncludeFileName($filename));
	}
	
	// php-include file 
	function parse_phpinclude($filename) {
		includephp($this->getIncludeFileName($filename));
	}	
	// parsed include
	function parse_parsedinclude($filename) {
		// check current level
		if ($this->level > 3) return;	// max. depth reached (avoid endless loop)
		$filename = $this->getIncludeFileName($filename);
		if (!file_exists($filename)) return '';
		
		$this->level = $this->level + 1;
		
		// read file 
		$fd = fopen ($filename, 'r');
		$contents = fread ($fd, filesize ($filename));
		fclose ($fd);		
		
		// parse file contents
		$this->parser->parse($contents);
		
		$this->level = $this->level - 1;		
	}
	
	/**
	 * Returns the correct location of the file to be included, according to
	 * parser properties
	 *
	 * IF IncludeMode = 'skindir' => use skindir
	 */
	function getIncludeFileName($filename) {
		// leave absolute filenames and http urls as they are
		if (
				(substr($filename,0,1) == '/')
			||	(substr($filename,0,7) == 'http://')
			||	(substr($filename,0,6) == 'ftp://')			
			)
			return $filename;
	
		$filename = PARSER::getProperty('IncludePrefix') . $filename;
		if (PARSER::getProperty('IncludeMode') == 'skindir') {
			global $DIR_SKINS;
			return $DIR_SKINS . $filename;
		} else {
			return $filename;
		}
	}
	
	/**
	 * Inserts an url relative to the skindir (useful when doing import/export)
	 *
	 * e.g. <skinfile(default/myfile.sth)>
	 */
	function parse_skinfile($filename) {
		global $CONF;
		
		echo $CONF['SkinsURL'] . PARSER::getProperty('IncludePrefix') . $filename;
	}
	
	/**
	 * Sets a property for the parser
	 */
	function parse_set($property, $value) {
		PARSER::setProperty($property, $value);
	}
	
	/**
	 * Helper function: add if condition
	 */
	function _addIfCondition($condition) {
		
		// if parent block is not shown, we don't need to show child blocks
		if ($this->_getTopIfCondition())
			array_push($this->if_conditions,$condition);
		else
			array_push($this->if_conditions,0);
			
		ob_start();		
	}
	
	/**
	 * returns the currently top if condition
	 */
	function _getTopIfCondition() {
		if (sizeof($this->if_conditions) == 0) return 1;
		
		return $this->if_conditions[sizeof($this->if_conditions) - 1];
	}
	
	/**
	 * else
	 */
	function parse_else() {
		if (sizeof($this->if_conditions) == 0) return;
		
		if (array_pop($this->if_conditions)) {
			ob_end_flush();
			$this->_addIfCondition(0);
		} else {
			ob_end_clean();
			$this->_addIfCondition(1);
		}
		
	}
	
	/**
	 * Ends a conditional if-block 
	 * see e.g. ifcat (BLOG), ifblogsetting (PAGEFACTORY)
	 */
	function parse_endif() {
		// we can only close what has been opened
		if (sizeof($this->if_conditions) == 0) return;
		
		if (array_pop($this->if_conditions)) {
			ob_end_flush();
		} else {
			ob_end_clean();
		}
	}
	
	
	/** 
	 * Sets the search terms to be highlighted
	 *
	 * @param $highlight
	 *		A series of search terms
	 */
	function setHighlight($highlight) {	
		$this->strHighlight = $highlight;
		if ($highlight) {
			$this->aHighlight = parseHighlight($highlight); 
		}
	}
	
	/**
	 * Applies the highlight to the given piece of text
	 *
	 * @param &$data
	 *		Data that needs to be highlighted
	 * @see setHighlight
	 */
	function highlight(&$data) {
		if ($this->aHighlight)
			return highlight($data,$this->aHighlight,$this->template['SEARCH_HIGHLIGHT']);
		else
			return $data;
	}
	
	

}
 
?>