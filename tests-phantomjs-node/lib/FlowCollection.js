"use strict";

var Promise = require('promise');
var Class = require('./Class').Class;
var assert = require("assert");

// Flows
var BaseOAuthFlow = require('./flows/BaseOAuthFlow').BaseOAuthFlow;
// var BadRedirectURI = require('./flows/BadRedirectURI').BadRedirectURI;



var emptyPromise = new Promise(function(resolve) { 
	// console.log(" At least first empty promise was fulfilled");
	resolve(); 
});

// Credits: http://trevorburnham.com/presentations/flow-control-with-promises/#/16
var promiseWaterfall = function(tasks) {
	var finalTaskPromise = tasks.reduce(function(prevTaskPromise, task) {
		return prevTaskPromise.then(function() {
			return task;
		});
	}, emptyPromise);
	return finalTaskPromise;
}





var FlowCollection = Class.extend({
	"init": function(ph, oauth) {
		this.ph = ph;
		this.oauth = oauth;
		this.flows = [];

		this.flows.push(new BaseOAuthFlow(ph, oauth));
		// this.flows.push(new BadRedirectURI(oauth));
		
		this.completed = false;
		this.onCompleted = null;


	},
	"run": function() {
		var i, that = this;

		// describe('Run flow [' + that.flows[0].title + ']', function() {


		// 	var f = that.flows[0].run();

		// 	it('Flow completed', function(done) {
		// 		f
		// 			.then(function() {
		// 				assert(true, "Flow completed");
		// 				done();						
		// 			});

		// 	});


		// });


		

		var waterfall = promiseWaterfall(
			that.flows.map(function(cflow) {
				return cflow.run();
			})
		);

		return waterfall
			.then(function() {
				console.log(" -- - - - - - - -  DONE with everything!!");
			});




	},

	"completed": function() {
		var that = this;
		return new Promise(function(resolve, reject) {
			if (that.completed) {
				return resolve();
			}
			that.onCompleted = resolve;
		});
	}

});


exports.FlowCollection = FlowCollection;

