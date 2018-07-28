CSPRNG Class:  'support/random.php'
===================================

The CSPRNG class is for generating user session tokens, random strings, and random integers.  It relies on built-in (e.g. PHP 7+'s random_bytes() function), libraries (e.g. OpenSSL), and OS-level sources to obtain Cryptographically Strong Pseudorandom data.

The file is called 'random.php' for historical reasons.

Example usage:

```php
<?php
	require_once "support/random.php";

	// Tokens.
	$rng = new CSPRNG(true);
	echo $rng->GenerateToken() . "\n";
?>
```

Additional examples are available below.

CSPRNG::__construct($cryptosafe = false)
----------------------------------------

Access:  public

Parameters:

* $cryptosafe - A boolean specifying whether to use crypto-safe methods (for private key generation and other crypto operations - may block until enough entropy is available) or strong methods (password and session generation) (Default is false).

Returns:  Nothing.

This function selects the best available source available to PHP on the host.  If a relevant source is not available (rare), an exception is raised.

CSPRNG::GetBytes($length)
-------------------------

Access:  public

Parameters:

* $length - An integer specifying the number of bytes to retrieve from the source.

Returns:  A string containing the exact number of bytes requested or a boolean of false on failure.

This function retrieves the number of requested bytes from the source selected in the constructor.

```php
<?php
	require_once "support/random.php";

	$rng = new CSPRNG();
	$bytes = $rng->GetBytes(4096);
	echo bin2hex($bytes) . "\n";
?>
```

CSPRNG::GenerateToken()
-----------------------

Access:  public

Parameters:  None.

Returns:  A string containing a token (hex encoded) on success or a boolean of false on failure.

This function retrieves a token suitable for a wide variety of uses such as a session token.

Example usage:

```php
<?php
	require_once "support/random.php";

	$rng = new CSPRNG();
	echo $rng->GenerateToken() . "\n";
?>
```

CSPRNG::GetInt($min, $max)
--------------------------

Access:  public

Parameters:

* $min - An integer that specifies the minimum integer to return.
* $max - An integer that specifies the maximum integer to return.

Returns:  Returns a random integer from `$min` to `$max` (inclusive) on success, a boolean of false on failure.

This function returns a random integer.  Random integer selection is performed by throwing out integers outside the desired range (the right way) instead of modulus arithmetic (the wrong way).

```php
<?php
	require_once "support/random.php";

	$rng = new CSPRNG();
	for ($x = 0; $x < 100; $x++)
	{
		$result = $rng->GetInt(0, 40);
		echo $result . "\n";
	}
?>
```

CSPRNG::GenerateString($size = 32)
----------------------------------

Access:  public

Parameters:

* $size - An integer specifying the length of the returned string in bytes (Default is 32).

Returns:  A string containing randomly selected alphanumeric characters.

This function uses `CSPRNG::GetInt()` to generate a random string with only alphanumeric characters (0-9, A-Z, and a-z).  Useful for generating random strings of various lengths.

Example usage:

```php
<?php
	require_once "support/random.php";

	$rng = new CSPRNG();
	$str = $rng->GenerateString();
	echo $str . "\n";
?>
```

CSPRNG::GenerateWord(&$freqmap, $len, $separator = "-")
-------------------------------------------------------

Access:  public

Parameters:

* $freqmap - A reference to an array containing a frenquency map.
* $len - An integer specifying the length of the returned string in bytes.
* $separator - A string specifying the character(s) to use to separate restarts (Default is "-").

Returns:  A string containing a randomly generated word.

This function implements a state engine that generates a random Unicode word based on the input frequency distribution.  Useful for generating random, mostly pronouncible, but nonsensical words of various lengths for use as a password, a project codename, etc.

Depending on the inputs, it is possible but extremely rare for the state engine to enter a failure state.  The state engine compensates by inserting the separator into the output string and going back to the starting state.

Example usage:

```php
<?php
	require_once "support/random.php";

	$rng = new CSPRNG();

	$freqmap = json_decode(file_get_contents("support/en_us_freq_3.json"), true);

	$words = array();
	for ($x = 0; $x < 3; $x++)  $words[] = $rng->GenerateWord($freqmap, $rng->GetInt(4, 8));

	echo "New password:  " . implode("-", $words) . "\n";
?>
```

There is a tool in this repository `src/process_dictionary.php` which generates CSPRNG-compliant frequency distributions from dictionaries.  A dictionary can be almost anything (e.g. names of cities, medical terms) in any language.  The tool generates Markov-like chains but with an awareness that words have a start, middle, and end.  The generated `support/en_us_freq_3.json` file included in this repository provides an excellent balance between system resource usage and producing mostly readable words.  `support/en_us_freq_4.json` is even better but requires a lot more system resources.

Looking for the dictionary used for the above?  See the [SSO server/client](https://github.com/cubiclesoft/sso-server/blob/master/support/dictionary.txt).

CSPRNG::GetMode()
-----------------

Access:  public

Parameters:  None.

Returns:  A string containing the current internal mode being used.

This function returns the mode that was selected by the constructor.  There are several modes available:  "native", "openssl", "mcrypt", and "file".

CSPRNG::RNG_Translate($format, ...)
-----------------------------------

Access:  _internal_ static

Parameters:

* $format - A string containing valid sprintf() format specifiers.

Returns:  A string containing a translation.

This internal static function takes input strings and translates them from English to some other language if CS_TRANSLATE_FUNC is defined to be a valid PHP function name.
