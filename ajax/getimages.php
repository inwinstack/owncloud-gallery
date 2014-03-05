<?php
/**
 * Copyright (c) 2012 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

OCP\JSON::checkAppEnabled('gallery');

if (isset($_GET['token'])) {
	$token = $_GET['token'];
	$linkItem = \OCP\Share::getShareByToken($token);
	if (is_array($linkItem) && isset($linkItem['uid_owner'])) {
		// seems to be a valid share
		$type = $linkItem['item_type'];
		$fileSource = $linkItem['file_source'];
		$shareOwner = $linkItem['uid_owner'];
		$path = null;
		$rootLinkItem = \OCP\Share::resolveReShare($linkItem);
		$fileOwner = $rootLinkItem['uid_owner'];

		// Setup FS with owner
		OCP\JSON::checkUserExists($fileOwner);
		OC_Util::tearDownFS();
		OC_Util::setupFS($fileOwner);

		// The token defines the target directory (security reasons)
		$path = \OC\Files\Filesystem::getPath($linkItem['file_source']);

		$view = new \OC\Files\View(\OC\Files\Filesystem::getView()->getAbsolutePath($path));
		$images = $view->searchByMime('image');

		$result = array();
		foreach ($images as $image) {
			$result[] = $token . $image['path'];
		}

		OCP\JSON::setContentTypeHeader();
		echo json_encode(array('images' => $result, 'users' => array(), 'displayNames' => array()));

		exit;
	}
}

OCP\JSON::checkLoggedIn();
OCP\JSON::checkAppEnabled('gallery');

$images = \OCP\Files::searchByMime('image');
$user = \OCP\User::getUser();

foreach ($images as &$image) {
	// we show shared images another way
	if (substr($image['path'], 0, 8) === '/Shared/') {
		$owner = \OC\Files\Filesystem::getOwner($image['path']);
		$users[$owner] = $owner;
	}
	$path = $user . $image['path'];
	if (strpos($path, DIRECTORY_SEPARATOR . ".")) {
		continue;
	}
	$image['path'] = $user . $image['path'];
}

$displayNames = array();
foreach ($users as $user) {
	$displayNames[$user] = \OCP\User::getDisplayName($user);
}

function startsWith($haystack, $needle) {
	return !strncmp($haystack, $needle, strlen($needle));
}

$result = array();
foreach ($images as $image) {
	$result[] = $image['path'];
}

OCP\JSON::setContentTypeHeader();
echo json_encode(array('images' => $result, 'users' => $users, 'displayNames' => $displayNames));
