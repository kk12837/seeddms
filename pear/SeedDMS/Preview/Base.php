<?php
/**
 * Implementation of preview base
 *
 * @category   DMS
 * @package    SeedDMS_Preview
 * @license    GPL 2
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2010, Uwe Steinmann
 * @version    Release: 1.2.2
 */


/**
 * Class for managing creation of preview images for documents.
 *
 * @category   DMS
 * @package    SeedDMS_Preview
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2011, Uwe Steinmann
 * @version    Release: 1.2.2
 */
class SeedDMS_Preview_Base {
	/**
	 * @var string $cacheDir location in the file system where all the
	 *      cached data like thumbnails are located. This should be an
	 *      absolute path.
	 * @access public
	 */
	public $previewDir;

	/**
	 * @var array $converters list of mimetypes and commands for converting
	 * file into preview image
	 * @access protected
	 */
	protected $converters;

	/**
	 * @var integer $timeout maximum time for execution of external commands
	 * @access protected
	 */
	protected $timeout;

	function __construct($previewDir, $timeout=5) { /* {{{ */
		if(!is_dir($previewDir)) {
			if (!SeedDMS_Core_File::makeDir($previewDir)) {
				$this->previewDir = '';
			} else {
				$this->previewDir = $previewDir;
			}
		} else {
			$this->previewDir = $previewDir;
		}
		$this->timeout = intval($timeout);
	} /* }}} */

	static function execWithTimeout($cmd, $timeout=5) { /* {{{ */
		$descriptorspec = array(
			0 => array("pipe", "r"),
			1 => array("pipe", "w"),
			2 => array("pipe", "w")
		);
		$pipes = array();
	 
		$timeout += time();
		$process = proc_open($cmd, $descriptorspec, $pipes);
		if (!is_resource($process)) {
			throw new Exception("proc_open failed on: " . $cmd);
		}
			 
		$output = '';
		$timeleft = $timeout - time();
		$read = array($pipes[1]);
		$write = NULL;
		$exeptions = NULL;
		do {
			stream_select($read, $write, $exeptions, $timeleft, 200000);
					 
			if (!empty($read)) {
				$output .= fread($pipes[1], 8192);
													}
			$timeleft = $timeout - time();
		} while (!feof($pipes[1]) && $timeleft > 0);
 
		if ($timeleft <= 0) {
			proc_terminate($process);
			throw new Exception("command timeout on: " . $cmd);
		} else {
			return $output;
		}
	} /* }}} */

	/**
	 * Set a list of converters
	 *
	 * Merges the list of passed converters with the already existing ones.
	 * Existing converters will be overwritten.
	 *
	 * @param array list of converters. The key of the array contains the mimetype
	 * and the value is the command to be called for creating the preview
	 */
	function setConverters($arr) { /* {{{ */
		$this->converters = array_merge($this->converters, $arr);
	} /* }}} */

	/**
	 * Check if converter for a given mimetype is set
	 *
	 * @param string $mimetype 
	 * @return boolean true if converter exists, otherwise false
	 */
	function hasConverter($mimetype) { /* {{{ */
		return array_key_exists($mimetype, $this->converters);
	} /* }}} */

}

