/*
 * This file is part of con4gis, the gis-kit for Contao CMS.
 * @package con4gis
 * @author con4gis contributors (see "authors.md")
 * @license LGPL-3.0-or-later
 * @copyright (c) 2010-2026, by Küstenschmiede GmbH Software & Design
 * @link https://www.con4gis.org
 */

const {CleanWebpackPlugin} = require('clean-webpack-plugin');
const TerserPlugin = require('terser-webpack-plugin');
var path = require('path');
var config = {
  entry: {
    'cart': './src/Resources/public/src/js/cart.jsx',
    'openinghours': './src/Resources/public/src/js/openinghours.jsx',
    'phonehours': './src/Resources/public/src/js/phonehours.jsx',
  },
  mode: "production",
  output: {
    filename: '[name].js',
    path: path.resolve('./src/Resources/public/dist/js'),
    chunkFilename: '[name].bundle.js',
    publicPath: "bundles/gutesiooperator/dist/js/"
  },
  resolve: {
    roots: ['node_modules', 'src/Resources/public/src/js'],
    extensions: ['.jsx', '.js']
  },
  optimization: {
    minimize: true,
    minimizer: [new TerserPlugin()]
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