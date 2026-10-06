<?php
/**
 * Dependency-free tests for BundleTiers\Pricing.
 * Run: php tests/pricing-test.php   (dev only, not shipped)
 */

define( 'ABSPATH', __DIR__ . '/' ); // Lets the class file load outside WordPress.
require __DIR__ . '/../includes/class-pricing.php';

use BundleTiers\Pricing;

$fail = 0;
$n    = 0;
function check( $label, $actual, $expected ) {
	global $fail, $n;
	++$n;
	if ( $actual !== $expected ) {
		++$fail;
		echo "FAIL  $label\n  expected: " . json_encode( $expected ) . "\n  actual:   " . json_encode( $actual ) . "\n";
	}
}

// Money conversion (no float drift).
check( 'to_minor 25.51', Pricing::to_minor( 25.51 ), 2551 );
check( 'to_minor 19.99', Pricing::to_minor( '19.99' ), 1999 );
check( 'to_minor 0.29', Pricing::to_minor( 0.29 ), 29 );
check( 'to_minor 0 decimals', Pricing::to_minor( 1200, 0 ), 1200 );
check( 'from_minor', Pricing::from_minor( 2551 ), 25.51 );

// Percent.
$r = Pricing::apply( 2551, 1, 'percent', 0 );
check( 'percent 0% x1 total', $r['total'], 2551 );
$r = Pricing::apply( 2551, 2, 'percent', 10 );
check( 'percent 10% x2 total', $r['total'], 4592 );   // 2296 x 2 (2295.9 -> 2296)
check( 'percent 10% x2 unit', $r['unit_price'], 2296 );
check( 'percent 10% x2 saved', $r['saved'], 510 );
$r = Pricing::apply( 2551, 3, 'percent', 15 );
check( 'percent 15% x3 total', $r['total'], 6504 );   // 2168 x 3 (2168.35 -> 2168)
check( 'percent half-up', Pricing::apply( 1005, 1, 'percent', 50 )['total'], 503 ); // 502.5 -> 503
check( 'percent 100%', Pricing::apply( 2551, 2, 'percent', 100 )['total'], 0 );
check( 'percent >100 clamped', Pricing::apply( 2551, 2, 'percent', 150 )['total'], 0 );
check( 'percent negative clamped', Pricing::apply( 2551, 2, 'percent', -10 )['total'], 5102 );
check( 'percent fractional 12.5', Pricing::apply( 1000, 1, 'percent', 12.5 )['total'], 875 );

// Fixed per item.
check( 'fixed_item 2.00 x3', Pricing::apply( 2551, 3, 'fixed_item', 2 )['total'], 3 * 2351 );
check( 'fixed_item above price', Pricing::apply( 500, 2, 'fixed_item', 9 )['total'], 0 );
check( 'fixed_item negative', Pricing::apply( 500, 2, 'fixed_item', -3 )['total'], 1000 );

// Fixed pack price + remainder spread.
$r = Pricing::apply( 2551, 3, 'fixed_pack', 65.05 );
check( 'fixed_pack total', $r['total'], 6505 );
check( 'fixed_pack units', $r['units'], array( 2169, 2168, 2168 ) );
check( 'fixed_pack units sum', array_sum( $r['units'] ), 6505 );
check( 'fixed_pack unit_price', $r['unit_price'], 2168 );
check( 'fixed_pack cap at full', Pricing::apply( 1000, 2, 'fixed_pack', 99 )['total'], 2000 );
check( 'fixed_pack negative', Pricing::apply( 1000, 2, 'fixed_pack', -5 )['total'], 0 );

// Units always sum to total.
foreach ( array( 'percent' => 13.3, 'fixed_item' => 0.7, 'fixed_pack' => 33.33 ) as $t => $v ) {
	foreach ( array( 1, 2, 3, 7 ) as $q ) {
		$r = Pricing::apply( 1999, $q, $t, $v );
		check( "sum $t x$q", array_sum( $r['units'] ), $r['total'] );
		check( "saved $t x$q", $r['saved'], $r['full'] - $r['total'] );
	}
}

// Unknown type, bad input.
check( 'unknown type = full price', Pricing::apply( 1000, 2, 'bogus', 10 )['total'], 2000 );
check( 'qty < 1 becomes 1', Pricing::apply( 1000, 0, 'percent', 10 )['total'], 900 );
check( 'zero price', Pricing::apply( 0, 3, 'percent', 10 )['total'], 0 );
check( 'zero decimals currency', Pricing::apply( 1200, 2, 'fixed_pack', 2000, 0 )['total'], 2000 );

// Tier lookup.
$tiers = array( array( 'qty' => 3 ), array( 'qty' => 1 ), array( 'qty' => 2 ) );
check( 'find_tier 1', Pricing::find_tier( $tiers, 1 )['qty'], 1 );
check( 'find_tier 2', Pricing::find_tier( $tiers, 2 )['qty'], 2 );
check( 'find_tier 5 -> 3', Pricing::find_tier( $tiers, 5 )['qty'], 3 );
check( 'find_tier 0 -> null', Pricing::find_tier( $tiers, 0 ), null );
check( 'find_tier empty', Pricing::find_tier( array(), 3 ), null );
check( 'find_tier gap', Pricing::find_tier( array( array( 'qty' => 1 ), array( 'qty' => 3 ) ), 2 )['qty'], 1 );
check( 'find_tier ignores qty 0', Pricing::find_tier( array( array( 'qty' => 0 ) ), 3 ), null );

// Mixed prices (variations with different current prices counted together).
$r = Pricing::apply_mixed( array( 2000, 3000, 3000 ), 'percent', 10 );
check( 'mixed percent units', $r['units'], array( 1800, 2700, 2700 ) );
check( 'mixed percent saved', $r['saved'], 800 );
$r = Pricing::apply_mixed( array( 2000, 3000 ), 'fixed_item', 1.5 );
check( 'mixed fixed_item units', $r['units'], array( 1850, 2850 ) );
$r = Pricing::apply_mixed( array( 2000, 3000, 3000 ), 'fixed_pack', 64 );
check( 'mixed fixed_pack total', $r['total'], 6400 );
check( 'mixed fixed_pack units sum', array_sum( $r['units'] ), 6400 );
check( 'mixed fixed_pack proportional', $r['units'], array( 1600, 2400, 2400 ) );
$r = Pricing::apply_mixed( array( 1000, 1000, 1000 ), 'fixed_pack', 20 );
check( 'mixed fixed_pack remainder first', $r['units'], array( 667, 667, 666 ) );
check( 'mixed fixed_pack capped', Pricing::apply_mixed( array( 1000, 1000 ), 'fixed_pack', 99 )['total'], 2000 );
check( 'mixed empty list', Pricing::apply_mixed( array(), 'percent', 10 )['total'], 0 );
check( 'allocate zero weights', Pricing::allocate( 10, array( 0, 0 ) ), array( 5, 5 ) );
check( 'allocate zero total', Pricing::allocate( 0, array( 5, 7 ) ), array( 0, 0 ) );
foreach ( array( 1, 2, 7, 99, 6505 ) as $tot ) {
	$parts = Pricing::allocate( $tot, array( 1999, 2551, 3001, 17 ) );
	check( "allocate sums to $tot", array_sum( $parts ), $tot );
}

echo $fail ? "\n$fail of $n checks FAILED\n" : "All $n checks passed\n";
exit( $fail ? 1 : 0 );
