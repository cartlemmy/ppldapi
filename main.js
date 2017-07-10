var ppldapi = new function(){
	var self = this, _ppldapi = self, inst = (function(els,el,m, i){
		els = document.getElementsByTagName('script');
		for (i = 0; i < els.length; i++) {
			el = els[i];
			if (m = el.src.match(/inst\=(\d+)/)) {
				return m[1];
			}
		}
		return false;
	})(), modules = {}, alerts = [], sendQueue = [], config = {
		"RATE":1
	};
	
	if (window.top._ppldapi) {
		return;
	} 
	
	window.top._ppldapi = self;
	
	window.top._ppldapi.getModule = function(n) {
		if (modules[n]) return modules[n];
		return null;
	}
	
	var lastUserMessage = null;
	self.userMessage = function(txt, type) {
		if (txt == lastUserMessage) return;
		lastUserMessage = txt;
		if (!type) {
			txt = "&bull; "+txt;
			type = 'sub';
		}
		$('#user-message').append('<div class="'+type+'">'+txt+'</div>');
	}
	
	self.setStationStatus = function(txt) {
		$('#status-box').html(txt);
	}
	
	if (!inst) return;
	self.modulesInit = 0;
	
	modules.config = new function(){
		this.receive = function(n, params) {
			switch (n) {
				case "set":
					console.log('config: setting '+n+' to ',params);
					config[params[0]] = params[1];
					return true;
			}
			return false;
		}				
	}
	
	function loadModule(src, cb) {
		src = 'http://127.0.0.1/ppldapi/?js='+src;
				
		var el = document.createElement("script");
		el.type = "text/javascript";
		el.src = src;
		document.body.appendChild(el);
		
		el.addEventListener("load",function(){cb(true,src);},false);
		el.addEventListener("error",function(e){cb(false,e);},false);
	}
	
	$(function() {
		function check(wait) {
			setTimeout(function(){
				var actions = [], p = {
					"upd":inst
				}, url;
				if (!self.modulesInit) actions.push('info');
				if (actions.length) p.a = actions.join(',');
				if (sendQueue.length) p.sq = JSON.stringify(sendQueue.shift());
				
				url = 'http://127.0.0.1/ppldapi/?'+$.param(p);
				
				console.log(url);
				$.ajax({
					url: url
				}).done(function(data){
					var i, args, dec, n, action;
					if (data === "STOP") return;
					if (data === "WAIT") {
						check(5000*config.RATE);
						return;
					}
					if (data) {
						data = data.split("\n");
						for (i = 0; i < data.length; i++) {
							args = data[i].split(':');
							if (args.length >= 3) {
								n = args.shift();
								action = args.shift();
								try {
									dec = JSON.parse(args.join(':'));
								} catch (e) {
									dec = args.join(':');
								}
								switch (action) {										
									case "info":
										self.modulesInit ++;
										if (modules[n]) {
											modules[n].setInfo(dec[0]);
										} else {
											modules[n] = new module(n, dec[0]);
											(function(modn){
												modules[n].send = function(n, v) {
													sendQueue.push([modn,n,v]);
												}
											})(n);
										}
										break;

									case "log":
										if (Array.isArray(dec)) {
											dec.unshift(n);
											console.log.apply(self,dec);
										} else {
											console.log(module, dec);
										}
										break;
									
									case "alert":
										if (alerts.indexOf(dec[0]) == -1) {
											alerts.push(dec[0]);
											alert(dec[1]);
										}
										break;
										
									case "info-updated":
										if (modules[n]) {
											modules[n].setInfo(dec[0]);
											break;
										}
										
									default:
										if (modules[n] && modules[n].receive) {											
											if (modules[n].receive(action, dec)) break;
										} 
										//console.log('No '+(modules[n] ? '' : 'modules or ')+'receiver for '+n+' / '+action, dec);							
										break;
								}
							}							
						}
					}
					check(1000*config.RATE);
				}).fail(function(j,txt){
					console.log(txt);
					check(5000*config.RATE);
				});
			},1000*config.RATE);
		}
		check(50);
	});
	
	function module(name, info) {
		var self = this, eh = [], emitStack = [];
		self.name = name;
		self.info = info;
		self.loaded = 0;
		
		self.on = function(match, func) {
			eh.push([match, func]);
		}
		
		self.userMessage = function(txt, type) {
			_ppldapi.userMessage(txt, type);
		}
		
		self.setStationStatus = function(status) {
			_ppldapi.setStationStatus(status);
		}
		
		self.emit = function() {
			var args = [], i, res;
			
			for (i = 0; i < arguments.length; i++) {
				args.push(arguments[i]);			
			}
			
			if (self.loaded < 2) {
				emitStack.push(args);
				return false;
			}
			
			//console.log('module '+name+' emit:', args);
			
			for (i = 0; i < eh.length; i++) {
				if (eh[i][0] != '*') {
					if ((eh[i][0] instanceof RegExp) && !args[0].match(eh[i][0])) continue;
					if (eh[i][0] != args[0]) continue;
				}
				res = eh[i][1].apply(self, args);
				if (res !== undefined && res !== false && res !== null) return res;
				break;
			}
			return false;
		}
			
		self.setInfo = function(n,v) {
			var i;
			if (typeof(n) == "object") {
				for (i in n) {
					self.setInfo(i, n[i]);
				}
				return;
			}
			if (self.info[n] === v) return;
			if (!self.emit('set-info',n,v)) self.info[n] = v;
		}
		
		function checkLoaded() {
			self.loaded ++;
			if (self.loaded < 2) return;
			
			while (f = emitStack.shift()) {
				self.emit.apply(self, f);
			}
		}
		
		self.jsDone = function(v) {
			checkLoaded();
			self.emit('module-js-done',name, v);
		}		
		
		if (info.src) {
			loadModule(name, function(success, res){
				var f;
				if (success) {
					checkLoaded();
					self.emit('module-loaded',name, self);
				} else {
					self.emit('module-load-failed',name, res);
				}
				
			});
		}
		
		console.log(name, info);
	}
};
