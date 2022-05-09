const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
// eslint-disable-next-line import/no-extraneous-dependencies
const TerserPlugin = require( 'terser-webpack-plugin' );
const path = require( 'path' );
const { fromProjectRoot } = require( '@wordpress/scripts/utils/file' );
const fs = require( 'fs' );

function getWebpackEntryPoints() {
	const entryPoints = {};

    const entryPaths = [
        fromProjectRoot( path.join( 'src', 'payments-methods' ) ),
    ];

    const entryNames = [ 'index' ];
    // const entryNames = [ 'index', 'frontend' ];

	entryNames.forEach( ( entryName ) => {
        for ( const entryPath of entryPaths ) {
            const dirs = fs
                .readdirSync( entryPath, {
                    withFileTypes: true,
                } )
                .filter( ( item ) => item.isDirectory() )
                .map( ( item ) => item.name );

            dirs.forEach( ( dir ) => {
                const filepath = path.resolve( entryPath, dir, `${ entryName }.js` );
                if ( fs.existsSync( filepath ) ) {
                    entryPoints[ dir + '/' + entryName ] = filepath;
                    entryPoints[ dir + '/' + entryName + '.min' ] = filepath;
                }
            } );

        }
	} );

	return entryPoints;
}

module.exports = {
	...defaultConfig,
	entry: getWebpackEntryPoints(),
	output: {
		path: fromProjectRoot( 'build' + path.sep ),
		filename: '[name].js',
	},
	optimization: {
		...defaultConfig.optimization,
		minimize: true,
		minimizer: [
			new TerserPlugin( {
				parallel: true,
				include: /index\.min\.js$/,
			} ),
		],
	},
};