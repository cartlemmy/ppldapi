var curCamera = false;
function setCamera(v) {
	var port, cam = null;
	for (port in v) {
		cam = v[port];
		break;
	}
			
	if (cam) {
		curCamera = cam.Model;
	} else {
		curCamera = false;
	}
}

function setStatus(txt) {
	if (typeof(txt) == "string") txt = [txt];
	txt[0] = '<h3>'+txt[0]+'</h3>';
	if (curCamera) txt.push(curCamera);
	if (txt.length >= 2) txt[0] = txt[0]+txt.splice(1,1);
	self.setStationStatus(txt.join("<br>"));
}

self.receive = function(n, params) {
	switch (n) {
		case "gp-init":
			self.userMessage("Attempting to tether camera", "warning");
			setStatus('Tethering...');
			return true;
		
		case "tethered":
		case "gp-start":
			self.userMessage("Camera tethered","success");
			setStatus('Tethered');
			return true;
		
		case "gp-stop":
			setStatus('Camera disconnected');
			return true;
			
		case "gp-download":
			self.emit('photo-captured', params);
			self.userMessage("Photo captured");
			if (window.top._ppldapiPrompt) window.top._ppldapiPrompt('yes');
			return true;
		
		case "photo-ready":
			self.emit('photo-ready', params);
			return true;
			
		case "thumb":
			self.userMessage("Thumbnail created");
			self.emit('thumb-created', params);
			return true;
			
		case "thumb-located":
			self.emit('thumb-located', params);
			return true;
			
		case "error":
			self.userMessage("Could not tether camera"+(
				self.info.lastError ? "<br>("+self.info.lastError.message+")" : ""
			), "error");
			return true;
	}
	
	return false;
}

self.on('set-info', function(t, n, v) {
	switch (n) {
		case "attendee":
			self.send(n, v);
			break;
		
		case "cameras":
			setCamera(v);
			break;
	}
});

setCamera(self.info.cameras);
switch (self.info.lastAction) {
	case "tethered":
		self.userMessage("Camera tethered","success");
		setStatus('Tethered');
		break;
	
	default:
		setStatus('Camera disconnected');
		break;
}

