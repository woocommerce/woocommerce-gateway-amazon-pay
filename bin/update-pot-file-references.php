<?php
// phpcs:ignoreFile - This is an auxiliary build tool, and not part of the plugin.

/**
 * Command line script for updating the file references of JS files in a .pot file.
 */

use phpDocumentor\Reflection\DocBlock\Tags\Var_;

/**
 * Get the file name from the command line.
 */
if ( $argc !== 2 ) {
	echo "Usage: {$argc} php -f {$argv[0]} file.pot\n";
	exit;
}

$pot_filename = $argv[1];

if ( ! is_file( $pot_filename ) ) {
	echo "[ERROR] File not found: {$pot_filename}\n";
	exit;
}

/**
 * Parses a .pot file into an array.
 *
 * @param string $file_name Pot file name.
 * @return array Translation messages
 */
function read_pot_translations( string $file_name ): array {
	$fh         = fopen( $file_name, 'r' );
	$originals  = [];
	$references = [];
	$messages   = [];
	$have_msgid = false;

	while ( ! feof( $fh ) ) {
		$line = trim( fgets( $fh ) );
		if ( ! $line ) {
			$message               = implode( "\n", $messages );
			$originals[ $message ] = $references;
			$references            = [];
			$messages              = [];
			$have_msgid            = false;
			continue;
		}

		if ( 'msgid' == substr( $line, 0, 5 ) ) {
			$have_msgid = true;
		}

		if ( $have_msgid ) {
			$messages[] = $line;
		} else {
			$references[] = $line;
		}
	}

	fclose( $fh );

	$message               = implode( "\n", $messages );
	$originals[ $message ] = $references;
	return $originals;
}

/**
 * Add the transpiled file path to the references of the translation messages.
 *
 * @param array $translations POT translations (including references/comments).
 * @return array Translation messages
 */
function add_transpiled_filepath_reference_to_comments( array $translations ): array {
	foreach ( $translations as $message => $references ) {
		// Check references for js/jsx/ts/tsx files
		$dist_js_to_add = [];
		foreach ( $references as $i => $ref ) {
			if ( preg_match( '%^#: (build.+)(\.(js|jsx|ts|tsx)):\d+$%', $ref, $m ) ) {
				if ( preg_match( '%\.min\.js$%', $m[1] ) ) {
					unset( $translations[ $message ][ $i ] );
					continue;
				}
				if ( empty( $m[2] ) ) {
					continue;
				}
				$dist_js_to_add[] = "#: {$m[1]}.min{$m[2]}:1";
			}
		}

		// Add the new file references to the top of the list.
		if ( ! empty( $dist_js_to_add ) ) {
			array_splice( $translations[ $message], 0, 0, array_unique( $dist_js_to_add ) );
		}
	}

	return $translations;
}

// Read the translation .pot file.
$translations = read_pot_translations( $pot_filename );

// For transpiled JS client files, we need to add a reference to the generated build file.
$translations = add_transpiled_filepath_reference_to_comments( $translations );

// Delete the original source.
unlink( $pot_filename );

$fh = fopen( $pot_filename, 'w' );

foreach ( $translations as $message => $original ) {
	fwrite( $fh, implode( "\n", $original ) );
	fwrite( $fh, "\n" . $message . "\n\n" );
}

fclose( $fh );

echo "Updated {$pot_filename}\n";
