CSPRNG Class:  'support/random.php'
===================================

The CSPRNG class is for generating user session tokens, random strings, and random integers.  It relies on built-in (e.g. PHP 7+'s random_bytes() function), libraries (e.g. OpenSSL), and OS-level sources to obtain Cryptographically Strong Pseudorandom data.

The file is called 'random.php' for historical reasons.

Example usage:

```php
<?php
	require_once "support/random.php";

	$rng = new CSPRNG(true);
	echo $rng->GenerateToken() . "\n";
?>
```

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
