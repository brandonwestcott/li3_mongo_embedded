<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2012, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_mongo_embedded\extensions\data\collection;

class DocumentArray extends \lithium\data\collection\DocumentArray {
	/**
	 * overwrites the current $_model to allow for lazy changing of relationships
	 *
	 * @var ref to model class
	 */
	public function setModel($model){
		$this->_model = $model;
		foreach($this->_data as $k => $v){
			if(method_exists($this->_data[$k], 'setModel')){
				$this->_data[$k]->setModel($model);			
			}
		}
	}

}

?>