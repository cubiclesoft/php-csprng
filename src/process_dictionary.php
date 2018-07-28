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
		echo "CSPRNG-compliant character sequence frequency generator\n";
		echo "Purpose:  Take an input UTF-8 dictionary file containing one word per line and generate sequence frequencies for later use with CSPRNG::GenerateWord().\n";
		echo "\n";
		echo "Syntax:  " . $argv[0] . " inputfile length/threshold outputfile\n";
		echo "\n";
		echo "Examples:\n";
		echo "\tphp " . $argv[0] . " dictionary.txt 3 en_us_freq_3.json\n";
		echo "\tphp " . $argv[0] . " dictionary.txt 4 en_us_freq_4.json\n";

		exit();
	}

	require_once $rootpath . "/support/utf8.php";

	$threshold = (int)$argv[2];
	if ($threshold < 2)
	{
		echo "The length/threshold must be at least 2.\n";

		exit();
	}

	// Make sure PHP doesn't introduce weird limitations.
	ini_set("memory_limit", "-1");
	set_time_limit(0);

	$result = array("threshold" => $threshold, "start" => array("" => 0, "*" => 0), "middle" => array(), "end" => array(), "recovery" => array());
	$fp = fopen($argv[1], "rb");
	if ($fp === false)
	{
		echo "Unable to open '" . $argv[1] . "' for reading.\n";

		exit();
	}

	while (($word = fgets($fp)) !== false)
	{
		$word = strtolower(trim($word));
		$word2 = preg_replace('/[^a-z\x80-\xff]/', "", $word);
		if ($word === $word2 && UTF8::IsValid($word))
		{
			$queue = array();
			$main = false;
			$path = &$result["start"];
			$num = 0;
			$pos = 0;
			$size = 0;
			$y = strlen($word);
			while (UTF8::NextChrPos($word, $y, $pos, $size))
			{
				$newchr = substr($word, $pos, $size);
				$num++;

				if ($num > 2 && $pos + $size < $y)
				{
					$lastchr = $queue[count($queue) - 1];
					if (!isset($result["recovery"][$lastchr]))  $result["recovery"][$lastchr] = array("" => 0);
					if (!isset($result["recovery"][$lastchr][$newchr]))  $result["recovery"][$lastchr][$newchr] = 0;

					$result["recovery"][$lastchr][""]++;
					$result["recovery"][$lastchr][$newchr]++;
				}

				if (!$main)
				{
					// Process the start of the word.
					if (!isset($path[$newchr]))
					{
						$path[$newchr] = ($num >= $threshold ? array("+" => 0, "-" => 0) : array("" => 0, "*" => 0, "+" => 0, "-" => 0));
						ksort($path);
					}

					if ($pos + $size >= $y)
					{
						$path["*"]++;
						$path[$newchr]["-"]++;
					}
					else
					{
						$path[""]++;
						$path[$newchr]["+"]++;
					}

					$queue[] = $newchr;
					$path = &$path[$newchr];

					if ($num >= $threshold)  $main = true;
				}
				else
				{
					// Process the middle of the word.
					$str = implode("", $queue);

					if (!isset($result["middle"][$str]))  $result["middle"][$str] = array("" => 0);
					if (!isset($result["middle"][$str][$newchr]))  $result["middle"][$str][$newchr] = 0;

					$result["middle"][$str][""]++;
					$result["middle"][$str][$newchr]++;

					$queue[] = $newchr;
					array_shift($queue);
				}
			}

			// Process the end of the word.
			if ($num > $threshold)
			{
				do
				{
					$firstchr = array_shift($queue);
					$num = count($queue);

					$str = implode("", $queue);

					if (!isset($result["end"][$num]))  $result["end"][$num] = array();
					if (!isset($result["end"][$num][$firstchr]))  $result["end"][$num][$firstchr] = array("" => 0);
					if (!isset($result["end"][$num][$firstchr][$str]))  $result["end"][$num][$firstchr][$str] = 0;

					$result["end"][$num][$firstchr][""]++;
					$result["end"][$num][$firstchr][$str]++;

				} while (count($queue) > 1);
			}
		}
	}

	fclose($fp);

	if (count($result["start"]) < 5 || count($result["middle"]) < 10 || count($result["recovery"]) < 3)
	{
		echo "An insufficient dictionary was supplied.  Add more entries or lower the length/threshold and try again.\n";

		exit();
	}

	// Prettify the arrays.
	foreach ($result["middle"] as $str => $info)
	{
		ksort($result["middle"][$str]);
	}
	ksort($result["middle"]);

	foreach ($result["end"] as $num => $chrs)
	{
		foreach ($chrs as $chr => $endings)
		{
			ksort($result["end"][$num][$chr]);
		}

		ksort($result["end"][$num]);
	}

	foreach ($result["recovery"] as $chr => $info)
	{
		ksort($result["recovery"][$chr]);
	}
	ksort($result["recovery"]);

	// Converts data to a human-readable JSON string.
	function ConvertDataToUserJSON($data, $compact = 0)
	{
		$data = str_replace("    ", "\t", json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

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
					if ($line2{0} !== "]" && $line2{0} !== "}" && substr($line2, -1) !== "{" && substr($line2, -1) !== "[")
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
						else if (($line2{0} === "]" || $line2{0} === "}") && in_array(substr($lines[count($lines) - 1], -1), array("{", "[")))
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

	echo "Memory usage:  " . memory_get_usage(true) . "\n";
?>