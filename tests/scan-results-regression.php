<?php
/**
 * Regression tests for duplicate-image result statistics.
 *
 * Runs independently of WordPress; no site, network, or database is used.
 */

define( 'ABSPATH', __DIR__ . '/' );

function absint( $value ) {
    return abs( (int) $value );
}

require_once dirname( __DIR__ ) . '/includes/scanner/finalize.php';

$cases = array(
    'empty usage list' => array(
        'usages' => array(),
        'expected' => 0,
    ),
    'one valid post' => array(
        'usages' => array( array( 'post_id' => 17 ) ),
        'expected' => 1,
    ),
    'duplicate usages in the same post' => array(
        'usages' => array( array( 'post_id' => 17 ), array( 'post_id' => '17' ) ),
        'expected' => 1,
    ),
    'two valid posts' => array(
        'usages' => array( array( 'post_id' => 17 ), array( 'post_id' => 18 ) ),
        'expected' => 2,
    ),
    'zero or malformed post IDs are not posts' => array(
        'usages' => array(
            array( 'post_id' => 0 ),
            array( 'post_id' => '' ),
            array( 'post_id' => 'invalid' ),
            array( 'post_id' => 17 ),
            array(),
            'invalid row',
        ),
        'expected' => 1,
    ),
    'invalid entries cannot invent repeated posts' => array(
        'usages' => array(
            array( 'post_id' => 0 ),
            array( 'post_id' => 'invalid' ),
            array(),
            null,
        ),
        'expected' => 0,
    ),
);

foreach ( $cases as $name => $case ) {
    $actual = media_insight_count_unique_usage_posts( $case['usages'] );
    if ( $actual !== $case['expected'] ) {
        fwrite( STDERR, sprintf( "%s: expected %d, got %d\n", $name, $case['expected'], $actual ) );
        exit( 1 );
    }
}

echo "Media Insight usage count regression: OK\n";
