const defaultConfig = require( './node_modules/@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );
const IgnoreEmitPlugin = require( 'ignore-emit-webpack-plugin' );
const MiniCSSExtractPlugin = require( 'mini-css-extract-plugin' );

const isProduction = process.env.NODE_ENV === 'production';
const mode = isProduction ? 'production' : 'development';

const jsFiles = {
	'admin/clipboard': path.resolve( process.cwd(), 'assets/js/admin', 'clipboard.js' ),
	'admin/image-upload': path.resolve( process.cwd(), 'assets/js/admin', 'image-upload.js' ),
	'divi': path.resolve( process.cwd(), 'assets/js', 'divi.js' ),
	'elementor-video': path.resolve( process.cwd(), 'assets/js', 'elementor-video.js' ),
	'embed-privacy': path.resolve( process.cwd(), 'assets/js', 'embed-privacy.js' ),
};
const scssFiles = {
	'astra': path.resolve( process.cwd(), 'assets/style/scss', 'astra.scss' ),
	'divi': path.resolve( process.cwd(), 'assets/style/scss', 'divi.scss' ),
	'elementor': path.resolve( process.cwd(), 'assets/style/scss', 'elementor.scss' ),
	'embed-privacy': path.resolve( process.cwd(), 'assets/style/scss', 'embed-privacy.scss' ),
	'embed-privacy-admin': path.resolve( process.cwd(), 'assets/style/scss', 'embed-privacy-admin.scss' ),
	'kadence-blocks': path.resolve( process.cwd(), 'assets/style/scss', 'kadence-blocks.scss' ),
	settings: path.resolve( process.cwd(), 'assets/style/scss', 'settings.scss' ),
	'shortcodes-ultimate': path.resolve( process.cwd(), 'assets/style/scss', 'shortcodes-ultimate.scss' ),
};

module.exports = [
	// JavaScript minification
	{
		mode: mode,
		devtool: ! isProduction ? 'source-map' : 'hidden-source-map',
		entry: jsFiles,
		output: {
			filename: '[name].min.js',
			path: path.resolve( process.cwd(), 'assets/js' ),
		},
		optimization: {
			minimize: true,
			minimizer: defaultConfig.optimization.minimizer,
		},
	},
	// compiled + minified CSS file
	{
		mode: mode,
		devtool: ! isProduction ? 'source-map' : 'hidden-source-map',
		entry: scssFiles,
		output: {
			path: path.resolve( process.cwd(), 'assets/style' ),
		},
		module: {
			rules: [
				{
					test: /\.(sc|sa)ss$/,
					use: [
						MiniCSSExtractPlugin.loader,
						{
							loader: 'css-loader',
							options: {
								sourceMap: ! isProduction,
								url: false,
							}
						},
						{
							loader: 'sass-loader',
							options: {
								sourceMap: ! isProduction,
								sassOptions: {
									minimize: true,
									outputStyle: 'compressed',
								}
							}
						},
					],
				},
			],
		},
		plugins: [
			new MiniCSSExtractPlugin( { filename: '[name].min.css' } ),
			new IgnoreEmitPlugin( [ '.js' ] ),
		],
	},
	// compiled CSS
	{
		mode: mode,
		devtool: ! isProduction ? 'source-map' : 'hidden-source-map',
		entry: scssFiles,
		output: {
			path: path.resolve( process.cwd(), 'assets/style' ),
		},
		module: {
			rules: [
				{
					test: /\.(sc|sa)ss$/,
					use: [
						MiniCSSExtractPlugin.loader,
						{
							loader: 'css-loader',
							options: {
								sourceMap: ! isProduction,
								url: false,
							}
						},
						{
							loader: 'sass-loader',
							options: {
								sourceMap: ! isProduction,
								sassOptions: {
									minimize: false,
									outputStyle: 'expanded',
								}
							}
						},
					],
				},
			],
		},
		plugins: [
			new MiniCSSExtractPlugin( { filename: '[name].css' } ),
			new IgnoreEmitPlugin( [ '.js' ] ),
		],
	},
];
