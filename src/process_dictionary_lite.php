<?php
	// CSPRNG-compliant character sequence frequency generator.
	// (C) 2018 CubicleSoft.  All Rights Reserved.

	if (!isset($_SERVER["argc"]) || !$_SERVER["argc"])
	{
		echo "This file is intended to be run from the command-line.";

		exit();
	}

	$rootpath = str_replace("\\", "/", dirname(__FILE__));

	if ($argc < 4)
	{
		echo "CSPRNG-compliant character frequency generator\n";
		echo "Purpose:  Take an input UTF-8 dictionary file containing one word per line and generate character frequencies for later use with CSPRNG::GenerateWordLite().\n";
		echo "\n";
		echo "Syntax:  " . $argv[0] . " inputfile vowels outputfile\n";
		echo "\n";
		echo "Examples:\n";
		echo "\tphp " . $argv[0] . " dictionary.txt aeiouy en_us_lite.json\n";

		exit();
	}

	require_once $rootpath . "/support/utf8.php";

	// Make sure PHP doesn't introduce weird limitations.
	ini_set("memory_limit", "-1");
	set_time_limit(0);

	$result = array("consonants" => array(), "vowels" => array());
	$fp = fopen($argv[1], "rb");
	if ($fp === false)
	{
		echo "Unable to open '" . $argv[1] . "' for reading.\n";

		exit();
	}

	$vowels = array();
	$vowels2 = preg_split('//u', $argv[2], -1, PREG_SPLIT_NO_EMPTY);
	foreach ($vowels2 as $chr)  $vowels[$chr] = true;

	while (($word = fgets($fp)) !== false)
	{
		$word = strtolower(trim($word));
		$word2 = preg_replace('/[^a-z\x80-\xff]/', "", $word);
		if ($word === $word2 && UTF8::IsValid($word))
		{
			$num = 0;
			$pos = 0;
			$size = 0;
			$y = strlen($word);
			while (UTF8::NextChrPos($word, $y, $pos, $size))
			{
				$newchr = substr($word, $pos, $size);

				if (isset($vowels[$newchr]))
				{
					if (!isset($result["vowels"][$newchr]))  $result["vowels"][$newchr] = 0;

					$result["vowels"][$newchr]++;
				}
				else
				{
					if (!isset($result["consonants"][$newchr]))  $result["consonants"][$newchr] = 0;

					$result["consonants"][$newchr]++;
				}
			}
		}
	}

	fclose($fp);

	// Prettify the result.
	ksort($result["consonants"]);
	ksort($result["vowels"]);

	// Converts data to a human-readable JSON string.
	function ConvertDataToUserJSON($data, $compact = 0)
	{
		$data = preg_replace_callback('/^\s+/m', function ($match) { return str_repeat("\t", strlen($match[0]) / 4); }, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

		if ($compact > 0)
		{
			$data = explode("\n", $data);
			$lines = array();
			$lastline = false;
			foreach ($data as $line)
			{
				$line2 = trim($line);
				if ($line2 !== "")
				{
					if ($line2[0] !== "]" && $line2[0] !== "}" && substr($line2, -1) !== "{" && substr($line2, -1) !== "[")
					{
						$y = strlen($line2);
						if ($lastline === false && $y < $compact)  $lastline = $line;
						else if (strlen($lastline) + $y < $compact)  $lastline .= " " . $line2;
						else
						{
							$lines[] = $lastline;

							if ($y < $compact)  $lastline = $line;
							else
							{
								$lastline = false;
								$lines[] = $line;
							}
						}
					}
					else
					{
						if ($lastline === false)  $lines[] = $line;
						else if (($line2[0] === "]" || $line2[0] === "}") && in_array(substr($lines[count($lines) - 1], -1), array("{", "[")))
						{
							$lines[count($lines) - 1] .= " " . trim($lastline) . " " . $line2;
							$lastline = false;
						}
						else
						{
							$lines[] = $lastline;
							$lastline = false;

							$lines[] = $line;
						}
					}
				}
			}

			$data = implode("\n", $lines);
		}

		return $data;
	}

	// Generate mostly compact JSON (balance between readability and file size).
	$data = ConvertDataToUserJSON($result, 1000);

	// Output the result.
	file_put_contents($argv[3], $data);
?>