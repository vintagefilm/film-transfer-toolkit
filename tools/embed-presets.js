/* node tools/embed-presets.js - copies core/data/datarate-presets.json into core/ftt-core.js. */
'use strict';
var fs = require('fs'), path = require('path');
var core = path.join(__dirname, '..', 'core', 'ftt-core.js');
var data = JSON.parse(fs.readFileSync(path.join(__dirname, '..', 'core', 'data', 'datarate-presets.json'), 'utf8'));
var s = fs.readFileSync(core, 'utf8');
var re = /(\/\* BEGIN DATARATE PRESETS \*\/\n)[\s\S]*?(\n  \/\* END DATARATE PRESETS \*\/)/;
if (!re.test(s)) { console.error('markers not found'); process.exit(1); }
s = s.replace(re, function (m, a, b) { return a + '  var DR = ' + JSON.stringify(data) + ';' + b; });
fs.writeFileSync(core, s);
console.log('embedded presets', data.version, data.as_of);
