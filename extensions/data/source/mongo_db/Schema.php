<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2012, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_mongo_embedded\extensions\data\source\mongo_db;

class Schema extends \lithium\data\source\mongo_db\Schema {

	protected $_classes = array(
		'entity'       => 'li3_mongo_embedded\extensions\data\entity\Document',
		'set'          => 'li3_mongo_embedded\extensions\data\collection\DocumentSet',
	);

}

?>