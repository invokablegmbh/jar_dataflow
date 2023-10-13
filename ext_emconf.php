<?php

$EM_CONF['jar_dataflow'] = array(
	'title' => 'Dataflow',
	'description' => 'Extends content elements with a query builder to output data from arbitrary list elements',
	'category' => 'plugin',
	'author' => 'invokable GmbH',
	'author_email' => 'info@invokable.gmbh',
	'version' => '1.0.20',
	'state' => 'stable',
	'internal' => '',
	'uploadfolder' => '0',
	'createDirs' => '',
	'clearCacheOnLoad' => 0,
	'constraints' => array(
		'depends' => array(
			'typo3' => '10.4.0-11.5.99',
			'php' => '7.4.0-7.4.999',
		),
		'conflicts' => array(
		),
		'suggests' => array(
		),
	),
);
