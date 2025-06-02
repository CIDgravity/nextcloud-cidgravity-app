<?php

return [
	'resources' => [
		'cidgravity' => ['url' => '/cidgravity']
	],
	'ocs' => [
		['name' => 'externalStorage#getExternalStorageConfigurationForSpecificFile', 'url' => '/get-external-storage-config', 'verb' => 'GET'],
		['name' => 'externalStorage#getMetadataForSpecificFile', 'url' => '/get-file-metadata', 'verb' => 'GET'],
	],
];
