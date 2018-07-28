<?php
	// Test suite.
	// (C) 2018 CubicleSoft.  All Rights Reserved.

	if (!isset($_SERVER["argc"]) || !$_SERVER["argc"])
	{
		echo "This file is intended to be run from the command-line.";

		exit();
	}

	// Temporary root.
	$rootpath = str_replace("\\", "/", dirname(__FILE__));

	require_once $rootpath . "/../support/random.php";

	echo "Testing CSPRNG constructor...";
	$rng = new CSPRNG();
	$rng2 = new CSPRNG(true);
	echo " [Passed]\n";
	echo "\n";

	echo "Random token:  " . $rng->GenerateToken(128) . "\n";
	echo "Random token:  " . $rng2->GenerateToken(128) . "\n";
	echo "\n";

	echo "Random number (1-100):  " . $rng->GetInt(1, 100) . "\n";
	echo "Random number (1-100):  " . $rng2->GetInt(1, 100) . "\n";
	echo "\n";

	echo "Random alphanumeric string:  " . $rng->GenerateString(64) . "\n";
	echo "Random alphanumeric string:  " . $rng2->GenerateString(64) . "\n";
	echo "\n";

	echo "Loading en-us frequency map with length/threshold 3...";
	$freqmap = json_decode(file_get_contents($rootpath . "/../support/en_us_freq_3.json"), true);
	echo " [Done]\n";
	echo "\n";

	echo "Randomly generated word:  " . $rng->GenerateWord($freqmap, 8) . "\n";
	echo "Randomly generated word:  " . $rng2->GenerateWord($freqmap, 8) . "\n";
	echo "\n";

	echo "Randomly generated password:  " . $rng->GenerateWord($freqmap, $rng->GetInt(4, 7)) . "-" . $rng->GenerateWord($freqmap, $rng->GetInt(4, 7)) . "-" . $rng->GenerateWord($freqmap, $rng->GetInt(4, 7)) . "\n";
?>