# [Lithium PHP](http://lithify.me) Plugin to allow embedded relations for MongoDB

This is in its early stages and only supports READ opperations. Hopefully, this plugin will not be needed for very long as li3 plans to support embedded relations in the core.

A plugin to add support to li3 embedded relations for MongoDb. Lithiums mongo adapter says it supports embedded relations, but after much investigation throught the core it appears this is not the case. Basically, when an embedded relation is specified, it does the single query for the parent and when the data is returned it creates the appropiate model with the data returned from the parent.

## Installation

### Use Composer
Modify your projects `composer.json` file

~~~ json
{
    "require": {
    	...
        "brandonwestcott/li3_embedded": "master"
        ...
    }
}
~~~

Run `./composer.phar install` which will install this librarie into your app/libraries

### Alternately, just clone, download or submodule
1. Clone/Download/submodule the plugin into your app's ``libraries`` directory.
2. Tell your app to load the plugin by adding the following to your app's ``config/bootstrap/libraries.php``:

## Usage

Add the plugin in your `config/bootstrap/libraries.php` file:

~~~ php
<?php
	Libraries::add('li3_embedded');
?>
~~~

Next, in your app/config/connections.php specify this extended MongoDB adapter.
~~~ php
Connections::add('default', array(
	'type' => 'MongoDb', 
	'adapter' => 'MongoEmbedded', 
	'host' => 'localhost', 
	'database' => 'foo'
));
~~~

#### Using an Embedded Relation

Continue defining relations in the lithium specified way as described [here](http://lithify.me/docs/manual/working-with-data/relationships.wiki), except for the embedded key

~~~ php
class Team extends  \lithium\data\Model.php {

	public $hasMany = array(
		'Players' => array(
			'to' 	   => 'Players',
			'embedded' => 'players'
 		),
 		'Scouts' => array(
 			'to' => 'Scouts',
 			'embedded' => 'miscellaneous.offseason.scouts',
 			'fieldName' => 'scouts',
 		),
	);

~~~

Key specified is the name used to refernce the relation on a find query.

Options are:  
to - specifieds target model  
embedded  - the key on which the data is embedded  
fieldName - the key on which the related model will be attached (in the above example the nested scouts DocumentSet would be embedded onto $team->scouts)  


#### Calling Relations

Relations are called in the lithium specified way as described [here](http://lithify.me/docs/manual/working-with-data/relationships.wiki)

~~~ php
Team::find('first', array(
	'_id' => 1,
	'with' => array(
		'Players',
	),
));
~~~

This would return a Document of a Team. On the team would exists players, which would be an instance of the Players model. (Debating to make the reference live in a magic method vs a property - any input is welcome, aka ->players())

hasOnes will be set as a Document.

hasManies will be set as a DocumentSet.

However, when no data is returned, the behavior is slightly different. An empty hasOne will return null, as is the behavior of calling Model::find('first'). An empty hasMany will continue to return an empty DocumentSet, as is the behavior of calling Model::find('all').


## Some Notes
1. Beta Beta Beta - Currently, this plugin is being used heavily in a read MongoDB environment. However, writes will likely majorly screw up your db. Use with caution.

## Plans for the future
Hopefully this plugin has a short future. This was a quick solution that allowed us not to hack core li3. I hope to move this work into a fork of the core and contribute there.