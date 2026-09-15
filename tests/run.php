<?php
/**
 * Tiny test runner: php tests/run.php
 *
 * @package SocialHub
 */

require_once __DIR__ . '/bootstrap.php';

$GLOBALS['sh_tests']    = array();
$GLOBALS['sh_failures'] = array();
$GLOBALS['sh_current']  = '';

/**
 * Registers a test case.
 *
 * @param string   $name Test name.
 * @param callable $body Test body.
 * @return void
 */
function sh_test( $name, callable $body ) {
	$GLOBALS['sh_tests'][ $name ] = $body;
}

/**
 * Records a failed assertion.
 *
 * @param string $message Failure description.
 * @return void
 */
function sh_fail( $message ) {
	$GLOBALS['sh_failures'][] = $GLOBALS['sh_current'] . ': ' . $message;
}

/**
 * Asserts strict equality.
 *
 * @param mixed  $expected Expected value.
 * @param mixed  $actual   Actual value.
 * @param string $message  Assertion description.
 * @return void
 */
function sh_assert_same( $expected, $actual, $message ) {
	if ( $expected !== $actual ) {
		sh_fail( $message . "\n    expected: " . var_export( $expected, true ) . "\n    actual:   " . var_export( $actual, true ) );
	}
}

/**
 * Asserts a truthy value.
 *
 * @param mixed  $value   Value to check.
 * @param string $message Assertion description.
 * @return void
 */
function sh_assert_true( $value, $message ) {
	if ( ! $value ) {
		sh_fail( $message . ' (got ' . var_export( $value, true ) . ')' );
	}
}

/**
 * Asserts that a string contains a needle.
 *
 * @param string $needle   Expected substring.
 * @param string $haystack String to search.
 * @param string $message  Assertion description.
 * @return void
 */
function sh_assert_contains( $needle, $haystack, $message ) {
	if ( false === strpos( (string) $haystack, (string) $needle ) ) {
		sh_fail( $message . "\n    missing:  " . $needle . "\n    in:       " . $haystack );
	}
}

/**
 * Asserts that a string does not contain a needle.
 *
 * @param string $needle   Unexpected substring.
 * @param string $haystack String to search.
 * @param string $message  Assertion description.
 * @return void
 */
function sh_assert_not_contains( $needle, $haystack, $message ) {
	if ( false !== strpos( (string) $haystack, (string) $needle ) ) {
		sh_fail( $message . "\n    unexpected: " . $needle . "\n    in:         " . $haystack );
	}
}

foreach ( glob( __DIR__ . '/test-*.php' ) as $file ) {
	require_once $file;
}

$passed = 0;

foreach ( $GLOBALS['sh_tests'] as $name => $body ) {
	$GLOBALS['sh_current'] = $name;
	$before                = count( $GLOBALS['sh_failures'] );

	sh_reset_state();
	$body();

	if ( count( $GLOBALS['sh_failures'] ) === $before ) {
		++$passed;
		echo "  ok   " . $name . "\n";
	} else {
		echo "  FAIL " . $name . "\n";
	}
}

echo "\n" . $passed . '/' . count( $GLOBALS['sh_tests'] ) . " tests passed\n";

if ( $GLOBALS['sh_failures'] ) {
	echo "\nFailures:\n";

	foreach ( $GLOBALS['sh_failures'] as $failure ) {
		echo '  - ' . $failure . "\n";
	}

	exit( 1 );
}
