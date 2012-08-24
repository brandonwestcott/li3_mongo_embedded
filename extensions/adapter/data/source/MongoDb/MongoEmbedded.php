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

		if(isset($options['embeddedOn']) && isset($options['embeddedOn']['source']) && isset($options['embeddedOn']['key'])){
			$embeddedOn = Libraries::locate('models', $options['embeddedOn']['source']);
			$relation = $embeddedOn::relations($options['embeddedOn']['key']);
			if(!empty($relation)){
				$relation = $relation->data();

				if(isset($relation['embedded'])){
					$embeddedKey = $relation['embedded'];
					$newOptions = $options;

					foreach(array('conditions', 'fields', 'order') as $key){
						if(!empty($options[$key])){
							unset($newOptions[$key]);
							foreach($options[$key] as $k => $v){
								if(is_int($k)){
									$newVal = $embeddedKey.'.'.$v;
									$newOptions[$key][] = $newVal;		
								} else {
									$newKey = $embeddedKey.'.'.$k;
									$newOptions[$key][$newKey] = $v;			
								}
							}
						}
						if($key == 'fields' && (!isset($newOptions['fields']) || empty($newOptions['fields']))){
							$newOptions['fields'][] = $embeddedKey;
						}			
					}

					$newOptions['source'] = $embeddedOn::meta('source');

					$options['embeddedKey'] = $embeddedKey;

					$query = $options['model']::invokeMethod('_instance', array('query', $newOptions));
				}
			}		
		}

		return parent::read($query, $options);
	}

	protected function _readEmbeddedFilter(){
		// filter for relations
		self::applyFilter('read', function($self, $params, $chain) {
		
			$results = $chain->next($self, $params, $chain);

			if(isset($params['options']['embeddedKey']) && !empty($params['options']['embeddedKey'])){
				$newResults = array();

				foreach($results as $k => $result){
					$result = Set::extract($result->to('array'), '/'.str_replace('.', '/', $params['options']['embeddedKey']));
					$newResult = array();

					if(!empty($result)){
						$lastKey = array_pop(explode('.', $params['options']['embeddedKey']));
						foreach($result as $rk => $rv){
							if(isset($rv[$lastKey]) && is_array($rv[$lastKey])){
								$newResult[$rk] = $self->item($params['query']->model(), $rv[$lastKey], array('class' => 'entity'));
							}
						}
					}
	
					$newResults[$k] = $self->item($params['query']->model(), $newResult, array('class' => 'set'));
				}

				$results = $self->item($params['query']->model(), $newResults, array('class' => 'set'));
			}

			if(isset($params['options']['with']) && !empty($params['options']['with'])){
	
				$model = is_object($params['query']) ? $params['query']->model() : null;

				$relations = $model::relations();

				foreach($params['options']['with'] as $k => $name){

					if(isset($relations[$name])){
						$relation = $relations[$name]->data();

						$relationModel = Libraries::locate('models', $relation['to']);

						if(!empty($relationModel) && !empty($results) && isset($relation['embedded'])){
							$embedded_on = $relation['embedded'];

							foreach($results as $k => $result){								
		
								$relationalData = Set::extract($result->to('array'), '/'.str_replace('.', '/', $embedded_on));

								if(!empty($embedded_on)){

									$keys = explode('.', $embedded_on);

									$lastKey = $keys;
									$lastKey = array_pop($lastKey);

									foreach($relationalData as $rk => $rv){
										if(isset($rv[$lastKey]) && is_array($rv[$lastKey])){
											$relationalData[$rk] = $rv[$lastKey];
										}
									}

									if(!empty($relationalData)){
										// TODO : Add support for conditions, fields, order, page, limit
										$validFields = array_fill_keys(array('with'), null);

										$options = array_intersect_key($relation, $validFields);

										$options['data'] = $relationalData;

										if($relation['type'] == 'hasMany'){
											$type = 'all';
										} else {
											$type = 'first';
										}

										$relationResult = $relationModel::find($type, $options);

									} else {
										if($relation['type'] == 'hasMany'){
											$type = 'set';
										} else {
											$type = 'entity';
										}

										$relationResult = $self->item($query->model(), array(), array('type' => $type));
									}

									// if fieldName === true, use the default lithium fieldName. 
									// if fieldName != relationName, then it was manually set, so use it
									// else, just use the embedded key
									if($relation['fieldName'] === true){
										$relation['fieldName'] = lcfirst($relation['name']);
										$keys = explode('.', $relation['fieldName']);
									} else if ($relation['fieldName'] != lcfirst($relation['name'])){
										$keys = explode('.', $relation['fieldName']);
									}

									// there has got to be a better way to do this
									switch (count($keys)) {
										case 1:
											$results[$k]->$keys[0] = $relationResult;
											break;
										case 2:
											$results[$k]->$keys[0]->$keys[1] = $relationResult;
											break;	
										case 3:
											$results[$k]->$keys[0]->$keys[1]->$keys[2] = $relationResult;
											break;
										case 4:
											$results[$k]->$keys[0]->$keys[1]->$keys[2]->$keys[3] = $relationResult;
											break;
										case 5:
											$results[$k]->$keys[0]->$keys[1]->$keys[2]->$keys[3]->$keys[4] = $relationResult;
											break;
										case 6:
											$results[$k]->$keys[0]->$keys[1]->$keys[2]->$keys[3]->$keys[4]->$keys[5] = $relationResult;	
											break;

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