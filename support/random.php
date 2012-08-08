<?php
	// Cryptographically Secure Pseudo-Random String Generator (CSPRSG) and CSPRNG.
	// (C) 2011 CubicleSoft.  All Rights Reserved.

	function RSG_GetRootSeedURLs()
	{
		$result = array(
			"https://www.random.org/integers/?num=100&min=0&max=255&col=10&base=16&format=plain&rnd=new" => array("alt" => "http://www.random.org/integers/?num=100&min=0&max=255&col=10&base=16&format=plain&rnd=new", "reduce" => false),
			"https://www.fourmilab.ch/cgi-bin/Hotbits?nbytes=128&fmt=bin" => array("alt" => "http://www.fourmilab.ch/cgi-bin/Hotbits?nbytes=128&fmt=bin", "reduce" => false),
			"https://www.grc.com/passwords.htm" => array("alt" => "", "reduce" => true)
		);

		return $result;
	}

	function RSG_Translate()
	{
		$args = func_get_args();
		if (!count($args))  return "";

		return call_user_func_array((defined("CS_TRANSLATE_FUNC") && function_exists(CS_TRANSLATE_FUNC) ? CS_TRANSLATE_FUNC : "sprintf"), $args);
	}

	function RSG_GetURL($url)
	{
		// First attempt to load via the Ultimate Web Scraper Toolkit.
		if (function_exists("fsockopen") && function_exists("GetWebUserAgent") && function_exists("RetrieveWebpage"))
		{
			$options2 = array(
				"headers" => array(
					"User-Agent" => GetWebUserAgent("Firefox"),
					"Accept" => "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
					"Accept-Language" => "en-us,en;q=0.5",
					"Accept-Charset" => "ISO-8859-1,utf-8;q=0.7,*;q=0.7",
					"Cache-Control" => "max-age=0"
				)
			);

			$result = RetrieveWebpage($url, $options2);
			if (!$result["success"])  return array("success" => false, "error" => RSG_Translate("Unable to retrieve the URL content.  %s", $result["error"]));
			if ($result["response"]["code"] != 200)  return array("success" => false, "error" => RSG_Translate("Unable to retrieve the URL content.  Server returned:  %s %s", $result["response"]["code"], $result["response"]["meaning"]));

			return array("success" => true, "data" => $result["body"], "headers" => $result["headers"], "url" => $url);
		}

		// Fallback to fopen-wrappers.
		$result = @file_get_contents($url);
		if ($result !== false)  return array("success" => true, "data" => $result, "headers" => array(), "url" => $url);

		return array("success" => false, "error" => RSG_Translate("Unable to retrieve the URL content.  Unable to find a suitable function to read the URL."));
	}

	function RSG_WriteSeedData(&$data, &$hash, $str, $reduce = true)
	{
		if (is_resource($hash))  hash_update($hash, $str);
		else if ($reduce)  $data .= pack("H*", sha1($str));
		else  $data .= $str;

		return 1;
	}

	// Generates a cryptographically secure random string for use as a root seed/secret key.
	function RSG_GenerateRootSeed($options = array())
	{
		$data = "";
		$hash = (function_exists("hash_init") ? hash_init("sha512") : false);
		$strength = (is_resource($hash) ? 1 : 0);

		// Start with some weak sauce.
		$strength += RSG_WriteSeedData($data, $hash, uniqid(mt_rand(), true));

		// Mix in all available request data.
		RSG_WriteSeedData($data, $hash, serialize($_REQUEST));
		RSG_WriteSeedData($data, $hash, serialize($_GET));
		RSG_WriteSeedData($data, $hash, serialize($_POST));
		RSG_WriteSeedData($data, $hash, serialize($_COOKIE));
		RSG_WriteSeedData($data, $hash, serialize($_FILES));
		RSG_WriteSeedData($data, $hash, serialize($_SERVER));
		RSG_WriteSeedData($data, $hash, serialize($_ENV));

		// Add some local functions that may or may not exist.
		if (function_exists("memory_get_usage"))  $strength += 0.1 * RSG_WriteSeedData($data, $hash, memory_get_usage(), false);
		if (function_exists("memory_get_peak_usage"))  $strength += 0.1 * RSG_WriteSeedData($data, $hash, memory_get_peak_usage(), false);
		if (function_exists("getrusage"))  $strength += 0.1 * RSG_WriteSeedData($data, $hash, serialize(getrusage()), false);
		if (function_exists("posix_times"))
		{
			$data2 = serialize(posix_times());
			$data2 .= serialize(posix_getpwuid(posix_getuid()));
			$data2 .= serialize(posix_getpwuid(posix_geteuid()));
			$data2 .= serialize(posix_getgrgid(posix_getgid()));
			$data2 .= serialize(posix_getgrgid(posix_getegid()));
			foreach (posix_getgroups() as $gid)  $data2 .= serialize(posix_getgrgid($gid));
			$data2 .= posix_getpid();
			$data2 .= posix_getsid(posix_getpid());

			$strength += 0.1 * RSG_WriteSeedData($data, $hash, $data2);
		}
		RSG_WriteSeedData($data, $hash, php_sapi_name() . zend_version() . phpversion() . get_current_user() . getmypid(), false);
		RSG_WriteSeedData($data, $hash, serialize(get_loaded_extensions()));
		RSG_WriteSeedData($data, $hash, serialize(get_included_files()));
		RSG_WriteSeedData($data, $hash, serialize(ini_get_all()));
		RSG_WriteSeedData($data, $hash, serialize(get_defined_constants()));
		RSG_WriteSeedData($data, $hash, serialize(get_defined_functions()));
		$data2 = get_declared_classes();
		RSG_WriteSeedData($data, $hash, serialize($data2));
		foreach ($data2 as $class)
		{
			RSG_WriteSeedData($data, $hash, serialize(get_class_vars($class)));
			RSG_WriteSeedData($data, $hash, serialize(get_class_methods($class)));
		}
		unset($data2);

		// Drop in the kitchen sink.  Some things repeated from above but this is the kitchen sink after all.
		ob_start();
		phpinfo();
		RSG_WriteSeedData($data, $hash, ob_get_contents());
		ob_end_clean();

		// Mix in some randomness with local crypto options.
		if (function_exists("openssl_random_pseudo_bytes"))
		{
			$data2 = @openssl_random_pseudo_bytes(4096, $strong);
			if ($data2 !== false && $strong)  $strength += 2 * RSG_WriteSeedData($data, $hash, $data2);
		}

		// Mix in some randomness from trusted remote sites.
		$found = false;
		$trusted = RSG_GetRootSeedURLs();
		if (!isset($options["urls"]))  $options["urls"] = array();
		foreach ($options["urls"] as $url => $info)
		{
			$origurl = $url;
			$data2 = RSG_GetURL($url);
			if (!$data2["success"])
			{
				if ($info["alt"] != "")
				{
					$url = $info["alt"];
					$data2 = RSG_GetURL($url);
				}

				if (!$data2["success"])  continue;
			}

			$strength += (3 + (strtolower(substr($url, 0, 8)) == "https://" ? 1 : -1) + (isset($trusted[$origurl]) ? 1 : -1)) * RSG_WriteSeedData($data, $hash, $data2["data"], $info["reduce"]);
		}
		if (!$found)  sleep(1);

		// Add some of the previous local functions that might have changed value.
		if (function_exists("memory_get_usage"))  RSG_WriteSeedData($data, $hash, memory_get_usage(), false);
		if (function_exists("memory_get_peak_usage"))  RSG_WriteSeedData($data, $hash, memory_get_peak_usage(), false);
		if (function_exists("getrusage"))  RSG_WriteSeedData($data, $hash, serialize(getrusage()), false);
		if (function_exists("posix_times"))  RSG_WriteSeedData($data, $hash, serialize(posix_times()), false);

		// Finalize the result.
		$strength += 0.6 * RSG_WriteSeedData($data, $hash, uniqid(mt_rand(), true));
		if (is_resource($hash))  $result = hash_final($hash);
		else  $result = sha1($data);

		return array("success" => true, "result" => $result, "strength" => $strength);
	}

	// Generates random tokens for things like session IDs.
	function RSG_GenerateToken($rootseed, $entropy = "")
	{
		$data = "";
		$hash = (function_exists("hash_init") ? hash_init("sha512") : false);

		RSG_WriteSeedData($data, $hash, uniqid(mt_rand(), true));
		if ($entropy != "")  RSG_WriteSeedData($data, $hash, $entropy);
		RSG_WriteSeedData($data, $hash, pack("H*", $rootseed), false);

		if (is_resource($hash))  $result = hash_final($hash);
		else  $result = sha1($data);

		return $result;
	}

	// Generates a stream of random numbers and strings.
	class RSG_Stream
	{
		private $rootseed, $lastseed, $lastseedpos, $entropy, $nextnum, $bitsleft;

		public function Init($rootseed)
		{
			$this->rootseed = $rootseed;
			$this->nextnum = 0;
			$this->lastseed = "";
			$this->lastseedpos = 0;
			$this->entropy = "";
			$this->bitsleft = array();
		}

		public function RandomBytes($length, $entropy = "")
		{
			$this->entropy .= $entropy;

			$result = "";

			while (strlen($result) < $length)
			{
				if ($this->lastseedpos >= strlen($this->lastseed))  $this->AddMoreBits();

				if (strlen($result) < $length && $this->lastseedpos < strlen($this->lastseed))
				{
					$num = min($length - strlen($result), strlen($this->lastseed) - $this->lastseedpos);
					$result .= substr($this->lastseed, $this->lastseedpos, $num);
					$this->lastseedpos += $num;
				}
			}

			return $result;
		}

		public function RandomInt($min, $max, $entropy = "")
		{
			$this->entropy .= $entropy;

			$min = (int)$min;
			$max = (int)$max;
			if ($max < $min)  return false;
			if ($min == $max)  return $min;

			$range = $max - $min + 1;

			$bits = 1;
			while ((1 << $bits) <= $range)  $bits++;
			$result = 0;

			$bytes = (int)(($bits + 7) / 8);
			$bytes2 = 1;
			$mask = (1 << (($bytes - 1) * 8)) - 1;
			$mask2 = (1 << $bits) - 1;

			do
			{
				$result = $result & $mask;
				$bytes2--;

				while ($bytes2 < $bytes)
				{
					if ($this->lastseedpos >= strlen($this->lastseed))  $this->AddMoreBits();

					if ($this->lastseedpos < strlen($this->lastseed))
					{
						$result = ($result << 8) | ord(substr($this->lastseed, $this->lastseedpos, 1));
						$bytes2++;
						$this->lastseedpos++;
					}
				}

				$result = $result & $mask2;
			} while ($result >= $range);

			return $result + $min;
		}

		private function AddMoreBits()
		{
			$this->lastseed = pack("H*", RSG_GenerateToken($this->rootseed, $this->entropy . $this->nextnum . $this->lastseed));
			$this->lastseedpos = 0;
			$this->nextnum++;
			$this->entropy = "";
		}
	}

	// Generates a normal distribution of random numbers across a fixed range.
	class RSG_NormalizedStream
	{
		private $randgen, $min, $max, $balancer, $counter;

		public function Init($min, $max, $rootseed)
		{
			if ($max < $min)  return false;

			$this->min = $min;
			$this->max = $max;
			$this->balancer = array();
			$range = $max - $min;
			for ($x = 0; $x <= $range; $x++)  $this->balancer[$x] = $min + $x;

			$this->randgen = new RSG_Stream();
			$this->randgen->Init($rootseed);
			$this->counter = 0;

			return true;
		}

		public function RandomInt($entropy = "")
		{
			$num = $this->randgen->RandomInt(0, count($this->balancer) - 1, $entropy . $this->counter);
			$entropy = "";
			$this->counter++;

			$result = $this->balancer[$num];
			array_splice($this->balancer, $num, 1);

			if (!count($this->balancer))
			{
				$range = $this->max - $this->min;
				for ($x = 0; $x <= $range; $x++)  $this->balancer[$x] = $this->min + $x;
			}

			return $result;
		}
	}

	// Convenience function to generate a random alphanumeric string.
	function RSG_GenerateRandString($seed, $entropy = "", $size = 32)
	{
		$alphanum = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz";
		$rsg = new RSG_Stream;
		$rsg->Init($seed);
		$last = $entropy;
		$result = "";
		for ($x = 0; $x < $size; $x++)
		{
			$rand = $rsg->RandomInt(0, 61, $last . $x);
			$result .= substr($alphanum, $rand, 1);
			$last = $rand;
		}

		return $result;
	}
?>