/*
 * This file is part of con4gis, the gis-kit for Contao CMS.
 * @package con4gis
 * @version 8
 * @author con4gis contributors (see "authors.txt")
 * @license LGPL-3.0-or-later
 * @copyright (c) 2010-2022, by Küstenschmiede GmbH Software & Design
 * @link https://www.con4gis.org
 */

const {CleanWebpackPlugin} = require('clean-webpack-plugin');
const webpack = require("webpack");
var path = require('path');
var config = {
  entry: {
    'cart': './src/Resources/public/src/js/cart.jsx',
    'openinghours': './src/Resources/public/src/js/openinghours.jsx'
  },
  mode: "development",
  output: {
    filename: '[name].js',
    path: path.resolve('./src/Resources/public/dist/js'),
    chunkFilename: '[name].bundle.js',
    publicPath: "bundles/gutesiooperator/dist/js/"
  },
  devtool: "inline-source-map",
  resolve: {
    modules: ['node_modules', 'Resources/public/src/js'],
    extensions: ['.jsx', '.js']
  },
  module: {
    rules: [
      {
        test: /\.(js|jsx)$/,
        exclude: /node_modules(?!\/ol)/,
        loader: "babel-loader",
        include: [
          path.resolve('.'),
          path.resolve('./src/Resources/public/js/'),
          path.resolve('./src/Resources/public/js/*'),
        ],
        options: {
          extends: path.resolve('.babelrc')
        }
      },
      {
        test: /\.css$/i,
        use: ['style-loader', 'css-loader'],
      },
      {
        test: /\.svg$/,
        loader: 'svg-inline-loader'
      },
      {
        test: /\.(eot|woff|ttf)/,
        loader: 'url-loader'
      },
      {
        test: /\.png$/,
        loader: 'file-loader'
      }
    ]
  }
};

module.exports = config;