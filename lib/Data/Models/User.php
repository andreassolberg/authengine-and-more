<?php

namespace FeideConnect\Data\Models;


/**
 * User 
 */
class User extends \FeideConnect\Data\Model {


	protected static $_properties = array(
		"userid", "created", "email", "name", 
		"profilephoto", "userid_sec", "userid_sec_seen", "selectedsource"
	);


	/**
	 * [setUserInfo description]
	 * @param [type] $sourceID     [description]
	 * @param [type] $name         [description]
	 * @param [type] $email        [description]
	 * @param [type] $profilephoto [description]
	 */
	public function setUserInfo($sourceID, $name = null, $email = null, $profilephoto = null) {

		if (empty($this->name)) $this->_properties['name'] = [];
		if (empty($this->email)) $this->_properties['email'] = [];
		if (empty($this->profilephoto)) $this->_properties['profilephoto'] = [];

		if (empty($sourceID)) throw new \Exception('Cannot set userinfo to a user without a sourceid.');

		if (!empty($name)) {
			$this->name[$sourceID] = $name;
		}
		if (!empty($email)) {
			$this->email[$sourceID] = $email;
		}
		if (!empty($profilephoto)) {
			$this->profilephoto[$sourceID] = $profilephoto;
		}

	}

	public function getSourcedProperty($name, $sourceID) {
		if (isset($this->{$name}) && is_array($this->name)) {
			if (isset($this->{$name}[$sourceID])) {
				return $this->{$name}[$sourceID];
			}
		}
		return null;
	}

	/**
	 * [getUserInfo description]
	 * @param  [type] $sourceID [description]
	 * @return [type]           [description]
	 */
	public function getUserInfo($sourceID = null) {

		$res = array();

		$src = null;
		if (!empty($this->selectedsource)) {
			$src = $this->selectedsource;
		}
		if ($sourceID !== null) {
			$src = $sourceID;
		}
		if ($src === null) {
			throw new \Exception('Cannot get user info from user without specifying source');
		}

		$res['name'] = $this->getSourcedProperty('name', $src);
		$res['email'] = $this->getSourcedProperty('email', $src);
		$res['profilephoto'] = $this->getSourcedProperty('profilephoto', $src);
		return $res;


	}


}