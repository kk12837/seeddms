<?php
/**
 * Implementation of EditFolder controller
 *
 * @category   DMS
 * @package    SeedDMS
 * @license    GPL 2
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2010-2013 Uwe Steinmann
 * @version    Release: @package_version@
 */

/**
 * Class which does the busines logic for editing a folder
 *
 * @category   DMS
 * @package    SeedDMS
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2010-2013 Uwe Steinmann
 * @version    Release: @package_version@
 */
class SeedDMS_Controller_EditFolder extends SeedDMS_Controller_Common {

	public function run() {
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$settings = $this->params['settings'];
		$folder = $this->params['folder'];
		$name = $this->params['name'];
		$comment = $this->params['comment'];
		$sequence = $this->params['sequence'];
		$attributes = $this->params['attributes'];

		/* Get the document id and name before removing the document */
		$foldername = $folder->getName();
		$folderid = $folder->getID();

		if(!$this->callHook('preEditFolder')) {
		}

		$result = $this->callHook('editFolder', $folder);
		if($result === null) {
			if(($oldname = $folder->getName()) != $name)
				if(!$folder->setName($name))
					return false;

			if(($oldcomment = $folder->getComment()) != $comment)
				if(!$folder->setComment($comment))
					return false;

			$oldattributes = $folder->getAttributes();
			if($attributes) {
				foreach($attributes as $attrdefid=>$attribute) {
					$attrdef = $dms->getAttributeDefinition($attrdefid);
					if($attribute) {
						if(!$attrdef->validate($attribute)) {
							$this->errormsg	= getAttributeValidationText($attrdef->getValidationError(), $attrdef->getName(), $attribute);
							return false;
						}

						if(!isset($oldattributes[$attrdefid]) || $attribute != $oldattributes[$attrdefid]->getValue()) {
							if(!$folder->setAttributeValue($dms->getAttributeDefinition($attrdefid), $attribute))
								return false;
						}
					} elseif($attrdef->getMinValues() > 0) {
						$this->errormsg = getMLText("attr_min_values", array("attrname"=>$attrdef->getName()));
					} elseif(isset($oldattributes[$attrdefid])) {
						if(!$folder->removeAttribute($dms->getAttributeDefinition($attrdefid)))
							return false;
					}
				}
			}
			foreach($oldattributes as $attrdefid=>$oldattribute) {
				if(!isset($attributes[$attrdefid])) {
					if(!$folder->removeAttribute($dms->getAttributeDefinition($attrdefid)))
						return false;
				}
			}

			if(strcasecmp($sequence, "keep")) {
				if($folder->setSequence($sequence)) {
				} else {
					return false;
				}
			}

			if(!$this->callHook('postEditFolder')) {
			}

		} else
			return $result;

		return true;
	}
}
