<?php
/**
 * Checks that assets/js/frontend.js computes exactly what Pricing::apply does.
 * Run: php tests/js-parity-test.php   (needs node; dev only)
 */

define( 'ABSPATH', __DIR__ . '/' ); // Lets the class file load outside WordPress.
require __DIR__ . '/../includes/class-pricing.php';

use BundleTiers\Pricing;

mt_srand( 42 );
$types = array( 'percent', 'fixed_item', 'fixed_pack', 'bogus' );
$cases = array();
for ( $i = 0; $i < 3000; $i++ ) {
	$type    = $types[ mt_rand( 0, 3 ) ];
	$base    = mt_rand( 0, 1 ) ? mt_rand( 0, 50000 ) : mt_rand( 1, 999 );
	$qty     = mt_rand( 1, 9 );
	$decimals = mt_rand( 0, 9 ) ? 2 : mt_rand( 0, 3 );
	$value   = 'percent' === $type ? round( mt_rand( -100, 12000 ) / 100, 2 ) : round( mt_rand( -100, 90000 ) / 100, 2 );
	$r       = Pricing::apply( $base, $qty, $type, $value, $decimals );
	$cases[] = array( $base, $qty, $type, $value, $decimals, array( 'total' => $r['total'], 'full' => $r['full'], 'saved' => $r['saved'], 'unit' => $r['unit_price'] ) );
}

$js = <<<'JS'
const fs = require('fs');
global.window = { jQuery: undefined, bundletiersConfig: { decimals: 2, decimalSep: '.', thousandSep: ',', priceFormat: '%2$s %1$s', symbol: '€' } };
global.document = { addEventListener() {} };
eval(fs.readFileSync(process.argv[2], 'utf8'));
const cases = JSON.parse(fs.readFileSync(0, 'utf8'));
let bad = 0;
cases.forEach(([base, qty, type, value, dec, exp]) => {
  const r = window.BundleTiers.applyTier(base, qty, type, value, dec);
  if (r.total !== exp.total || r.full !== exp.full || r.saved !== exp.saved || r.unit !== exp.unit) {
    if (bad++ < 5) console.log('MISMATCH', JSON.stringify([base, qty, type, value, dec]), JSON.stringify(r), JSON.stringify(exp));
  }
});
const f = window.BundleTiers.formatMoney;
const fmt = [[2551,'25.51 €'],[0,'0.00 €'],[5,'0.05 €'],[123456789,'1,234,567.89 €']];
fmt.forEach(([m, e]) => { if (f(m) !== e) { bad++; console.log('FMT', m, f(m), e); } });
console.log(bad ? bad + ' mismatches' : 'JS matches PHP on ' + cases.length + ' cases + formatting');
process.exit(bad ? 1 : 0);
JS;

$tmp = tempnam( sys_get_temp_dir(), 'btjs' );
file_put_contents( $tmp, $js );
$proc = proc_open( array( 'node', $tmp, __DIR__ . '/../assets/js/frontend.js' ), array( 0 => array( 'pipe', 'r' ), 1 => STDOUT, 2 => STDERR ), $pipes );
fwrite( $pipes[0], json_encode( $cases ) );
fclose( $pipes[0] );
$code = proc_close( $proc );
unlink( $tmp );
exit( $code );
