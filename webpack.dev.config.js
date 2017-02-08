var extend = require('extend');
var common = require('./webpack.common.config');

module.exports = extend(common, {
    devServer: {
        host: "10.3.2.40",
        port: 9999,
        contentBase: 'asset/',
        // headers: { "Access-Control-Allow-Origin": "*" },
    }
});
