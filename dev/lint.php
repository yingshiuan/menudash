<?php
/**
 * Syntax check of every PHP file in the plugin, in whichever PHP version runs it:
 *   npx @wp-playground/cli php --php=7.4 --mount="$PWD:/repo" -- /repo/dev/lint.php
 */
$bad = 0;
$it  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( '/repo/menudash' ) );
foreach ( $it as $f ) {
	if ( 'php' !== $f->getExtension() ) {
		continue;
	}
	try {
		token_get_all( file_get_contents( $f->getPathname() ), TOKEN_PARSE );
		echo 'ok   ' . substr( $f->getPathname(), 6 ) . "\n";
	} catch ( ParseError $e ) {
		$bad++;
		echo 'FAIL ' . substr( $f->getPathname(), 6 ) . ':' . $e->getLine() . ' ' . $e->getMessage() . "\n";
	}
}
echo 'PHP ' . PHP_VERSION . ( $bad ? ": $bad file(s) with syntax errors\n" : ": all files parse\n" );
exit( $bad ? 1 : 0 );
