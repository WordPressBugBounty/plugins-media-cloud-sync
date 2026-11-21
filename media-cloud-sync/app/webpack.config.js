const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const BrowserSyncPlugin = require('browser-sync-webpack-plugin');
// const WebpackDevServer = require('webpack-dev-server');
const TerserPlugin = require('terser-webpack-plugin');

require('dotenv').config({ quiet: true });

const webpackConfig = {
	devtool: 'source-map',

	entry: {
		backend: path.resolve(__dirname, 'src/App.js')
	},

	output: {
		filename: '[name].js',
		path: path.resolve(__dirname, '../assets/js')
	},

	resolve: {
		extensions: ['.js', '.jsx', '.json']
	},
	devServer: {},

	plugins: [
		new MiniCssExtractPlugin({
			// Options similar to the same options in webpackOptions.output
			// both options are optional
			filename: '../css/[name].css',
			chunkFilename: '../css/[id].css'
		})
	],
	module: {
		rules: [
			{
				test: /\.(js|jsx)$/,
				exclude: /(node_modules|bower_components)/,
				use: {
					loader: 'babel-loader',
					options: {
						presets: ['@babel/preset-env', '@babel/preset-react'],
						plugins: [
							['@babel/plugin-proposal-class-properties'],
							[
								'@wordpress/babel-plugin-makepot',
								{
									output: '../languages/media-cloud-sync-en_US.po'
								}
							]
						]
					}
				}
			},

			{
				test: /\.scss$/,
				use: [
					{
						loader: MiniCssExtractPlugin.loader,
						options: {
							// you can specify a publicPath here
							// by default it use publicPath in webpackOptions.output
							publicPath: '../'
						}
					},
					// {loader: 'style-loader'},
					{
						loader: 'css-loader',
						options: {
							url: false,
							modules: {
								localIdentName: 'wpmcs_[local][hash:base64:5]',
								exportLocalsConvention: 'camelCase',
								namedExport: false
							}
						}
					},
					{ loader: 'sass-loader' },
					{
						loader: 'sass-resources-loader',
						options: {
							sourceMap: true,
							resources: [path.resolve('src/variables.scss')]
						}
					}
				]
			},
			{
				test: /\.css$/,
				use: [
					{
						loader: 'style-loader'
					},
					{
						loader: 'css-loader',
						options: {
							url: false
						}
					}
				]
			},

			//   {
			//     test: /\.(css|scss|sass)$/,
			//     use: [
			//       {
			//         loader: MiniCssExtractPlugin.loader,
			//         options: {
			//           // you can specify a publicPath here
			//           // by default it use publicPath in webpackOptions.output
			//           publicPath: '../'
			//         }
			//       },
			//       "css-loader",
			//       "sass-loader"
			//
			//     ]
			// },
			{
				test: /\.(png|jpg|gif|svg)$/i,
				use: [
					{
						// loader: 'file-loader?name=[name].[ext]',
						loader: 'url-loader'
						// options: {
						//     limit: 0,
						//     name: '../images/[name].[ext]',
						//     publicPath: './'
						//
						// },
					}
				]
			}
		]
	},
	optimization: {
		minimizer: [
			new TerserPlugin({
				terserOptions: {
					ecma: undefined,
					warnings: false,
					parse: {},
					compress: {
						drop_console: process.env.NODE_ENV === 'production'
					},
					mangle: {
						reserved: ['__']
					},

					module: false,
					output: null,
					toplevel: false,
					nameCache: null,
					ie8: false,
					keep_classnames: undefined,
					keep_fnames: false,
					safari10: false
				}
			})
		]
	}
};

if (process.env.NODE_ENV === 'production') {
	webpackConfig.devtool = false;
} else if (process.env.PROXY) {
	// `yarn start` only: reloads the WP admin (proxied) after each rebuild; settings in app/.env.
	webpackConfig.plugins.push(
		new BrowserSyncPlugin(
			{
				host: process.env.HOST,
				// Only this machine can reach the dev proxy.
				listen: process.env.HOST,
				port: process.env.PORT,
				proxy: process.env.PROXY,
				// PRO add-on's build output (its own `yarn start`), so one tab reloads for both.
				files: [
					path.resolve(__dirname, '../../media-cloud-sync-pro/assets/js/**/*.js'),
					path.resolve(__dirname, '../../media-cloud-sync-pro/assets/css/**/*.css')
				]
			},
			{ reload: true }
		)
	);
}

module.exports = webpackConfig;
