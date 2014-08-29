<?php

class Math {
	/**
	 * Mersenne Twister Random Number Generator
	 * Returns a random number. Depending on the application, you likely don't have to reseed every time as you can simply select a different index
	 * from the last seed and get a different number - it is already auto-incremented each time mt() is called unless the index is specified manually.
	 *
	 * Note: This has been tweaked for performance. It still generates the same numbers as the original algorithm but it's slightly more complicated
	 *       because it maintains both speed and elegance. Though not a crucial difference under normal use, on 1 million iterations,
	 *       re-seeding each time, it will save 5 minutes of time from the orginal algorithm - at least on my system.
	 *
	 * $seed    : Any number, used to seed the generator. Default is time().
	 * $index   : An index indicating the index of the internal array to select the number to generate the random number from
	 * $min     : The minimum number to return
	 * $max     : The maximum number to return
	 *
	 * Returns the random number as an INT
	 **/
	public static function mt($seed = null, $index = null, $min = 0, $max = 1000)
	{
	    static $mt = array(); // 624 element array used to get random numbers
	    static $ps = null; // Previous Seed
	    static $idx = 0; // The index to use when selecting a number to randomize
	 
	    // Seed if none was given
	    if($seed === null)
	        $seed = time();
	 
	    // Assign index
	    if($index !== null)
	        $idx = $index;
	 
	    // Regenerate when reseeding or seeding initially
	    if($seed !== $ps)
	    {
	        $s = $seed & 0xffffffff;
	        $mt = array(&$s, 624 => &$s);
	        $ps = $seed;
	 
	        for($i = 1; $i < 624; ++$i)
	            $mt[$i] = (0x6c078965 * ($mt[$i - 1] ^ ($mt[$i - 1] >> 30)) + $i) & 0xffffffff;
	 
	        // This has been tweaked for maximum speed and elegance
	        // Explanation of possibly confusing variables:
	        //   $p = previous index
	        //   $sp = split parts of array - the numbers at which to stop and continue on
	        //   $n = number to iterate to - we loop up to 227 adding 397 after which we finish looping up to 624 subtracting 227 to continue getting out 397 indices ahead reference
	        //   $m = 397 or -227 to add to $i to keep our 397 index difference
	        //   $i = the previous element in $sp, our starting index in this iteration
	        for($j = 1, $sp = array(0, 227, 397); $j < count($sp); ++$j)
	        {
	            for($p = $j - 1, $i = $sp[$p], $m = ((624 - $sp[$j]) * ($p ? -1 : 1)), $n = ($sp[$j] + $sp[$p]); $i < $n; ++$i)
	            {
	                $y = ($mt[$i] & 0x80000000) | ($mt[$i + 1] & 0x7fffffff);
	                $mt[$i] = $mt[$i + $m] ^ ($y >> 1) ^ (($y & 0x1) * 0x9908b0df); // TODO: check if (($y ^ 0x1) || 0x9908b0df) would be faster
	            }
	        }
	    }
	 
	    // Select a number from the array and randomize it
	    $y = $mt[$idx = $idx % 624];
	    $y ^= $y >> 11;
	    $y ^= ($y << 7) & 0x9d2c5680;
	    $y ^= ($y << 15) & 0xefc60000;
	    $y ^= $y >> 18;
	 
	    // Set the next index to randomize the results even if the same seed is passed
	    // We don't need to % 624 on this because that's already done when selecting $y
	    ++$idx;
	 
	    return $y % ($max - $min + 1) + $min;
	}
}
