const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const postcssPlugins = require( '@wordpress/postcss-plugins-preset' );
const TerserPlugin = require('terser-webpack-plugin');
const CopyWebpackPlugin = require( 'copy-webpack-plugin' );
const MiniCSSExtractPlugin = require( 'mini-css-extract-plugin' );
const path = require('path');
const { fromProjectRoot } = require('@wordpress/scripts/utils/file');
const glob = require('glob');
const RemoveEmptyScriptsPlugin = require('webpack-remove-empty-scripts');

const {
	hasCssnanoConfig,
	hasPostCSSConfig,
	hasBabelConfig,
	hasArgInCLI,
} = require('@wordpress/scripts/utils');

const hasReactFastRefresh = hasArgInCLI( '--hot' ) && ! isProduction;

const isProduction = process.env.NODE_ENV === 'production';

const cssLoaders = [
	{
		loader: MiniCSSExtractPlugin.loader,
	},
	{
		loader: require.resolve( 'css-loader' ),
		options: {
			sourceMap: ! isProduction,
			modules: {
				auto: true,
			},
		},
	}
];

const cssMinLoaders = [
	...cssLoaders,
	{
		loader: require.resolve( 'postcss-loader' ),
		options: {
			// Provide a fallback configuration if there's not
			// one explicitly available in the project.
			...( ! hasPostCSSConfig() && {
				postcssOptions: {
					ident: 'postcss',
					sourceMap: ! isProduction,
					plugins: isProduction
						? [
								...postcssPlugins,
								require( 'cssnano' )( {
									// Provide a fallback configuration if there's not
									// one explicitly available in the project.
									...( ! hasCssnanoConfig() && {
										preset: [
											'default',
											{
												discardComments: {
													removeAll: true,
												},
											},
										],
									} ),
								} ),
						  ]
						: postcssPlugins,
				},
			} ),
		},
	},
];

function getWebpackEntryPoints() {
	let entryPoints = {};

    // Get path from entries fron package.json config file
	const entryPaths = {
		js: fromProjectRoot(process.env.npm_package_config_webpack_js),
		css: fromProjectRoot(process.env.npm_package_config_webpack_css),
	};

	for ( const type in entryPaths ) {
		var thisPath = entryPaths[type];
		var typeFiles = glob.sync(
			// Search for .js or .scss files that do not start with _ or .
			path.join( thisPath, '**', type === 'js' ? '[^_.]*.js' : '[^_.]*.scss' )
		);
	
		typeFiles.forEach( ( file ) => {
			const relative = path.relative( thisPath, file );
			const relativeOut = path.join( type, relative );
			const entryName = relativeOut.substring(0, relativeOut.lastIndexOf('.')) || relativeOut; // remove extension

			entryPoints[ entryName ] = file;

			// Produce minified versions of the files.
			entryPoints[ entryName + '.min' ] = file;
		} );
	}

	return entryPoints;
}

let scssFilesToMinify = [];
let scssFilesMinified = [];

module.exports = {
	...defaultConfig,
	entry: getWebpackEntryPoints(),
	output: {
		path: fromProjectRoot('build' + path.sep),
		filename: '[name].js',
	},
	optimization: {
		...defaultConfig.optimization,
		minimize: true,
		minimizer: [
			new TerserPlugin({
				parallel: true,
				terserOptions: {
					output: {
						comments: /translators:/i,
					},
					compress: {
						passes: 2,
					},
					mangle: {
						reserved: [ '__', '_n', '_nx', '_x' ],
					},
				},
				extractComments: false,
				include: [ /\.min\.js$/ ],
			}),
		],
	},
	module: {
		...defaultConfig.module,
		rules: [
			{
				test: /\.(j|t)sx?$/,
				exclude: /node_modules/,
				use: [
					{
						loader: require.resolve( 'babel-loader' ),
						options: {
							// Babel uses a directory within local node_modules
							// by default. Use the environment variable option
							// to enable more persistent caching.
							cacheDirectory:
								process.env.BABEL_CACHE_DIRECTORY || true,

							// Provide a fallback configuration if there's not
							// one explicitly available in the project.
							...( ! hasBabelConfig() && {
								babelrc: false,
								configFile: false,
								presets: [
									require.resolve(
										'@wordpress/babel-preset-default'
									),
								],
								plugins: [
									hasReactFastRefresh &&
										require.resolve(
											'react-refresh/babel'
										),
								].filter( Boolean ),
							} ),
						},
					},
				],
			},
			{
				test: /\.css$/,
				use: cssLoaders,
			},
			{
				test: /\.min\.css$/,
				use: cssMinLoaders,
			},
			{
				// Testing for scss files. Producing minified.
				test: (name) => {
					if ( 'scss' !== name.split('.').pop() ) {
						return false;
					}

					if ( scssFilesToMinify.includes( name ) ) {
						scssFilesMinified.push( name );
						return true;
					}

					scssFilesToMinify.push( name );
					return false;
				},
				use: [
					...cssMinLoaders,
					{
						loader: require.resolve( 'sass-loader' ),
						options: {
							sourceMap: ! isProduction,
						},
					},
				],
			},
			{
				// Testing for scss files. Producing not minified.
				test: (name) => {
					if ( 'scss' !== name.split('.').pop() ) {
						return false;
					}

					if ( scssFilesMinified.includes( name ) ) {
						return false;
					}

					return true;
				},
				use: [
					...cssLoaders,
					{
						loader: require.resolve( 'sass-loader' ),
						options: {
							sourceMap: ! isProduction,
							sassOptions: {
								minimize: false,
								outputStyle: 'expanded'
							}
						},
					},
				],
			},
			{
				test: /\.svg$/,
				issuer: /\.(j|t)sx?$/,
				use: [ '@svgr/webpack', 'url-loader' ],
				type: 'javascript/auto',
			},
			{
				test: /\.svg$/,
				issuer: /\.(sc|sa|c)ss$/,
				type: 'asset/inline',
			},
			{
				test: /\.(bmp|png|jpe?g|gif|webp)$/i,
				type: 'asset/resource',
				generator: {
					filename: 'images/[name][ext]',
				},
			},
			{
				test: /\.(woff|woff2|eot|ttf|otf)$/i,
				type: 'asset/resource',
				generator: {
					filename: 'fonts/[name].[hash:8][ext]',
				},
			},
		],
	},
	plugins: [
        new RemoveEmptyScriptsPlugin(),
		...defaultConfig.plugins,
		new CopyWebpackPlugin({
			patterns: [
				{ from: 'src/images', to: './images/' }
			]
		}),
	],
};
