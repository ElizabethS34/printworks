const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');

module.exports = {
    entry: [
        path.resolve('src', 'app.js'),
        path.resolve('src', 'styles.css'),
    ],
    output: {
        path: path.resolve('public', 'assets'),
        filename: 'app.js',
    },
    module: {
        rules: [
            {
                test: /\.js$/,
                exclude: /node_modules/,
                use: ['babel-loader'],
            },
            {
                test: /\.css$/,
                use: [
                    MiniCssExtractPlugin.loader,
                    'css-loader',
                    'postcss-loader',
                ],
            },
            {
                test: /\.(png|svg|jpe?g|gif)$/,
                include: /images/,
                use: [
                    {
                        loader: 'file-loader',
                        options: {
                            name: '[name].[ext]',
                            outputPath: 'images/',
                            publicPath: 'images/'
                        }
                    }
                ]
            },
        ],
    },
    plugins: [new MiniCssExtractPlugin()],
};
