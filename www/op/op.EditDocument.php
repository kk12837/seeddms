<?php
//    MyDMS. Document Management System
//    Copyright (C) 2002-2005  Markus Westphal
//    Copyright (C) 2006-2008 Malcolm Cowe
//    Copyright (C) 2010-2016 Uwe Steinmann
//
//    This program is free software; you can redistribute it and/or modify
//    it under the terms of the GNU General Public License as published by
//    the Free Software Foundation; either version 2 of the License, or
//    (at your option) any later version.
//
//    This program is distributed in the hope that it will be useful,
//    but WITHOUT ANY WARRANTY; without even the implied warranty of
//    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//    GNU General Public License for more details.
//
//    You should have received a copy of the GNU General Public License
//    along with this program; if not, write to the Free Software
//    Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.

include("../inc/inc.Settings.php");
include("../inc/inc.LogInit.php");
include("../inc/inc.Utils.php");
include("../inc/inc.Language.php");
include("../inc/inc.Init.php");
include("../inc/inc.Extension.php");
include("../inc/inc.DBInit.php");
include("../inc/inc.ClassUI.php");
include("../inc/inc.Authentication.php");

if (!isset($_POST["documentid"]) || !is_numeric($_POST["documentid"]) || intval($_POST["documentid"])<1) {
	UI::exitError(getMLText("document_title", array("documentname" => getMLText("invalid_doc_id"))),getMLText("invalid_doc_id"));
}

$documentid = $_POST["documentid"];
$document = $dms->getDocument($documentid);

if (!is_object($document)) {
	UI::exitError(getMLText("document_title", array("documentname" => getMLText("invalid_doc_id"))),getMLText("invalid_doc_id"));
}

$folder = $document->getFolder();
$docPathHTML = getFolderPathHTML($folder, true). " / <a href=\"../out/out.ViewDocument.php?documentid=".$documentid."\">".$document->getName()."</a>";

if ($document->getAccessMode($user) < M_READWRITE) {
	UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("access_denied"));
}

if($document->isLocked()) {
	$lockingUser = $document->getLockingUser();
	if (($lockingUser->getID() != $user->getID()) && ($document->getAccessMode($user) != M_ALL)) {
		UI::exitError(getMLText("document_title", array("documentname" => htmlspecialchars($document->getName()))),getMLText("lock_message", array("email" => $lockingUser->getEmail(), "username" => htmlspecialchars($lockingUser->getFullName()))));
	}
}

$name =     isset($_POST['name']) ? trim($_POST["name"]) : "";
$comment =  isset($_POST['comment']) ? trim($_POST["comment"]) : "";
$keywords = isset($_POST["keywords"]) ? trim($_POST["keywords"]) : "";
if(isset($_POST['categoryidform1'])) {
	$categories = explode(',', preg_replace('/[^0-9,]+/', '', $_POST["categoryidform1"]));
} elseif(isset($_POST["categories"])) { 
	$categories = $_POST["categories"];
} else {
	$categories = array();
}
$sequence = isset($_POST["sequence"]) ? $_POST["sequence"] : "keep";
$sequence = str_replace(',', '.', $_POST["sequence"]);
if (!is_numeric($sequence)) {
	$sequence="keep";
}
if(isset($_POST["attributes"]))
	$attributes = $_POST["attributes"];
else
	$attributes = array();

if (($oldname = $document->getName()) != $name) {
	if($document->setName($name)) {
		// Send notification to subscribers.
		if($notifier) {
			$notifyList = $document->getNotifyList();
			$folder = $document->getFolder();
			$subject = "document_renamed_email_subject";
			$message = "document_renamed_email_body";
			$params = array();
			$params['name'] = $document->getName();
			$params['old_name'] = $oldname;
			$params['folder_path'] = $folder->getFolderPathPlain();
			$params['username'] = $user->getFullName();
			$params['url'] = "http".((isset($_SERVER['HTTPS']) && (strcmp($_SERVER['HTTPS'],'off')!=0)) ? "s" : "")."://".$_SERVER['HTTP_HOST'].$settings->_httpRoot."out/out.ViewDocument.php?documentid=".$document->getID();
			$params['sitename'] = $settings->_siteName;
			$params['http_root'] = $settings->_httpRoot;

			// if user is not owner send notification to owner
			if ($user->getID() != $document->getOwner()->getID() &&
				false === SeedDMS_Core_DMS::inList($document->getOwner(), $notifyList['users'])) {
				$notifyList['users'][] = $document->getOwner();
			}
			$notifier->toList($user, $notifyList["users"], $subject, $message, $params);
			foreach ($notifyList["groups"] as $grp) {
				$notifier->toGroup($user, $grp, $subject, $message, $params);
			}
		}

	}
	else {
		UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("error_occured"));
	}
}

if (($oldcomment = $document->getComment()) != $comment) {
	if($document->setComment($comment)) {
		// Send notification to subscribers.
		if($notifier) {
			$notifyList = $document->getNotifyList();
			$folder = $document->getFolder();
			$subject = "document_comment_changed_email_subject";
			$message = "document_comment_changed_email_body";
			$params = array();
			$params['name'] = $document->getName();
			$params['folder_path'] = $folder->getFolderPathPlain();
			$params['old_comment'] = $oldcomment;
			$params['new_comment'] = $comment;
			$params['username'] = $user->getFullName();
			$params['url'] = "http".((isset($_SERVER['HTTPS']) && (strcmp($_SERVER['HTTPS'],'off')!=0)) ? "s" : "")."://".$_SERVER['HTTP_HOST'].$settings->_httpRoot."out/out.ViewDocument.php?documentid=".$document->getID();
			$params['sitename'] = $settings->_siteName;
			$params['http_root'] = $settings->_httpRoot;

			// if user is not owner send notification to owner
			if ($user->getID() != $document->getOwner()->getID() &&
				false === SeedDMS_Core_DMS::inList($document->getOwner(), $notifyList['users'])) {
				$notifyList['users'][] = $document->getOwner();
			}
			$notifier->toList($user, $notifyList["users"], $subject, $message, $params);
			foreach ($notifyList["groups"] as $grp) {
				$notifier->toGroup($user, $grp, $subject, $message, $params);
			}
		}
	}
	else {
		UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("error_occured"));
	}
}

switch($_POST["presetexpdate"]) {
case "date":
	$tmp = explode('-', $_POST["expdate"]);
	$expires = mktime(0,0,0, $tmp[1], $tmp[2], $tmp[0]);
	break;
case "1w":
	$tmp = explode('-', date('Y-m-d'));
	$expires = mktime(0,0,0, $tmp[1], $tmp[2]+7, $tmp[0]);
	break;
case "1m":
	$tmp = explode('-', date('Y-m-d'));
	$expires = mktime(0,0,0, $tmp[1]+1, $tmp[2], $tmp[0]);
	break;
case "1y":
	$tmp = explode('-', date('Y-m-d'));
	$expires = mktime(0,0,0, $tmp[1], $tmp[2], $tmp[0]+1);
	break;
case "2y":
	$tmp = explode('-', date('Y-m-d'));
	$expires = mktime(0,0,0, $tmp[1], $tmp[2], $tmp[0]+2);
	break;
case "never":
default:
	$expires = null;
	break;
}

if ($expires != $document->getExpires()) {
	if(isset($GLOBALS['SEEDDMS_HOOKS']['editDocument'])) {
		foreach($GLOBALS['SEEDDMS_HOOKS']['editDocument'] as $hookObj) {
			if (method_exists($hookObj, 'preSetExpires')) {
				$hookObj->preSetExpires(null, array('document'=>$document, 'expires'=>&$expires));
			}
		}
	}

	if($document->setExpires($expires)) {
		if($notifier) {
			$notifyList = $document->getNotifyList();
			$folder = $document->getFolder();
			// Send notification to subscribers.
			$subject = "expiry_changed_email_subject";
			$message = "expiry_changed_email_body";
			$params = array();
			$params['name'] = $document->getName();
			$params['folder_path'] = $folder->getFolderPathPlain();
			$params['username'] = $user->getFullName();
			$params['url'] = "http".((isset($_SERVER['HTTPS']) && (strcmp($_SERVER['HTTPS'],'off')!=0)) ? "s" : "")."://".$_SERVER['HTTP_HOST'].$settings->_httpRoot."out/out.ViewDocument.php?documentid=".$document->getID();
			$params['sitename'] = $settings->_siteName;
			$params['http_root'] = $settings->_httpRoot;

			// if user is not owner send notification to owner
			if ($user->getID() != $document->getOwner()->getID() &&
				false === SeedDMS_Core_DMS::inList($document->getOwner(), $notifyList['users'])) {
				$notifyList['users'][] = $document->getOwner();
			}
			$notifier->toList($user, $notifyList["users"], $subject, $message, $params);
			foreach ($notifyList["groups"] as $grp) {
				$notifier->toGroup($user, $grp, $subject, $message, $params);
			}
		}
	} else {
		UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("error_occured"));
	}

	$document->verifyLastestContentExpriry();

	if(isset($GLOBALS['SEEDDMS_HOOKS']['editDocument'])) {
		foreach($GLOBALS['SEEDDMS_HOOKS']['editDocument'] as $hookObj) {
			if (method_exists($hookObj, 'postSetExpires')) {
				$hookObj->postSetExpires(null, array('document'=>$document, 'expires'=>$expires));
			}
		}
	}
}

if (($oldkeywords = $document->getKeywords()) != $keywords) {
	if(isset($GLOBALS['SEEDDMS_HOOKS']['editDocument'])) {
		foreach($GLOBALS['SEEDDMS_HOOKS']['editDocument'] as $hookObj) {
			if (method_exists($hookObj, 'preSetKeywords')) {
				$hookObj->preSetKeywords(null, array('document'=>$document, 'keywords'=>&$keywords, 'oldkeywords'=>&$oldkeywords));
			}
		}
	}

	if($document->setKeywords($keywords)) {
	}
	else {
		UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("error_occured"));
	}

	if(isset($GLOBALS['SEEDDMS_HOOKS']['editDocument'])) {
		foreach($GLOBALS['SEEDDMS_HOOKS']['editDocument'] as $hookObj) {
			if (method_exists($hookObj, 'postSetKeywords')) {
				$hookObj->postSetKeywords(null, array('document'=>$document, 'keywords'=>&$keywords, 'oldkeywords'=>&$oldkeywords));
			}
		}
	}
}

$oldcategories = $document->getCategories();
if($categories) {
	$categoriesarr = array();
	foreach($categories as $catid) {
		if($cat = $dms->getDocumentCategory($catid)) {
			$categoriesarr[] = $cat;
		}
		
	}
	$oldcatsids = array();
	foreach($oldcategories as $oldcategory)
		$oldcatsids[] = $oldcategory->getID();

	if (count($categoriesarr) != count($oldcategories) ||
			array_diff($categories, $oldcatsids)) {
		if(isset($GLOBALS['SEEDDMS_HOOKS']['editDocument'])) {
			foreach($GLOBALS['SEEDDMS_HOOKS']['editDocument'] as $hookObj) {
				if (method_exists($hookObj, 'preSetCategories')) {
					$hookObj->preSetCategories(null, array('document'=>$document, 'categories'=>&$categoriesarr, 'oldcategories'=>&$oldcategories));
				}
			}
		}
		if($document->setCategories($categoriesarr)) {
		} else {
			UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("error_occured"));
		}
		if(isset($GLOBALS['SEEDDMS_HOOKS']['editDocument'])) {
			foreach($GLOBALS['SEEDDMS_HOOKS']['editDocument'] as $hookObj) {
				if (method_exists($hookObj, 'postSetCategories')) {
					$hookObj->postSetCategories(null, array('document'=>$document, 'categories'=>&$categoriesarr, 'oldcategories'=>&$oldcategories));
				}
			}
		}
	}
} elseif($oldcategories) {
	if(isset($GLOBALS['SEEDDMS_HOOKS']['editDocument'])) {
		foreach($GLOBALS['SEEDDMS_HOOKS']['editDocument'] as $hookObj) {
			if (method_exists($hookObj, 'preSetCategories')) {
				$hookObj->preSetCategories(null, array('document'=>$document, 'categories'=>array(), 'oldcategories'=>&$oldcategories));
			}
		}
	}
	if($document->setCategories(array())) {
	} else {
		UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("error_occured"));
	}
	if(isset($GLOBALS['SEEDDMS_HOOKS']['editDocument'])) {
		foreach($GLOBALS['SEEDDMS_HOOKS']['editDocument'] as $hookObj) {
			if (method_exists($hookObj, 'postSetCategories')) {
				$hookObj->postSetCategories(null, array('document'=>$document, 'categories'=>array(), 'oldcategories'=>&$oldcategories));
			}
		}
	}
}

$oldattributes = $document->getAttributes();
if($attributes) {
	foreach($attributes as $attrdefid=>$attribute) {
		$attrdef = $dms->getAttributeDefinition($attrdefid);
		if($attribute) {
			if(!$attrdef->validate($attribute)) {
				$errmsg = getAttributeValidationText($attrdef->getValidationError(), $attrdef->getName(), $attribute);
				UI::exitError(getMLText("document_title", array("documentname" => $document->getName())), $errmsg);
			}
			if(!isset($oldattributes[$attrdefid]) || $attribute != $oldattributes[$attrdefid]->getValue()) {
				if(!$document->setAttributeValue($dms->getAttributeDefinition($attrdefid), $attribute))
					UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("error_occured"));
			}
		} elseif($attrdef->getMinValues() > 0) {
			UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("attr_min_values", array("attrname"=>$attrdef->getName())));
		} elseif(isset($oldattributes[$attrdefid])) {
			if(!$document->removeAttribute($dms->getAttributeDefinition($attrdefid)))
				UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("error_occured"));
		}
	}
}
foreach($oldattributes as $attrdefid=>$oldattribute) {
	if(!isset($attributes[$attrdefid])) {
		if(!$document->removeAttribute($dms->getAttributeDefinition($attrdefid)))
			UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("error_occured"));
	}
	
}

if($sequence != "keep") {
 	if($document->setSequence($sequence)) {
	}
	else {
		UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("error_occured"));
	}
}

$session->setSplashMsg(array('type'=>'success', 'msg'=>getMLText('splash_document_edited')));

add_log_line("?documentid=".$documentid);
header("Location:../out/out.ViewDocument.php?documentid=".$documentid);

?>
