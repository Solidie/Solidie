const path = require('path');
const TerserPlugin = require('terser-webpack-plugin');
const CssMinimizerPlugin = require('css-minimizer-webpack-plugin');
const UglifyJsPlugin = require('uglifyjs-webpack-plugin');

module.exports = (env, options) => {
	const mode = options.mode || 'development';

	const config = {
		mode,
		module: {
			rules: [
				{
					test: /\.(js|jsx)$/,
					exclude: /node_modules/,
					use: 'babel-loader',
				},
				{
					test: /\.(png|jp(e*)g|svg|gif)$/,
					use: [
						{
							loader: 'file-loader',
							options: {
								name: 'images/[hash]-[name].[ext]',
							},
						},
					],
				},
			],
		},
		devtool: 'source-map',
	};

	if ('production' === mode) {
		var minimizer = !env.build
			? new TerserPlugin({
					terserOptions: {},
					minify: (file) => {
						const uglifyJsOptions = {
							sourceMap: true,
						};
						return require('uglify-js').minify(file, uglifyJsOptions);
					},
			  })
			: new TerserPlugin({
					terserOptions: {},
					minify: (file) => {
						const uglifyJsOptions = {
							sourceMap: false,
						};
						return require('uglify-js').minify(file, uglifyJsOptions);
					},
			  });

		config.devtool = false;
		config.optimization = {
			// minimize: true,
			// minimizer: [minimizer, new CssMinimizerPlugin()],
			minimizer: [
				// we specify a custom UglifyJsPlugin here to get source maps in production
				new UglifyJsPlugin({
					cache: true,
					parallel: true,
					uglifyOptions: {
						compress: false,
						ecma: 6,
						mangle: true,
					},
					// sourceMap: true,
				}),
				new CssMinimizerPlugin(),
			],
		};
	}

	var react_blueprints = [
		{
			dest_path: './dist',
			src_files: {
				'dashboard': './components/Dashboard.jsx'
			},
		}
	];

	var configEditors = [];
	for (let i = 0; i < react_blueprints.length; i++) {
		let { src_files, dest_path } = react_blueprints[i];

		configEditors.push(
			Object.assign({}, config, {
				name: 'configEditor',
				entry: src_files,
				output: {
					path: path.resolve(dest_path),
					filename: `[name].js`,
				},
			}),
		);
	}

	return [...configEditors];
};