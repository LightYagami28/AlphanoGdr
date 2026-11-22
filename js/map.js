(function() {
    var _raf = window.requestAnimationFrame;

    var _isTouch = 'ontouchend' in document;

    // this is simple, no "deep" support
    var _extend = function() {
        for (var i=1; i<arguments.length; i++) {
            for (var key in arguments[i]) {
                if (arguments[i].hasOwnProperty(key)) {
                    arguments[0][key] = arguments[i][key];
                }
            }
        }
        return arguments[0];
    };
	


    var VanillaKinetic = function(element, settings) {
        this.settings = _extend({}, VanillaKinetic.DEFAULTS, settings);
        this.el       = element;
        this.ACTIVE_CLASS = "kinetic-active";

        this._initElements();

        this.el._VanillaKinetic = this;
        return this;
    };
	

    VanillaKinetic.DEFAULTS = {
        cursor: 'move',
        decelerate: true,
        triggerHardware: false,
        threshold: 0,
		
        y: true,
        x: true,
        slowdown: 0.9,
        maxvelocity: 40,
        throttleFPS: 60,
        invert: false,
        movingClass: {
            up: 'kinetic-moving-up',
            down: 'kinetic-moving-down',
            left: 'kinetic-moving-left',
            right: 'kinetic-moving-right'
        },
        deceleratingClass: {
            up: 'kinetic-decelerating-up',
            down: 'kinetic-decelerating-down',
            left: 'kinetic-decelerating-left',
            right: 'kinetic-decelerating-right'
        }
    };

    // Public functions

    VanillaKinetic.prototype.start = function(options) {
        this.settings = _extend(this.settings, options);
        this.velocity = options.velocity || this.velocity;
        this.velocityY = options.velocityY || this.velocityY;
        this.settings.decelerate = false;
        this._move();
    };

    VanillaKinetic.prototype.end = function() {
        this.settings.decelerate = true;
    };

    VanillaKinetic.prototype.stop = function() {
        this.velocity = 0;
        this.velocityY = 0;
        this.settings.decelerate = true;
        if (typeof this.settings.stopped === 'function') {
            this.settings.stopped.call(this);
        }
    };

    VanillaKinetic.prototype.detach = function() {
        this._detachListeners();
        this.el.classList.remove(this.ACTIVE_CLASS);
        this.el.style.cursor = '';
    };

    VanillaKinetic.prototype.attach = function() {
        if (this.el.classList.contains(this.ACTIVE_CLASS)) {
            return;
        }
        this._attachListeners();
        this.el.classList.add(this.ACTIVE_CLASS);
        this.el.style.cursor = this.settings.cursor;
    };

    // Internal functions

    VanillaKinetic.prototype._initElements = function() {
        this.el.classList.add(this.ACTIVE_CLASS);

        _extend(this, {
            xpos: null,
            prevXPos: false,
            ypos: null,
            prevYPos: false,
            mouseDown: false,
            throttleTimeout: 1000 / this.settings.throttleFPS,
            lastMove: null,
            elementFocused: null
        });

        this.velocity = 0;
        this.velocityY = 0;

        this.documentResetHandler = () => this._resetMouse();

        this._initEvents();

        this.el.style.cursor = this.settings.cursor;

        if (this.settings.triggerHardware) {
            var prefixes = ['', '-ms-', '-webkit-', '-moz-'];
            var styles = {
                'transform': 'translate3d(0,0,0)',
                'perspective': '1000px',
                'backface-visibility': 'hidden'
            };
            for (var prefix of prefixes) {
                for (var key in styles) {
                    if (styles.hasOwnProperty(key)){
                        this.el.style[prefix+key] = styles[key];
                    }
                }
            }
        }
    };

    VanillaKinetic.prototype._initEvents = function() {
        this.settings.events = {
            touchStart: e => {
                var touch;
                if (this._useTarget(e.target, e)) {
                    if(e.originalEvent === undefined){
                        touch = e.touches[0];
                    }else{ touch = e.originalEvent.touches[0];}
                    this.threshold = this._threshold(e.target, e);
                    this._start(touch.clientX, touch.clientY);
                    e.stopPropagation();
                }
            },
            touchMove: e => {
                var touch;
                if (this.mouseDown) {
                    if(e.originalEvent === undefined){
                        touch = e.touches[0];
                    }else{ touch = e.originalEvent.touches[0];}
                    this._inputmove(touch.clientX, touch.clientY);
                    if (e.preventDefault) {
                        e.preventDefault();
                    }
                }
            },
            inputDown: e => {
                if (this._useTarget(e.target, e)) {
                    this.threshold = this._threshold(e.target, e);
                    this._start(e.clientX, e.clientY);
                    this.elementFocused = e.target;
                    if (e.target.nodeName === "DIV" && e.target.className === "test_") {
                        e.preventDefault();
                    }
                    e.stopPropagation();
                }
            },
            inputEnd: e => {
                if (this._useTarget(e.target, e)) {
                    this._end();
                    this.elementFocused = null;
                    if(e.touches && e.touches[0] && e.touches[0].target instanceof HTMLImageElement && e.touches[0].target.attributes[0]){
                        window.location.href = e.touches[0].target.getAttribute('href');
                    }
                    if (e.preventDefault && e.cancelable) {
                        e.preventDefault();
                    }
                }
            },
            inputMove: e => {
                if (this.mouseDown) {
                    this._inputmove(e.clientX, e.clientY);
                    if (e.preventDefault) {
                        e.preventDefault();
                    }
                }
            },
            scroll: e => {
                if (typeof this.settings.moved === 'function') {
                    this.settings.moved.call(this, this.settings);
                }
                if (e.preventDefault) {
                    e.preventDefault();
                }
            },
            inputClick: e => {
                if (Math.abs(this.velocity) > 0 || Math.abs(this.velocityY) > 0) {
                    e.preventDefault();
                    if (e.stopPropagation) {
                        e.stopPropagation();
                    }
                    return false;
                }
            },
            dragStart: e => {
                if (this._useTarget(e.target, e) && this.elementFocused) {
                    if (e.preventDefault) {
                        e.preventDefault();
                    }
                    if (e.stopPropagation) {
                        e.stopPropagation();
                    }
                    return false;
                }
            },
            selectStart: (...args) => {
                const e = args[0];
                if (typeof this.settings.selectStart === 'function') {
                    return this.settings.selectStart.apply(this, args);
                }
                if (this._useTarget(e.target, e)) {
                    if (e.preventDefault) {
                        e.preventDefault();
                    }
                    if (e.stopPropagation) {
                        e.stopPropagation();
                    }
                    return false;
                }
            }
        };

        this._attachListeners();
    };

    VanillaKinetic.prototype._blurFocused = function() {
        if (!this.elementFocused) { return; }
        this.elementFocused.blur();
        this.elementFocused = null;
        this.el.focus();
    };

    VanillaKinetic.prototype._scrollByDelta = function(movedX, movedY) {
        var scrollLeft = this.scrollLeft();
        var scrollTop = this.scrollTop();
        this.scrollLeft(this.settings.x ? scrollLeft - movedX : scrollLeft);
        this.scrollTop(this.settings.y ? scrollTop - movedY : scrollTop);
    };

    VanillaKinetic.prototype._inputmove = function(clientX, clientY) {
        if (this.lastMove && new Date() <= new Date(this.lastMove.getTime() + this.throttleTimeout)) {
            return;
        }
        this.lastMove = new Date();
        if (!this.mouseDown || (!this.xpos && !this.ypos)) {
            return;
        }
        var movedX = clientX - this.xpos;
        var movedY = clientY - this.ypos;
        if (this.settings.invert) {
            movedX *= -1;
            movedY *= -1;
        }
        if (this.threshold > 0) {
            var moved = Math.hypot(movedX, movedY);
            if (this.threshold > moved) {
                return;
            }
            this.threshold = 0;
        }
        this._blurFocused();
        this.settings.decelerate = false;
        this.velocity = this.velocityY = 0;
        this._scrollByDelta(movedX, movedY);
        this.prevXPos = this.xpos;
        this.prevYPos = this.ypos;
        this.xpos = clientX;
        this.ypos = clientY;
        this._calculateVelocities();
        this._setMoveClasses(this.settings.movingClass);
        if (typeof this.settings.moved === 'function') {
            this.settings.moved.call(this, this.settings);
        }
    };

    VanillaKinetic.prototype._calculateVelocities = function() {
        this.velocity = this._capVelocity(this.prevXPos - this.xpos, this.settings.maxvelocity);
        this.velocityY = this._capVelocity(this.prevYPos - this.ypos, this.settings.maxvelocity);
        if (this.settings.invert) {
            this.velocity *= -1;
            this.velocityY *= -1;
        }
    };

    VanillaKinetic.prototype._end = function() {
        if (this.xpos && this.prevXPos && this.settings.decelerate === false) {
            this.settings.decelerate = true;
            this._calculateVelocities();
            this.xpos = this.prevXPos = this.mouseDown = false;
            this._move();
        }
    };

    VanillaKinetic.prototype._useTarget = function(target, event) {
        if (typeof this.settings.filterTarget === 'function') {
            return this.settings.filterTarget.call(this, target, event) !== false;
        }
        return true;

    };

    VanillaKinetic.prototype._threshold = function(target, event) {
        if (typeof this.settings.threshold === 'function') {
            return this.settings.threshold.call(this, target, event);
        }
        return this.settings.threshold;
    };

    VanillaKinetic.prototype._start = function(clientX, clientY) {
        this.mouseDown = true;
        this.velocity = this.prevXPos = 0;
        this.velocityY = this.prevYPos = 0;
        this.xpos = clientX;
        this.ypos = clientY;
    };

    VanillaKinetic.prototype._resetMouse = function() {
        this.xpos = false;
        this.ypos = false;
        this.mouseDown = false;
    };

    VanillaKinetic.prototype._decelerateVelocity = function(velocity, slowdown) {
        return Math.floor(Math.abs(velocity)) === 0 ? 0 // is velocity less than 1?
            : velocity * slowdown; // reduce slowdown
    };

    VanillaKinetic.prototype._capVelocity = function(velocity, max) {
        var newVelocity = velocity;
        if (velocity > 0){
            if (velocity > max){
                newVelocity = max;
            }
        } else if (velocity < (0 - max)){
            newVelocity = (0 - max);
        }
        return newVelocity;
    };

    VanillaKinetic.prototype._setMoveClasses = function(classes) {
        // Solo le classi gestite da kinetic vengono rimosse: gli altri stati
        // dell'elemento non vengono toccati.
        var settings = this.settings;
        var el = this.el;

        el.classList.remove(
            settings.movingClass.up, settings.movingClass.down,
            settings.movingClass.left, settings.movingClass.right,
            settings.deceleratingClass.up, settings.deceleratingClass.down,
            settings.deceleratingClass.left, settings.deceleratingClass.right
        );

        if (this.velocity > 0){
            el.classList.add(classes.right);
        }
        if (this.velocity < 0){
            el.classList.add(classes.left);
        }
        if (this.velocityY > 0){
            el.classList.add(classes.down);
        }
        if (this.velocityY < 0){
            el.classList.add(classes.up);
        }
    };

    VanillaKinetic.prototype._moveAxis = function(scroller, settings, axis, sizeProp, velocityProp, scrollFn) {
        if (!settings[axis] || scroller[sizeProp] <= 0) {
            this[velocityProp] = 0;
            return;
        }
        this[scrollFn](this[scrollFn]() + this[velocityProp]);
        if (Math.abs(this[velocityProp]) > 0 && settings.decelerate) {
            this[velocityProp] = this._decelerateVelocity(this[velocityProp], settings.slowdown);
        }
    };

    VanillaKinetic.prototype._move = function() {
        var scroller = this._getScroller();
        var settings = this.settings;

        this._moveAxis(scroller, settings, 'x', 'scrollWidth', 'velocity', 'scrollLeft');
        this._moveAxis(scroller, settings, 'y', 'scrollHeight', 'velocityY', 'scrollTop');

        this._setMoveClasses(settings.deceleratingClass);

        if (typeof settings.moved === 'function'){
            settings.moved.call(this, settings);
        }

        if (Math.abs(this.velocity) > 0 || Math.abs(this.velocityY) > 0){
            if (!this.moving) {
                this.moving = true;
                // tick for next movement
                _raf(() => {
                    this.moving = false;
                    this._move();
                });
            }
        }
        else {
            this.stop();
        }
    };

    VanillaKinetic.prototype._getScroller = function() {
        // Lo scroller è sempre l'elemento a cui kinetic è associato.
        return this.el;
    };

    VanillaKinetic.prototype.scrollLeft = function(left) {
        var scroller = this._getScroller();
        if (typeof left === 'number') {
            scroller.scrollLeft = left;
            this.settings.scrollLeft = left;
        }
        else {
            return scroller.scrollLeft;
        }
    };

    VanillaKinetic.prototype.scrollTop = function(top) {
        var scroller = this._getScroller();
        if (typeof top === 'number') {
            scroller.scrollTop = top;
            this.settings.scrollTop = top;
        }
        else {
            return scroller.scrollTop;
        }
    };

    VanillaKinetic.prototype._attachListeners = function() {
        var el = this.el;
        var settings = this.settings;

        document.documentElement.addEventListener("mouseup", this.documentResetHandler, false);
        document.documentElement.addEventListener("click", this.documentResetHandler, false);

        if (_isTouch) {
            el.addEventListener('touchstart', settings.events.touchStart, false);
            el.addEventListener('touchend', settings.events.inputEnd, false);
            el.addEventListener('touchmove', settings.events.touchMove, false);
        }

        el.addEventListener('mousedown', settings.events.inputDown, false);
        el.addEventListener('mouseup', settings.events.inputEnd, false);
        el.addEventListener('mousemove', settings.events.inputMove, false);

        el.addEventListener('click', settings.events.inputClick, false);
        el.addEventListener('scroll', settings.events.scroll, false);
        el.addEventListener('selectstart', settings.events.selectStart, false);
        el.addEventListener('dragstart', settings.events.dragStart, false);
    };

    VanillaKinetic.prototype._detachListeners = function() {
        var el = this.el;
        var settings = this.settings;

        document.documentElement.removeEventListener("mouseup", this.documentResetHandler, false);
        document.documentElement.removeEventListener("click", this.documentResetHandler, false);

        if (_isTouch) {
            el.removeEventListener('touchstart', settings.events.touchStart, false);
            el.removeEventListener('touchend', settings.events.inputEnd, false);
            el.removeEventListener('touchmove', settings.events.touchMove, false);
        }

        el.removeEventListener('mousedown', settings.events.inputDown, false);
        el.removeEventListener('mouseup', settings.events.inputEnd, false);
        el.removeEventListener('mousemove', settings.events.inputMove, false);

        el.removeEventListener('click', settings.events.inputClick, false);
        el.removeEventListener('scroll', settings.events.scroll, false);
        el.removeEventListener('selectstart', settings.events.selectStart, false);
        el.removeEventListener('dragstart', settings.events.dragStart, false);
    };


    window.VanillaKinetic = VanillaKinetic;
})();