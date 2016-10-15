<?php
// gettextjson /var/www/mywebpage or gettextjson
// search for gettext.json in path passed or in current path if none has been passed
//
// gettext.json example:
// {
//	"search-paths"		: ["app/controllers","app/views"],
//	"search-extensions"	: ["php","html"],
//	"search-language"	: ["php"],
//	"search-paterns"	: ["_($1)","t($1)"],
//	"output-path"		: "app/lang/en.json"
// }

// Join Paths
function joinPaths($arr) {
	$path = implode('/', $arr);
	$path = str_replace('///', '/', $path);
	$path = str_replace('//', '/', $path);

	return $path;
}

// Parse gettext.json
function getJson($path) {
	$out = null;
	$buf = @file_get_contents($path);

	if($buf) {
		$out = json_decode($buf, true);
	}

	return $out;
}

// Find sub folders (recursive)
function findSubfolders($paths, $maxDeep=20, $deep=0) {
	$allPaths = $paths;
	
	if($maxDeep == $deep) {
		return $allPaths;	
	}
	
	foreach($paths as $path) {
		$allSubPaths = glob(joinPaths([$path , '*']),GLOB_ONLYDIR);
		$allSubPaths = findSubfolders($allSubPaths, $maxDeep, $deep + 1);
		$allPaths = array_merge($allPaths, $allSubPaths);
	}

	return $allPaths;
}

// Fetch text 
// From $config['search-paths']
// Based on $config['search-paterns']
function fetchText($config) {
	$searchPath = findSubfolders($config['search-paths']);
	$extensionStr = '{'. implode(',', $config['search-extensions']) . '}';

	$mapKeys = [];

	print("Fetching Text...\n");

	foreach($searchPath as $path) {
		if($config['debug'] == 'true' || $config['debug'] == 1) {
			print("\t" . $path . "\n");		
		}

		$fileList = glob(joinPaths([$path, '*.']) . $extensionStr, GLOB_BRACE);
		
		foreach($fileList as $filename) {
			$buf = file_get_contents($filename);

			// regexp php tags 
			preg_match_all('/<\?([\s\S]+?)\?>/',$buf, $matches);
			$buf = implode("\n", $matches[1]);

			// regexp paterns
			foreach($config['search-paterns'] as $patern) {
				$matches = [];
				$patern = str_replace('$1',"([\s\S]+?)", $patern);
				preg_match_all('/[^\w]' . $patern . '/', $buf, $matches);
				$matches = $matches[1];

				$keys = array_fill_keys($matches, '');
				$mapKeys = array_merge($mapKeys, $keys);
			}
		}
	}

	print("\n\n[Done]\n\n");	

	return $mapKeys;
}

// Merge and compare new key map with last one
function fillNewMapText(&$mapTextNew, $mapTextOri, &$newKeys, &$deprecatedKeys) {
	foreach($mapTextNew as $key => $dummy) {
		if(isset($mapTextOri[$key])) {
			$mapTextNew[$key] = $mapTextOri[$key];
			unset($mapTextOri[$key]);
		} else {
			$mapTextNew[$key] = $key;
			$newKeys[] = $key;
		}
	}
	$deprecatedKeys = $mapTextOri;
}

// Save to Json file
function saveOutput($map, $outputPath) {
	return file_put_contents($outputPath . '_tmp', json_encode($map));
}

// MAIN
$configPath = './';

switch($argc) {
	case 2:
		$configPath = $argv[1];
	case 1:
		// get configuration
		$configPath = joinPaths([$configPath, 'gettext.json']);

		$config = getJson($configPath);
		if(! $config) {
			print("'$configPath' was not found!\n");
		}
		
		//TODO: Validate configuration

		// find all text entries
		$mapTextNew = fetchText($config);

		// get last text entries
		$mapTextOri = getJson($config['output-path']);
		if($mapTextOri == null) {
			$mapTextOri = [];
		}

		$newKeys = [];
		$deprecatedKeys = [];

		// compare last with new and find what new and wats deprecated
		fillNewMapText($mapTextNew, $mapTextOri, $newKeys, $deprecatedKeys);

		// save to json file
		$res = saveOutput($mapTextNew, $config['output-path']);
		if(! $res) {
			print("Output file could not be saved!\n");
		} else {
			// show some information
			print("Output file saved at: " . $config['output-path'] . "_tmp \n");
			
			print("New keys:\n\t");
			print(implode("\n\t", $newKeys));
			
			print("\nDeprecated keys:\n\t");
			print(implode("\n\t", $deprecatedKeys));
			
			print("\nTotal items found: " . count($mapTextNew) . "\n");
		}

		print("\n");
		break;
	default:
		print("
			Too many options.
			Example: gettextjson /var/www/mywebpage or gettextjson
		");
		break;
}
