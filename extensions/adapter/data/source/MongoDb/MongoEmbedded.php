<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2012, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_embedded\extensions\adapter\data\source\MongoDb;

use Mongo;
use MongoId;
use MongoCode;
use MongoDate;
use MongoRegex;
use MongoBinData;
use lithium\util\Inflector;
use lithium\util\Set;
use lithium\core\Libraries;
use lithium\core\NetworkException;
use Exception;

/**
 * A data source adapter which allows you to connect to the MongoDB database with support for
 * embedded documents.
 *
 * adding options for embbedded documents - Lith supports this one level deep, but does not working if embedded document has a with
 * to use, simply set 
 * 1. $meta['source'] == to the parent model 
 * 2. $meta['embedded'] == to the field this model should populate from 
 * ex: 
 * protected $_meta = array(
 * 	'source' => 'Car',
 * 	'embedded'	=> 'wheels',
 * );
 *
 * @see lithium\data\source\MongoDb
 */
class MongoEmbedded extends \lithium\data\source\MongoDb {

	public function __construct(array $config = array()) {
		parent::__construct($config);
		$this->_readEmbeddedFilter();		
	}

	public function read($query, array $options = array()) {
		if(!empty($options['data'])){
			$params = compact('query', 'options');
			$_config = $this->_config;
			return $this->_filter(__METHOD__, $params, function($self, $params) use ($_config) {
				$query = $params['query'];			
				$config = compact('query') + array('class' => 'set');
				return $self->item($params['query']->model(), $params['options']['data'], $config);
			});
		}

		return parent::read($query, $options);
	}

	protected function _readEmbeddedFilter(){
		// filter for relations
		self::applyFilter('read', function($self, $params, $chain) {
		
			$results = $chain->next($self, $params, $chain);

			if(isset($params['options']['with']) && !empty($params['options']['with'])){
	
				$model = is_object($params['query']) ? $params['query']->model() : null;

				$relations = $model::relations();

				foreach($params['options']['with'] as $k => $name){

					if(isset($relations[$name])){
						$relation = $relations[$name]->data();

						$relationModel = Libraries::locate('models', $relation['to']);

						if(!empty($relationModel) && !empty($results) && isset($relation['embedded'])){
							$embedded_on = $relation['embedded'];

							$resultsArray = $results->to('array');

							foreach($resultsArray as $k => $result){								
		
								$relationalData = Set::extract($result, '/'.str_replace('.', '/', $embedded_on));

								if(!empty($embedded_on)){

									$keys = explode('.', $embedded_on);

									$lastKey = array_slice($keys, -1, 1);
									$lastKey = $lastKey[0];

									$data = array();

									foreach($relationalData as $rk => $rv){
										if(!empty($rv)){
											if(!is_array($rv)){
												$data[$rk] = $rv;
											} else if (isset($rv[$lastKey]) && !empty($rv[$lastKey])){
												$data[$rk] = $rv[$lastKey];											
											}
										}
									}
									
									if(!empty($data)){
										// TODO : Add support for conditions, fields, order, page, limit
										$validFields = array_fill_keys(array('with'), null);

										$options = array_intersect_key($relation, $validFields);

										$options['data'] = $data;

										if($relation['type'] == 'hasMany'){
											$type = 'all';
										} else {
											$type = 'first';
										}

										$relationResult = $relationModel::find($type, $options);

									} else {
										if($relation['type'] == 'hasMany'){
											$relationResult = $self->item($relationModel, array(), array('class' => 'set'));
										} else {
											$relationResult = null;
										}
									}

									// if fieldName === true, use the default lithium fieldName. 
									// if fieldName != relationName, then it was manually set, so use it
									// else, just use the embedded key
									$relationName = ($relation['type'] == 'hasOne') ? Inflector::pluralize($relation['name']) : $relation['name'];
									if($relation['fieldName'] === true){
										$relation['fieldName'] = lcfirst($relationName);
										$keys = explode('.', $relation['fieldName']);
									} else if ($relation['fieldName'] != lcfirst($relationName)){
										$keys = explode('.', $relation['fieldName']);
									}
									
									$ref = $results[$k];
									foreach($keys as $k => $key){
										if(!isset($ref->$key)){
											$ref->$key = $self->item(null, array(), array('class' => 'entity'));
										}
										if(count($keys) - 1 == $k){
											$ref->$key = $relationResult;
										} else {
											$ref = $ref->$key;		
										}
									}

								}

							}

						}

					}

				}

			}

			return $results;

		});
	}

}

?>