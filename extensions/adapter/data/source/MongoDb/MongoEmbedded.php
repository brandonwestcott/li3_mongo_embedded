<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2012, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_mongo_embedded\extensions\adapter\data\source\MongoDb;

use lithium\core\Libraries;

use lithium\util\collection\Filters;

/**
 * A data source adapter which allows you to connect to the MongoDB database with support for
 * embedded documents.
 *
 * @see lithium\data\source\MongoDb
 */
class MongoEmbedded extends \lithium\data\source\MongoDb {

	public function __construct(array $config = array()) {
		$this->_classes['entity']	 = 'li3_mongo_embedded\extensions\data\entity\Document';
		$this->_classes['array']	 = 'li3_mongo_embedded\extensions\data\collection\DocumentArray';
		$this->_classes['set']		 = 'li3_mongo_embedded\extensions\data\collection\DocumentSet';	
		parent::__construct($config);
		$this->_readEmbeddedFilter();		
	}

	/**
	 * Extended for full namesapce support
	 */
	public function relationship($class, $type, $name, array $config = array()) {
		if(isset($config['to'])){
			$config['to'] = Libraries::locate('models', $config['to']);
		}
		return parent::relationship($class, $type, $name, $config);
	}

	/**
	 * Hack for embedded data
	 */	
	public function read($query, array $options = array()) {
		if(!empty($options['embeddedData'])){
			$params = compact('query', 'options');
			return $this->_filter(__METHOD__, $params, function($self, $params) {
				return $params['options']['embeddedData'];
			});
		}
		return parent::read($query, $options);
	}

	protected function _readEmbeddedFilter(){

		$processRelations = function($data, $options, $model) use (&$processRelations) {

			if(isset($options['with']) && !empty($options['with'])){

				$relations = $model::relations();

				if(!empty($relations)){

					foreach($options['with'] as $key => $val){
						$relation = null;

						if(!is_int($key) && isset($relations[$key])){
							$relation = $relations[$key]->data();

							if(!empty($val)){
								$relation = array_merge($relation, $val);
							}
						} else if (!empty($val) && isset($relations[$val])) {
							$relation = $relations[$val]->data();
						}

						if(!empty($relation)){

							$relationModel = Libraries::locate('models', $relation['to']);

							if(!empty($relationModel) && isset($relation['embedded'])){
								$embedded_on = $relation['embedded'];

								if(!empty($embedded_on)){

									$records = method_exists($data, 'map') ? $data : array($data);

									foreach($records as $k => $record){

										$keys = explode('.', $embedded_on);

										$ref = $records[$k];
										foreach($keys as $k => $key){
											if(isset($ref->$key)){
												$ref = $ref->$key;		
											} else {
												continue 2;
											}
										}

										if(!empty($ref)){
											// TODO : Add support for conditions, fields, order, page, limit
											$ref->setModel($relationModel);

											if(isset($relation['with'])){
												// why find? because we need to be able to filter embedded models, everything is protected in li3, so this was the best way
												$ref = $relationModel::find('all', array(
													'embeddedData' => $processRelations($ref, $relation, $relationModel)
												));
											}
										}
									}
								}
							}
						}
					}
				}
			}

			return $data;
		};

		// filter for relations
		self::applyFilter('read', function($self, $params, $chain) use ($processRelations) {

			$results = $chain->next($self, $params, $chain);

			$queryOptions = $params['options'];

			$model = is_object($params['query']) ? $params['query']->model() : null;

			$results->applyFilter('_populate', function($self, $params, $chain) use ($queryOptions, $processRelations, $model) {

				$item = $chain->next($self, $params, $chain);
				
				if(!empty($item)){
					$item = $processRelations($item, $queryOptions, $model);
				}

				return $item;
			});

			return $results;

		});
	}

}

?>