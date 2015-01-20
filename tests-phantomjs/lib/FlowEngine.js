

var _ =   function(func, wrapper) {
    return function() {
      var args = [func].concat(slice.call(arguments, 0));
      return wrapper.apply(this, args);
    };
  };


var FlowEngine = function(page) {

	var that = this;
	this.page = page;
	this.states = {};
	this.current = '_init_';

	this.nextCandidates = [];

	page.onConsoleMessage = function(msg) {
		console.log('[' + that.current + '] ›' + msg);
	};

	page.onLoadFinished = (function(x) {
		// console.log("Load page finnished ");
		return function(status) {
			// console.log("Loaded finnished. " + page.url);
			x.pageLoaded();
		};
	})(that);


};

FlowEngine.prototype.pageLoaded = function() {

	var test, i, next;

	console.log("-------------  ------------- ------------- -------------");

	// if (this.nextCandidates === false) {

	// 	console.log("Bypassing this step.");

	// }


	console.log("Page loaded from [" +  this.current + "] [" + this.page.url.substr(0, 160) + "]");
	console.log("About to process " + JSON.stringify(this.nextCandidates, undefined, 2));
	for(i = 0; i < this.nextCandidates.length; i++) {
		test = this.states[this.nextCandidates[i]].detect(this.page);
		if (!test) {
			console.log(" [ ] State not match: [" + this.nextCandidates[i] + "]");
		} else {
			next = this.states[this.nextCandidates[i]];
			this.current = this.nextCandidates[i];
			

			console.log(" [X] State did match [" + this.nextCandidates[i] + "]");
			console.log("     Setting next candidates to " + next.nextStates.join(','));
			console.log("     ------> Execute [" + this.nextCandidates[i] + "] <------");
			console.log("");

			this.nextCandidates = next.nextStates;
			return next.run(this.page);
		}
	}
	console.error("No states detected. Dumping output");
	console.log("----- --- --- -- -- - - .");
	console.log(this.page.content);

};

FlowEngine.prototype.addState = function(id, detect, run, nextStates)  {
	this.states[id] = {
		"detect": detect,
		"run": run,
		"nextStates": nextStates
	};

};


FlowEngine.prototype.go = function(url, nextStates) {

	this.nextCandidates = nextStates;
	this.page.open(url);

	// console.log("Config to go:"); console.log(JSON.stringify(this, undefined, 4));

};

FlowEngine.prototype.unexpected = function() {



};


exports.FlowEngine = FlowEngine;


