'use strict';

String.prototype.toProperCase = function () {
    return this.replace(/\w\S*/g, function(txt){return txt.charAt(0).toUpperCase() + txt.substring(1).toLowerCase();});
};

// Script Modules
// Include as needed on your project. All scripts will be bundled together.
// Some sample files are included for ideas to get you started.
require('./scripts/form-field-mapping.js');
// require('./scripts/socials.js');
// require('./scripts/form-utilities.js');
// require('./scripts/progressive-fields.js');
