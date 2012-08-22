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

		// filter for relations
		self::applyFilter(__FUNCTION__, function($self, $params, $chain) {
		
			$results = $chain->next($self, $params, $chain);

			if(isset($params['options']['with']) && !empty($params['options']['with'])){
	
				$model = is_object($params['query']) ? $params['query']->model() : null;

				$relations = $model::relations();

				foreach($params['options']['with'] as $k => $name){

					if(isset($relations[$name])){
						$relation = $relations[$name]->data();

						$relationModel = Libraries::locate('models', $relation['class']);

						if(!empty($relationModel)){
							$key = $relationModel::meta('embedded');

							foreach($results as $k => $result){								
	
								if(!empty($key) && !empty($result->$key)){
									// TODO : Add support for conditions, fields, order, page, limit
									$validFields = array_fill_keys(array('with'), null);

									$options = array_intersect_key($relation, $validFields);

									$options['data'] = $result->$key->to('array');

									if($relation['type'] == 'hasMany'){
										$type = 'all';
									} else {
										$type = 'first';
									}

									$results[$k]->$key = $relationModel::find($type, $options);
									
								} else {
									if($relation['type'] == 'hasMany'){
										$type = 'set';
									} else {
										$type = 'entity';
									}

									$results[$k]->$key = $self->item($query->model(), array(), array('type' => $type));

								}

							}

						}

					}

				}

			}

			return $results;

		});		

		return parent::read($query, $options);
	}

}

?>