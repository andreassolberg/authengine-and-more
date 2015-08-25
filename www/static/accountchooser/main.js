define(function(require, exports, module) {
	"use strict";

	require('components/es6-promise/promise.min');


	// Configure console if not defined. A fix for IE <= 9.
	if (!window.console) {
		window.console = {
			"log": function() {},
			"error": function() {},
		}
	}

    var App = require('App');
	$(document).ready(function() {
	    var app = new App();
	});


});