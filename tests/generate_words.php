<?php
	// CSPRNG frequency map tester.
	// (C) 2018 CubicleSoft.  All Rights Reserved.

	if (!isset($_SERVER["argc"]) || !$_SERVER["argc"])
	{
		echo "This file is intended to be run from the command-line.";

		exit();
	}

	$rootpath = str_replace("\\", "/", dirname(__FILE__));

	if ($argc < 4)
	{
		echo "CSPRNG frequency map tester\n";
		echo "Purpose:  Take input file containing a frequency map generated by 'src/process_dictionary.php', generate sample words of various lengths with CSPRNG::GenerateWord(), and track system resource usage.\n";
		echo "\n";
		echo "Syntax:  " . $argv[0] . " inputfile minlength maxlength\n";
		echo "\n";
		echo "Examples:\n";
		echo "\tphp " . $argv[0] . " en_us_freq_3.json 1 15\n";
		echo "\tphp " . $argv[0] . " en_us_freq_4.json 4 25\n";

		exit();
	}

	// Make sure PHP doesn't introduce weird limitations for the test.
	ini_set("memory_limit", "-1");
	set_time_limit(0);

	echo "Memory usage (before load):  " . memory_get_usage(true) . "\n";
	$ts = microtime(true);
	$freqmap = json_decode(file_get_contents($argv[1]), true);
	echo "Time taken:  " . (microtime(true) - $ts) . " sec\n";
	echo "Memory usage (after load):  " . memory_get_usage(true) . "\n";
	echo "\n";

	require_once $rootpath . "/../support/random.php";

	$rng = new CSPRNG();
	for ($len = (int)$argv[2]; $len <= (int)$argv[3]; $len++)
	{
		$ts = microtime(true);
		$word = $rng->GenerateWord($freqmap, $len);
		echo "Time taken:  " . (microtime(true) - $ts) . " sec\n";
		echo "Word:  " . $word . "\n";
		echo "\n";
	}

	echo "Generating some passwords (each one is three words at 4-8 characters each):\n\n";
	$ts = microtime(true);
	for ($x = 0; $x < 10; $x++)
	{
		echo "\t" . $rng->GenerateWord($freqmap, $rng->GetInt(4, 8)) . "-" . $rng->GenerateWord($freqmap, $rng->GetInt(4, 8)) . "-" . $rng->GenerateWord($freqmap, $rng->GetInt(4, 8)) . "\n";
	}
	echo "\n";
	echo "Time taken:  " . (microtime(true) - $ts) . " sec\n";
	echo "\n";

	echo "Final memory usage:  " . memory_get_usage(true) . "\n";
?>