<?php

namespace FeideConnect\Data\Models;

use Cassandra\Type\Uuid;
use Cassandra\Type\CollectionList;
use Cassandra\Type\CollectionSet;
use Cassandra\Type\Base;
use Cassandra\Type\Timestamp;
use Cassandra\Type\Blob;

use FeideConnect\Utils\Misc;

/*
CREATE TABLE feideconnect.organizations (
    id text PRIMARY KEY,
    kindid int,
    logo blob,
    logo_updated timestamp,
    name map<text, text>,
    organization_number text,
    realm text,
    type set<text>
)
 */

class Organization extends \FeideConnect\Data\Model {

	public $id, $name, $realm, $type, $uiinfo, $service;

	protected static $_properties = array(
		"id", "name", "realm", "type", "uiinfo", "service"
	);
	protected static $_types = [
	];



	function __construct($props) {

		parent::__construct($props);

		if (isset($props["uiinfo"])) {
			$this->uiinfo = json_decode($props["uiinfo"], true);
			unset ($props["uiinfo"]);
		}




	}

	public function getTypes() {
		$t = [];
		foreach($this->type AS $type) {
			switch($type) {
				case 'primary_and_lower_secondary':
					$t[] = 'go'; break;
				case 'upper_secondary':
					$t[] = 'vgs'; break;
				case 'higher_education':
					$t[] = 'he'; break;
				// case 'service_provider':
				// 	$t[] = 'sp'; break;
			}

		}
		return $t;

	}

	public function distance($lat, $lon) {

		if (!isset($this->uiinfo)) { return null; }
		if (!isset($this->uiinfo["geo"])) { return null; }
		if (!is_array($this->uiinfo["geo"])) { return null; }

		$distance = 9999;
		foreach($this->uiinfo["geo"] AS $geoitem) {
			$dc = Misc::distance($lat, $lon, $geoitem["lat"], $geoitem["lon"]);
			if ($dc < $distance) {
				$distance = $dc;
			}
		}
		return $distance;
	}


	public function getOrgInfo($lat = null, $lon = null) {

		$res = [];
		$prepared = parent::getAsArray();
		$res["id"] = $prepared["realm"];
		$res["type"] = $this->getTypes();

		$lang = Misc::get_browser_language(array_keys($prepared["name"]));
		$res["title"] = $prepared["name"][$lang];
		$res["uiinfo"] = $prepared["uiinfo"];

		if ($lat !== null && $lon !== null) {
			$res["distance"] = $this->distance($lat, $lon);
		}
		
		return $res;
	}

	public function isHomeOrg() {
		return $this->hasType("home_organization");
	}

	public function hasType($type) {
		if ($this->type === null) { return false; }
		return in_array($type, $this->type);
	}

	public function getStorableArray() {

		$prepared = parent::getStorableArray();
		$prepared["uiinfo"] = json_encode($this->uiinfo);

		// if (isset($this->redirect_uri)) {
		// 	$prepared["redirect_uri"] =  new CollectionList($this->redirect_uri, Base::ASCII);
		// }
		// if (isset($this->scopes)) {
		// 	$prepared["scopes"] =  new CollectionSet($this->scopes, Base::ASCII);
		// }
		// if (isset($this->scopes_requested)) {
		// 	$prepared["scopes_requested"] =  new CollectionSet($this->scopes_requested, Base::ASCII);
		// }
		// if (isset($this->status)) {
		// 	$prepared["status"] =  new CollectionSet($this->status, Base::ASCII);
		// }
		

		return $prepared;
	}



}