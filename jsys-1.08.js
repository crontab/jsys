//
//  JavaScript System Library v1.08
//
//  DOM Shortcuts and utilities for IE9+ and the rest of the sane browsers
//
//  (c) 2011-2012 Hovik Melikyan
//


(function () {

var STD_TRANSITION_TIME = 300;

var DEFAULT_DISPLAY = {
	// non-inline and non-block elements:
	MARQUEE:'inline-block', TABLE:'table', THEAD:'table-header-group',
	TBODY:'table-row-group', TFOOT:'table-footer-group', COL:'table-column',
	COLGROUP:'table-column-group', TR:'table-row', TD:'table-cell', TH:'table-cell',
	CAPTION:'table-caption', LI:'list-item', INPUT:'inline-block', TEXTAREA:'inline-block',
	KEYGEN:'inline-block', SELECT:'inline-block', BUTTON:'inline-block', ISINDEX:'inline-block',
	METER:'inline-block', PROGRESS:'inline-block', IMG:'inline-block',
}

var BLOCK_ELEMENTS = {
	HTML:1, BODY:1, P:1, DIV:1, LAYER:1, ARTICLE:1, ASIDE:1, FOOTER:1, HEADER:1,
	HGROUP:1, NAV:1, SECTION:1, ADDRESS:1, BLOCKQUOTE:1, FIGCAPTION:1, FIGURE:1,
	CENTER:1, HR:1, H1:1, H2:1, H3:1, H4:1, H5:1, H6:1, UL:1, MENU:1, DIR:1,
	OL:1, DD:1, DL:1, DT:1, FORM:1, LEGEND:1, FIELDSET:1, PRE:1, XMP:1,
	PLAINTEXT:1, LISTING:1, FRAMESET:1, FRAME:1, IFRAME:1, DETAILS:1, SUMMARY:1,
}

var p = Element.prototype;

p.$c = function (names)
	{ return this.getElementsByClassName(names) }

p.$c0 = function (names)
	{ return this.$c(names)[0] }

p.$C = function (name)
	{ return this.cls(name) ? this :
		this.parentNode ? this.parentNode.$C(name) : null }

p.$t = function (tag)
	{ return this.getElementsByTagName(tag) }

p.$t0 = function (tag)
	{ return this.$t(tag)[0] }

p.$T = function (tag)
	{ return this.nodeName === tag.toUpperCase() ? this :
		this.parentNode ? this.parentNode.$T(tag) : null }

p.$F = function ()
	{ return this.$T('FORM') }

p.add = function (c)
	{ return this.appendChild(c) }

p.ins = function (c)
	{ return this.insertBefore(c, this.firstChild) }

p.rm = function (c)
	{ return this.removeChild(c) }

p.rmAll = function ()
{
	while (this.hasChildNodes())
		this.removeChild(this.lastChild);
	return this;
}

p.rmSelf = function ()
	{ return this.parentNode.removeChild(this) }

p.insBefore = function (item)
	{ return this.parentNode.insertBefore(item, this) }

p.insAfter = function (item)
	{ return this.parentNode.insertBefore(item, this.nextSibling) }

p.within = function (parent)
	{ return this == parent || (this.parentNode && this.parentNode.within &&
		this.parentNode.within(parent)); }

p.each = function (func)
{
	for (var item = this.firstChild; item; item = item.nextSibling)
		func(item);
	return this;
}

p.find = function (func)
{
	for (var item = this.firstChild; item; item = item.nextSibling)
		if (func(item))
			return item;
	return null;
}

p.filter = function (func)
{
	var a = [];
	for (var item = this.firstChild; item; item = item.nextSibling)
		func(item) && a.push(item);
	return a;
}

p.map = function (func)
{
	var a = [];
	for (var item = this.firstChild; item; item = item.nextSibling)
		a.push(func(item));
	return a;
}

p.attr = function (name, val)
{
	if (typeof val == 'undefined')
		return this.getAttribute(name);
	else if (val === null)
		this.removeAttribute(name);
	else
		this.setAttribute(name, val);
	return this;
}

p.enbl = function (val)
{
	if (typeof val == 'undefined')
		return this.getAttribute('disabled') === null;
	else if (val)
		this.removeAttribute('disabled');
	else
		this.setAttribute('disabled', '');
	return this;
}

p.fire = function (evtName, bubbles, cancelable)
{
	var e = document.createEvent('HTMLEvents');
	e.initEvent(evtName || 'change', bubbles || false, cancelable || true);
	return this.dispatchEvent(e);
}

p.on = function (type, func)
{
	this.listeners || (this.listeners = {});
	this.listeners[type] || (this.listeners[type] = []);
	this.listeners[type].push(func);
	this.addEventListener(type, func, false);
	return this;
}

p.off = function (type, func)
{
	var list;
	if (!this.listeners || !(list = this.listeners[type]))
		return this;
	if (typeof func == 'undefined')
		while (list.length)
			this.removeEventListener(type, list.pop(), false);
	else
		this.removeEventListener(type, list.rm(func), false);
	return this;
}

p.timeout = function (func, delay)
{
	var that = this;
	return setTimeout(function () { func.call(that) }, delay || 0);
}


p = HTMLElement.prototype;

p.cls = function (name, addrm)
{
	var c = this.className ? this.className.split(' ') : [];
	var i = c.indexOf(name);
	if (typeof addrm == 'undefined')
		return i >= 0;
	if (i < 0 && addrm)
		c.push(name);
	else if (i >= 0 && !addrm)
		c.splice(i, 1);
	else
		return this;
	this.className = c.join(' ');
	return this;
}

p.css = function (a, b)
{
	if (typeof a == 'object')
		for (var i in a)
			a.hasOwnProperty(i) && (this.style[i] = a[i]);
	else if (typeof b == 'undefined')
		return ''; // not implemented (because basically not needed)
	else
		this.style[a] = b;
	return this;
}

p.hide = function ()
{
	if (this._clickaway)
	{
		document.removeEventListener('click', this._clickaway, true);
		delete this._clickaway;
	}
	if (this._keyaway)
	{
		document.removeEventListener('keydown', this._keyaway, true);
		delete this._keyaway;
	}
	this._rmAutoFade();
	if (this.style.display && this.style.display != 'none')
		this._saveDisp =  this.style.display;
	this.style.display = 'none';
	return this;
}

p.defDisp = function ()
	{ return DEFAULT_DISPLAY[this.nodeName] || (BLOCK_ELEMENTS[this.nodeName] ? 'block' : 'inline') }

p.show = function (disp)
{
	if (typeof disp == 'boolean')
		return disp ? this.show() : this.hide();
	this.style.display = disp || this._saveDisp || this.defDisp();
	return this;
}

p.shown = function ()
	{ return this.offsetWidth || this.offsetHeight }

p.autoFadeIn = function ()
	{ return this.timeout(function () { this.show()._setAutoFade(1) }) }

p.autoFadeOut = function (endFunc)
	{ return this._setAutoFade(0, typeof endFunc == 'function' ? endFunc : this.rmSelf) }

p._setAutoFade = function (flag, endFunc)
{
	this.timeout(function() {
		var s = 'all ' + STD_TRANSITION_TIME + 'ms ease-in-out';
		this.css({opacity: flag,
			WebkitTransition: s, MozTransition: s, OTransition: s, transition: s });
		endFunc && this.timeout(endFunc, STD_TRANSITION_TIME + 50);
	});
	return this;
}

p._rmAutoFade = function ()
	{ return this.css({opacity: '', WebkitTransition: '', MozTransition: '', OTransition: '', transition: ''}) }

p.beginClickaway = function (input)
{
	if (this._clickaway) return;
	var that = this;
	this._clickaway = function(e)
	{
		if (!e.target.within(that) && (!input || !e.target.within(input)))
			that.hide();
		return false;
	}
	document.addEventListener('click', this._clickaway, true);
	this._keyaway = function(e)
	{
		var key = e.charCode || e.keyCode;
		if (key == 27 || key == 9)
		{
			that.hide();
			return key != 27;
		}
	}
	document.addEventListener('keydown', this._keyaway, true);
	return this;
}

p.beginCapture = function ()
{
	if (this._capture) return;
	var that = this;
	this._capture = function(e)
	{
		that.oncapmove && that.oncapmove(e);
		e.preventDefault();
	}
	this._capend = function(e)
	{
		document.removeEventListener('mousemove', that._capture, true);
		document.removeEventListener('mouseup', that._capend, true);
		delete that._capture;
		delete that._capend;
		document.onselectstart = document.ondragstart = null;
		that.oncapend && that.oncapend(e);
		e.preventDefault();
	}
	document.addEventListener('mousemove', this._capture, true);
	document.addEventListener('mouseup', this._capend, true);
	document.onselectstart = document.ondragstart = function () { return false };
	return this;
}


p = HTMLFormElement.prototype;

p.subm = function ()
{
	this.fire('submit') && this.off('submit');
	this.submit();
}


p = HTMLInputElement.prototype;

p.chk = function (flag)
{
	if (typeof flag == 'undefined')
		return this.checked;
	if (this.checked != flag)
	{
		this.checked = flag;
		this.onchange && this.onchange();
	}
	return this;
}

p.val = function (v, fireChange)
{
	if (typeof v == 'undefined')
		return this.value;
	if (this.value != v)
	{
		this.value = v;
		fireChange && (this.fire(), this.fire('input'));
	}
	return this;
}

p.ival = function ()
	{ return parseInt(this.value, 10) || 0 }

p.fval = function ()
	{ return parseFloat(this.value) || 0 }


p = HTMLSelectElement.prototype;

p.val = function (v, fireChange)
{
	if (typeof v == 'undefined')
		return this.options[this.selectedIndex].value;
	for (var i = 0; i < this.options.length; i++)
		if (this.options[i].value == v)
		{
			this.selectedIndex = i;
			fireChange && (this.fire(), this.fire('input'));
			return this;
		}
	return this;
}


p = Array.prototype;

p.each = function (func)
{
	for (var i = 0; i < this.length; i++)
		func(this[i]);
	return this;
}

p.find = function (func)
{
	for (var i = 0; i < this.length; i++)
		if (func(this[i]))
			return this[i];
	return null;
}

p.filter = function (func)
{
	var item, a = [];
	for (var i = 0; i < this.length; i++)
		func(item = this[i]) && a.push(item);
	return a;
}

p.map = function (func)
{
	var a = [];
	for (var i = 0; i < this.length; i++)
		a.push(func(this[i]));
	return a;
}

p.last = function ()
	{ if (this.length) return this[this.length - 1]; }

p.rm = function (item)
{
	var i = this.indexOf(item);
	if (i >= 0)
		return this.splice(i, 1)[0];
}


for (i in p)
	if (p.hasOwnProperty(i) && typeof p[i] == 'function')
		HTMLCollection.prototype[i] = NodeList.prototype[i] = p[i];


p = String.prototype;

p.split2 = function (sep)
{
	var i = this.indexOf(sep);
	return i < 0 ? [this.substr()] : [this.substr(0, i), this.substr(i + sep.length)];
}

p.trim = function ()
	{ return this.replace(/^\s+|\s+$/g, ''); }

})();


// --- Common shortcuts


function $(obj)
	{ return (typeof obj == 'string') ? document.getElementById(obj) : obj }

function $n(url)
	{ if (url) window.location = url }

function $N(url)
	{ if (url) window.open(url, '_blank') }

function $f(n)
	{ return Math.floor(n) }

function isInt(n)
	{ return parseInt(n, 10) == n; }


// --- Misc. Utilities


function _html(s)
	{ return s.split('&').join('&amp;').split('<').join('&lt;').split('"').join('&quot;') }

function newText(text)
	{ return document.createTextNode(text) }

function newElem(tag, className, text)
{
	var elem = document.createElement(tag);
	if (className) elem.className = className;
	if (text) elem.add(newText(text)); 
	return elem;
}

function newDiv(className, text)
	{ return newElem('div', className, text) }

function newSpan(className, text)
	{ return newElem('span', className, text) }

function newInput(name, value, type, className)
{
	var elem = document.createElement('input');
	if (className) elem.className = className;
	elem.type = type || 'hidden';
	if (name) elem.name = name;
	if (value) elem.value = value;
	return elem;
}

function newA(href, text, className)
	{ return newElem('a', className, text).attr('href', href || '') }

function newLi(text, className)
	{ return newElem('li', className, text) }

function newButton(html, onClick, autofocus)
{
	var btn = newElem('button');
	if (autofocus) btn.attr('autofocus', 1);
	if (onClick) btn.onclick = onClick;
	btn.innerHTML = html;
	return btn;
}

function newOption(value, text)
{
	var o = newElem('option', '', text);
	o.value = value;
	return o;
}

function _log()
	{ console.log(Array.prototype.slice.call(arguments)) }

function _loga(a)
	{ for (var i in a) _log(i + ': ' + a[i]) }

function propCnt(obj)
{
	var size = 0;
	for (var key in obj)
		if (obj.hasOwnProperty(key)) size++;
	return size;
}

function getProps(obj)
{
	var keys = [];
	for (var key in obj)
		if (obj.hasOwnProperty(key))
			keys.push(key);
	return keys;
}

function parseIni(data, extras, noerr)
{
	data = data.split('\n');
	var ini = {};
	for (var i = 0; i < data.length; i++)
	{
		var s = data[i];
		if (!s || s[0] == '#')
			continue;
		var t = s.split2('=');
		if (t[0].match(/^[.-]?\w+$/) && t.length == 2)
		{
			if (t[0][0] != '.')
				ini[t[0]] = t[1];
			else if (typeof extras == 'object')
				extras[t[0].substr(1)] = t[1];
		}
		else if (!noerr)
		{
			alert('Internal data error.');
			return {};
		}
	}
	return ini;
}

function stripslashes(s)
{
	return s.replace(/\\(.?)/g, function (s, n1) {
		switch (n1) {
			case '\\': return '\\';
			case 'n': return '\n';
			case 't': return '\t';
			case '': return '';
			default: return n1;
		}
	});
}


// --- XMLHttpRequest


function httpSend(method, url, callback, data)
{
	var http = new XMLHttpRequest();
	http.open(method, url, !!callback);
	if (data)
		http.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
	if (callback)
		http.onreadystatechange = function ()
			{
				if (http.readyState != 4) return;
				if (http.status != 200 && http.status != 304)
					{ alert('Unexpected HTTP request error'); return }
				callback(http);
			};
	http.send(data);
	return callback ? http : http.responseText;
}


function httpGet(url, callback)
	{ return httpSend('GET', url, callback) }



// --- SHOW MODAL WINDOW


var DLG_OK =		0x0001;
var DLG_YESNO =		0x0002;
var DLG_SUBMIT =	0x0004;
var DLG_ERRMSG =	0x0008 | DLG_OK;
var DLG_CLICKAWAY = 0x2000;


var _modalCnt = 0;

function showModal(content, flags, yesFunc)
{
	flags || (flags = 0);
	var win = newElem('div', 'modal-win');

	win.hide = function ()
	{
		HTMLElement.prototype.hide.call(this);
		this.timeout(Element.prototype.rmSelf);
		if (--_modalCnt == 0)
			$('greyall').autoFadeOut(function () { this.hide() });
	}

	if (flags & DLG_CLICKAWAY)
		win.beginClickaway();

	var body = win.add(newDiv('modal-body' + (flags & DLG_ERRMSG ? ' errmsg' : '')));
	body.add(newDiv('rxwrap'));
	body.add(newDiv('rx')).onclick = function () { win.hide() };
	body.add(newDiv('modal-content'));
	if (flags & (DLG_OK | DLG_YESNO | DLG_SUBMIT))
	{
		var btnDiv = body.add(newDiv('modal-buttons'));
		if (flags & DLG_YESNO)
		{
			btnDiv.add(newButton('Yes',
				function () { win.hide(); yesFunc() },
				true));
			btnDiv.add(newButton('No', function() { win.hide() }));
		}
		else if (flags & DLG_SUBMIT)
			btnDiv.add(newButton('Submit',
				function () { win.hide(); yesFunc() },
				true));
		else if (flags & DLG_OK)
			btnDiv.add(newButton('OK',
				function ()
					{ win.hide(); window._errobj && window._errobj.select() },
				true));
	}
	$('page').add(win);
	win.$c0('modal-content').innerHTML =
		typeof content == 'function' ? content() :
			typeof content == 'object' ? content.innerHTML :
				(content || '');
	$('greyall').autoFadeIn();
	_modalCnt++;
	return win.show();
}


function notimpl()
	{ showModal('Feature not implemented yet.', DLG_OK) }

