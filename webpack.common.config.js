const path = require('path');
const webpack = require('webpack');
const HtmlWebpackPlugin = require('html-webpack-plugin');
const ExtractTextPlugin = require("extract-text-webpack-plugin");

const PATHS = {
    bootstrap: path.join(__dirname, 'src/App.js'),
    asset: path.join(__dirname, 'asset')
};

module.exports = {

    entry: PATHS.bootstrap,

    output: {
        path: PATHS.asset,
        filename: 'asset.js'
    },

    module: {
        loaders: [
            { test: /\.html/, loader: "underscore-template-loader" },
            { test: /\.scss$/, loader: ExtractTextPlugin.extract("style", "css!sass")},
        ]
    },

    plugins: [
        new HtmlWebpackPlugin({
            title: "vc-flowjs",
            template: "src/App.ejs"
        }),
        new webpack.ProvidePlugin({
            $: "jquery",
            _: "underscore",
            Backbone: "backbone",
        }),
        new ExtractTextPlugin('[name]/bundle.css'),
    ],

};
