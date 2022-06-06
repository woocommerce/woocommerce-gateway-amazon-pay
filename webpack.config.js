const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
// eslint-disable-next-line import/no-extraneous-dependencies
const TerserPlugin = require( 'terser-webpack-plugin' );
const path = require( 'path' );
const { fromProjectRoot } = require( '@wordpress/scripts/utils/file' );
const fs = require( 'fs' );

function getWebpackEntryPoints() {
	const entryPoints = {};

    const entryPaths = {
        'payments-methods': fromProjectRoot( path.join( 'src', 'payments-methods' ) ),
        'blocks': fromProjectRoot( path.join( 'src', 'blocks' ) ),
    };

    const entryNames = [ 'index', 'frontend' ];

	entryNames.forEach( ( entryName ) => {
        for ( const entryPath in entryPaths ) {
            const dirs = fs
                .readdirSync( entryPaths[ entryPath ], {
                    withFileTypes: true,
                } )
                .filter( ( item ) => item.isDirectory() )
                .map( ( item ) => item.name );

            dirs.forEach( ( dir ) => {
                const filepath = path.resolve( entryPaths[ entryPath ], dir, `${ entryName }.js` );
                if ( fs.existsSync( filepath ) ) {
                    entryPoints[ entryPath + '/' + dir + '/' + entryName ] = filepath;
                    entryPoints[ entryPath + '/' + dir + '/' + entryName + '.min' ] = filepath;
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